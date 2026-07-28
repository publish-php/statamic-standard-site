<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PublishPhp\StatamicStandardSite\ContentConverter;
use PublishPhp\StatamicStandardSite\EntryMapper;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Fields\Value;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests for EntryMapper convention detection and blueprint overrides.
 *
 * Since EntryMapper depends on Statamic's Entry and Blueprint classes,
 * we test the logic that can be exercised without a full Statamic bootstrap.
 * The convention detection and override logic is tested via stub objects
 * that implement the minimal interface EntryMapper actually uses.
 */
class EntryMapperTest extends TestCase
{
    private ContentConverter $converter;
    private EntryMapper $mapper;

    protected function setUp(): void
    {
        $this->converter = new ContentConverter();
        $this->mapper = new EntryMapper($this->converter);
    }

    public function test_markdown_passthrough_to_markdown(): void
    {
        $input = "# Hello\n\nWorld";
        $result = $this->converter->toMarkdown($input);
        $this->assertSame($input, $result);
    }

    public function test_markdown_to_textcontent_strips_formatting(): void
    {
        $input = "# Hello\n\n**bold** and *italic*";
        $result = $this->converter->toTextContent($input);
        $this->assertStringNotContainsString('#', $result);
        $this->assertStringNotContainsString('**', $result);
        $this->assertStringNotContainsString('*', $result);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('bold and italic', $result);
    }

    public function test_bard_content_to_markdown_and_textcontent(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 1],
                    'content' => [['type' => 'text', 'text' => 'Title']],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Body with '],
                        ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]],
                    ],
                ],
            ],
        ];

        $markdown = $this->converter->toMarkdown($bard);
        $textContent = $this->converter->toTextContent($bard);

        $this->assertStringContainsString('# Title', $markdown);
        $this->assertStringContainsString('**bold**', $markdown);
        $this->assertStringContainsString('Title', $textContent);
        $this->assertStringContainsString('bold', $textContent);
        $this->assertStringNotContainsString('**', $textContent);
    }

    public function test_content_converter_excludes_sets(): void
    {
        $converter = new ContentConverter(excludedSets: ['newsletter']);

        $bard = [
            [
                'type' => 'text',
                'text' => '<p>Content</p>',
            ],
            [
                'type' => 'set',
                'attrs' => [
                    'values' => [
                        'type' => 'newsletter',
                        'heading' => 'Subscribe',
                    ],
                ],
            ],
        ];

        $result = $converter->toMarkdown($bard);
        $this->assertStringContainsString('Content', $result);
        $this->assertStringNotContainsString('Subscribe', $result);
    }

    public function test_entry_mapper_constructs_with_converter(): void
    {
        $mapper = new EntryMapper($this->converter);
        $this->assertInstanceOf(EntryMapper::class, $mapper);
    }

    public function test_sync_result_created(): void
    {
        $result = \PublishPhp\StatamicStandardSite\SyncResult::success('at://test', 'created');
        $this->assertTrue($result->success);
        $this->assertSame('created', $result->action);
    }

    public function test_sync_result_updated(): void
    {
        $result = \PublishPhp\StatamicStandardSite\SyncResult::success('at://test', 'updated');
        $this->assertTrue($result->success);
        $this->assertSame('updated', $result->action);
    }

    public function test_sync_result_deleted(): void
    {
        $result = \PublishPhp\StatamicStandardSite\SyncResult::success('', 'deleted');
        $this->assertTrue($result->success);
        $this->assertSame('deleted', $result->action);
    }

    // ── Cover / feature image detection + prepend ──

    public function test_cover_image_handle_conventions_and_override_registered(): void
    {
        // The auto-detection contract: conventional handles + an override.
        // Guards the documented "magic" list against accidental edits.
        $reflection = new \ReflectionClass(EntryMapper::class);

        $convention = $reflection->getConstant('CONVENTION');
        $this->assertArrayHasKey('cover_image', $convention);
        $this->assertContains('cover', $convention['cover_image']);
        $this->assertContains('feature_image', $convention['cover_image']);
        $this->assertContains('featured_image', $convention['cover_image']);
        // Bare `image` is deliberately excluded as too ambiguous.
        $this->assertNotContains('image', $convention['cover_image']);

        $overrides = $reflection->getConstant('OVERRIDES');
        $this->assertSame('standard_site_cover_image', $overrides['cover_image']);
    }

    public function test_prepend_cover_markdown_prepends_as_first_block(): void
    {
        $expose = $this->expose();

        // No cover → body untouched.
        $this->assertSame('Body.', $expose->prependCoverMarkdown(null, 'Body.'));

        // Cover + body → image first, blank line, then body.
        $this->assertSame(
            "![A cover](https://cdn.example.com/cover.jpg)\n\nBody.",
            $expose->prependCoverMarkdown(['url' => 'https://cdn.example.com/cover.jpg', 'alt' => 'A cover'], 'Body.'),
        );

        // Cover + empty body → just the image (no trailing blank line).
        $this->assertSame(
            '![A cover](https://cdn.example.com/cover.jpg)',
            $expose->prependCoverMarkdown(['url' => 'https://cdn.example.com/cover.jpg', 'alt' => 'A cover'], ''),
        );

        // Empty alt is fine — still a standalone image block.
        $this->assertSame(
            '![](https://cdn.example.com/cover.jpg)',
            $expose->prependCoverMarkdown(['url' => 'https://cdn.example.com/cover.jpg', 'alt' => ''], ''),
        );
    }

    public function test_first_asset_unwraps_value_and_collections(): void
    {
        $expose = $this->expose();
        $asset = $this->makeAsset('jpg', 'https://cdn.example.com/cover.jpg', 'Alt', 'image/jpeg');

        // Value-wrapped single asset (the augmented shape).
        $this->assertSame($asset, $expose->firstAsset(new Value($asset, 'assets', null, null)));

        // Bare asset.
        $this->assertSame($asset, $expose->firstAsset($asset));

        // Value wrapping a collection → first asset.
        $this->assertSame($asset, $expose->firstAsset(new Value([$asset], 'assets', null, null)));

        // Nothing resolvable.
        $this->assertNull($expose->firstAsset(new Value(null, 'assets', null, null)));
        $this->assertNull($expose->firstAsset(new Value([], 'assets', null, null)));
        $this->assertNull($expose->firstAsset(null));
    }

    /**
     * Expose EntryMapper's private cover helpers (they don't touch the
     * ContentConverter dependency), mirroring SetContentResolverTest's seam.
     */
    private function expose(): object
    {
        $mapper = new EntryMapper($this->converter);

        return new class ($mapper) {
            public function __construct(private EntryMapper $m) {}

            public function prependCoverMarkdown(?array $cover, string $body): string
            {
                return (fn () => $this->prependCoverMarkdown($cover, $body))->call($this->m);
            }

            public function firstAsset(mixed $augmented): ?AssetContract
            {
                return (fn () => $this->firstAsset($augmented))->call($this->m);
            }
        };
    }

    /**
     * Minimal Asset-contract stub (only the methods EntryMapper touches, plus
     * no-op contract stubs). Mirrors SetContentResolverTest::makeAsset.
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
