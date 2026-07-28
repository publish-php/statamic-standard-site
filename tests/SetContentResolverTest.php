<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PublishPhp\StatamicStandardSite\SetContentResolver;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Fields\Fieldtype;
use Statamic\Fields\Value;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Real tests for SetContentResolver — the augmentation-unwrapping seam that
 * shipped broken in v1.1.7 (assets silently dropped because Fields::augment()
 * returns a lazy Value wrapper, not a resolved Asset).
 *
 * Statamic's full augmentation pipeline (Fields::augment()) needs the Laravel
 * container and can't run under plain PHPUnit. These tests therefore exercise
 * the two methods that form the seam — assetsFromAugmented() (Value
 * unwrapping) and mediaKind() (classification) — directly, against a minimal
 * Asset-contract stub and real Statamic Value wrappers. This is exactly the
 * logic that broke; the full pipeline integration is covered by the planned
 * fake-PDS e2e harness.
 */
class SetContentResolverTest extends TestCase
{
    /**
     * Expose the private asset-normalization + media-kind methods for testing.
     */
    private function expose(): object
    {
        // SetContentResolver's constructor takes a Fieldtype, but the methods
        // under test don't touch it. Pass a stub.
        $resolver = new SetContentResolver($this->createStub(Fieldtype::class));

        return new class ($resolver) {
            public function __construct(private SetContentResolver $r) {}

            public function assetsFromAugmented(mixed $augmented): array
            {
                return (fn () => $this->assetsFromAugmented($augmented))->call($this->r, );
            }

            public function mediaKind(AssetContract $asset): string
            {
                return (fn () => $this->mediaKind($asset))->call($this->r, );
            }
        };
    }

    // ── Value unwrapping (the v1.1.7 regression) ──

    public function test_value_wrapped_single_asset_is_unwrapped_and_resolved(): void
    {
        // This is the exact shape that broke: Fields::augment() yields a Value
        // wrapping the Asset, NOT a bare Asset. v1.1.7 checked `instanceof Asset`
        // on the wrapper and dropped everything.
        $asset = $this->makeAsset('mp4', 'https://cdn.example.com/talks/slide.mp4', 'A clip', 'video/mp4');
        $wrapped = new Value($asset, 'video', null, null);

        $result = $this->expose()->assetsFromAugmented($wrapped);

        $this->assertCount(1, $result);
        $this->assertSame('https://cdn.example.com/talks/slide.mp4', $result[0]->absoluteUrl());
    }

    public function test_bare_single_asset_still_works(): void
    {
        // Defense in depth: a non-wrapped Asset (e.g. shallowAugment) must also resolve.
        $asset = $this->makeAsset('png', 'https://cdn.example.com/img.png', 'Alt', 'image/png');

        $result = $this->expose()->assetsFromAugmented($asset);

        $this->assertCount(1, $result);
    }

    public function test_value_wrapped_null_yields_empty(): void
    {
        $wrapped = new Value(null, 'video', null, null);
        $this->assertSame([], $this->expose()->assetsFromAugmented($wrapped));
    }

    public function test_plain_null_yields_empty(): void
    {
        $this->assertSame([], $this->expose()->assetsFromAugmented(null));
    }

    public function test_value_wrapping_an_empty_collection_yields_empty(): void
    {
        $wrapped = new Value([], 'assets', null, null);
        $this->assertSame([], $this->expose()->assetsFromAugmented($wrapped));
    }

    // ── Media-kind classification (contract-stable, Statamic-aligned) ──

    public function test_media_kind_classifies_video_extensions(): void
    {
        $resolver = $this->expose();
        foreach (['mp4', 'webm', 'mov', 'm4v', 'ogv', 'h264'] as $ext) {
            $asset = $this->makeAsset($ext, 'https://cdn.example.com/x.' . $ext);
            $this->assertSame('video', $resolver->mediaKind($asset), "extension $ext should be video");
        }
    }

    public function test_media_kind_classifies_image_extensions(): void
    {
        $resolver = $this->expose();
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'] as $ext) {
            $asset = $this->makeAsset($ext, 'https://cdn.example.com/x.' . $ext);
            $this->assertSame('image', $resolver->mediaKind($asset), "extension $ext should be image");
        }
    }

    public function test_media_kind_classifies_audio_extensions(): void
    {
        $resolver = $this->expose();
        foreach (['mp3', 'ogg', 'wav', 'aac', 'flac', 'm4a'] as $ext) {
            $asset = $this->makeAsset($ext, 'https://cdn.example.com/x.' . $ext);
            $this->assertSame('audio', $resolver->mediaKind($asset), "extension $ext should be audio");
        }
    }

    public function test_media_kind_classifies_other_files_as_file(): void
    {
        $resolver = $this->expose();
        foreach (['pdf', 'txt', 'zip', 'svg', 'docx'] as $ext) {
            $asset = $this->makeAsset($ext, 'https://cdn.example.com/x.' . $ext);
            $this->assertSame('file', $resolver->mediaKind($asset), "extension $ext should be file");
        }
    }

    public function test_media_kind_is_case_insensitive(): void
    {
        $resolver = $this->expose();
        $asset = $this->makeAsset('MP4', 'https://cdn.example.com/x.MP4');
        $this->assertSame('video', $resolver->mediaKind($asset));
    }

    /**
     * Build a minimal Asset-contract stub — only the methods SetContentResolver
     * touches (extension, absoluteUrl, mimeType, get) plus no-op contract stubs.
     */
    private function makeAsset(string $ext, string $url, ?string $alt = null, ?string $mime = null): AssetContract
    {
        return new class ($ext, $url, $alt, $mime) implements AssetContract {
            public function __construct(
                private string $ext,
                private string $url,
                private ?string $alt,
                private ?string $mime,
            ) {}

            public function extension() { return $this->ext; }
            public function url() { return $this->url; }
            public function absoluteUrl() { return $this->url; }
            public function mimeType() { return $this->mime; }
            public function get($key, $fallback = null) { return $key === 'alt' ? $this->alt : $fallback; }
            public function filename() { return ''; }
            public function basename() { return ''; }
            public function container($container = null) { return null; }
            public function manipulate($params = null) { return ''; }
            public function isImage() { return false; }
            public function lastModified() { return null; }
            public function upload(UploadedFile $file) { return $this; }
            public function download(?string $name = null, array $headers = []) { return ''; }
            public function contents() { return ''; }
        };
    }
}
