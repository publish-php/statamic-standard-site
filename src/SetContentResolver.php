<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite;

use Statamic\Contracts\Assets\Asset;
use Statamic\Assets\Asset as AssetClass;
use Statamic\Fields\Fieldtype;
use Statamic\Fields\Value;

/**
 * Resolves the fields of a Bard set through Statamic's own augmentation
 * pipeline and flattens them into framework-agnostic "field descriptors"
 * that {@see ContentConverter} can render without any Statamic runtime.
 *
 * This is the convention-aware bridge: it drives everything off the Bard
 * field's configured set blueprint (via the field's real fieldtypes), so it
 * works for ANY user's set schema — nothing is hardwired to a particular
 * blueprint. An `assets` field augments to real Asset objects, from which we
 * read a fully-qualified `absoluteUrl()` that is correct on any driver
 * (local, S3, Scaleway/CDN, etc.). Nested Bard fields are passed through as
 * RAW ProseMirror so the converter can render them on its high-fidelity
 * node walker rather than a lossy HTML round-trip.
 *
 * Descriptor shapes (each is an ordered list item):
 *
 *   asset: [
 *     'kind'   => 'asset',
 *     'handle' => string,                       // set field handle
 *     'media'  => 'image'|'video'|'audio'|'file',
 *     'url'    => string,                        // absolute URL
 *     'alt'    => ?string,                       // asset alt data (if any)
 *     'mime'   => ?string,                       // e.g. 'video/mp4'
 *   ]
 *   bard:  ['kind' => 'bard', 'handle' => string, 'value' => array]  // raw ProseMirror
 *   text:  ['kind' => 'text', 'handle' => string, 'value' => string] // markdown/plain/html
 *
 * @see \Statamic\Fieldtypes\Bard\Augmentor
 */
class SetContentResolver
{
    /**
     * The Bard fieldtype instance for the content field being converted.
     *
     * Obtained via `$blueprint->field('content')->fieldtype()`. Carries the
     * configured set blueprints, so `fields($setType, $index)` yields the
     * real field objects for a given set.
     */
    public function __construct(
        private readonly Fieldtype $bardFieldtype,
    ) {}

    /**
     * Resolve one set's values into an ordered list of field descriptors.
     *
     * @param string $setType The set's type handle (e.g. 'video_slide').
     * @param array<string,mixed> $values The raw set values (as stored on disk).
     * @param int $index The set's positional index (used for Statamic field
     *   path hashing / localization; any stable int is acceptable).
     * @return list<array<string,mixed>> Ordered field descriptors.
     */
    public function resolve(string $setType, array $values, int $index): array
    {
        $fieldsConfig = $this->bardFieldtype->flattenedSetsConfig();
        if (! isset($fieldsConfig[$setType]['fields'])) {
            // Unknown set type (no blueprint) — nothing we can resolve.
            return [];
        }

        // Build the set's Fields object and augment it. Augmentation resolves
        // asset fields to Asset objects, term fields to Terms, etc. We keep the
        // pre-augmentation Fields to read each field's TYPE, and read augmented
        // values for asset URL resolution.
        $fields = $this->bardFieldtype->fields($setType, $index)->addValues($values);
        $augmentedValues = $fields->augment()->values();

        $descriptors = [];

        foreach ($fields->all() as $handle => $field) {
            $type = $field->type();
            $rawValue = $values[$handle] ?? null;

            if ($rawValue === null || $rawValue === '' || $rawValue === []) {
                continue;
            }

            // Nested Bard → hand back RAW ProseMirror for high-fidelity
            // rendering on the converter's node walker (not lossy HTML).
            if ($type === 'bard') {
                $descriptors[] = [
                    'kind' => 'bard',
                    'handle' => (string) $handle,
                    'value' => $rawValue,
                ];
                continue;
            }

            // Assets → resolve through the augmented Asset object(s).
            if ($type === 'assets') {
                $augmented = $augmentedValues[$handle] ?? null;
                foreach ($this->assetsFromAugmented($augmented) as $asset) {
                    $descriptor = $this->describeAsset($asset, (string) $handle);
                    if ($descriptor !== null) {
                        $descriptors[] = $descriptor;
                    }
                }
                continue;
            }

            // Everything else (text, textarea, markdown, etc.) → string.
            if (is_string($rawValue)) {
                $descriptors[] = [
                    'kind' => 'text',
                    'handle' => (string) $handle,
                    'value' => $rawValue,
                ];
            }
        }

        return $descriptors;
    }

    /**
     * Normalize an augmented assets value into a list of Asset objects.
     *
     * Statamic's `Fields::augment()` wraps each field in a lazy
     * {@see \Statamic\Fields\Value}; calling `->value()` triggers the fieldtype's
     * augmentation. For an `assets` field that yields a single Asset
     * (`max_files: 1`) or a query/collection of Assets (multi-file).
     *
     * @return list<Asset>
     */
    private function assetsFromAugmented(mixed $augmented): array
    {
        if ($augmented === null) {
            return [];
        }

        // Unwrap the lazy Value wrapper produced by Fields::augment().
        if ($augmented instanceof Value) {
            $augmented = $augmented->value();
        }

        if ($augmented === null) {
            return [];
        }

        if ($augmented instanceof Asset) {
            return [$augmented];
        }

        // Query builder or collection — resolve to a plain list.
        if (is_object($augmented) && method_exists($augmented, 'get')) {
            $augmented = $augmented->get();
        }

        if (is_iterable($augmented)) {
            $assets = [];
            foreach ($augmented as $item) {
                if ($item instanceof Value) {
                    $item = $item->value();
                }
                if ($item instanceof Asset) {
                    $assets[] = $item;
                }
            }
            return $assets;
        }

        return [];
    }

    /**
     * Build an asset descriptor from a resolved Asset object.
     *
     * @return array<string,mixed>|null Null if the asset has no resolvable URL.
     */
    private function describeAsset(Asset $asset, string $handle): ?array
    {
        $url = $asset->absoluteUrl();
        if (! $url) {
            return null;
        }

        return [
            'kind' => 'asset',
            'handle' => $handle,
            'media' => $this->mediaKind($asset),
            'url' => $url,
            'alt' => $asset->get('alt') ?: null,
            'mime' => $asset->mimeType() ?: null,
        ];
    }

    /**
     * Classify an asset's media kind for rendering dispatch.
     *
     * Keys off the asset's `extension()` (guaranteed on the Asset contract) and
     * classifies against Statamic's own canonical extension constants
     * (`Asset::IMAGE/VIDEO/AUDIO_EXTENSIONS`). This stays perfectly aligned with
     * Statamic's `isImage()/isVideo()/isAudio()` (which use the same constants
     * under the hood) while remaining unit-testable against a minimal Asset
     * stub — the contract guarantees `extension()`, whereas `isVideo()` /
     * `isAudio()` live only on the concrete class.
     */
    private function mediaKind(Asset $asset): string
    {
        $extension = strtolower((string) $asset->extension());

        if (in_array($extension, AssetClass::IMAGE_EXTENSIONS, true)) {
            return 'image';
        }
        if (in_array($extension, AssetClass::VIDEO_EXTENSIONS, true)) {
            return 'video';
        }
        if (in_array($extension, AssetClass::AUDIO_EXTENSIONS, true)) {
            return 'audio';
        }
        return 'file';
    }
}
