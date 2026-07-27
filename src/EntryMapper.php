<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite;

use Statamic\Entries\Entry;
use Statamic\Fields\Blueprint;

/**
 * Maps a Statamic entry to site.standard.document fields.
 *
 * Uses convention auto-detection with blueprint override support (SEO Pro pattern).
 * Convention: checks common field handles (title, content, description, etc.).
 * Override: if a `standard_site_*` field exists on the blueprint, it takes precedence.
 *
 * @see https://standard.site/docs/lexicons/document/
 */
class EntryMapper
{
    /**
     * Convention handles for each document field.
     * Checked in order — first match wins.
     */
    private const CONVENTION = [
        'title' => ['title'],
        'content' => ['content'],
        'description' => ['description', 'summary', 'excerpt', 'meta_description'],
        'published_at' => ['date', 'published_at'],
        'tags' => ['tags'],
    ];

    /**
     * Blueprint override field handles (checked first if present).
     */
    private const OVERRIDES = [
        'title' => 'standard_site_title',
        'content' => 'standard_site_content',
        'description' => 'standard_site_description',
        'published_at' => 'standard_site_published_at',
        'path' => 'standard_site_path',
        'tags' => 'standard_site_tags',
    ];

    public function __construct(
        private readonly ContentConverter $converter,
    ) {}

    /**
     * Map an entry to document fields.
     *
     * Returns an array with keys matching the Document constructor parameters
     * (minus `site`, which the SyncManager provides from the publication AT-URI).
     *
     * @param Entry $entry
     * @return array{title: string, content: ?array, textContent: ?string, path: ?string, description: ?string, publishedAt: string, tags: ?array, updatedAt: ?string}
     */
    public function map(Entry $entry): array
    {
        $blueprint = $entry->blueprint();

        return [
            'title' => $this->resolveTitle($entry, $blueprint),
            'content' => $this->resolveContent($entry, $blueprint),
            'textContent' => $this->resolveTextContent($entry, $blueprint),
            'path' => $this->resolvePath($entry, $blueprint),
            'description' => $this->resolveDescription($entry, $blueprint),
            'publishedAt' => $this->resolvePublishedAt($entry, $blueprint),
            'tags' => $this->resolveTags($entry, $blueprint),
            'updatedAt' => $this->resolveUpdatedAt($entry),
        ];
    }

    private function resolveTitle(Entry $entry, Blueprint $blueprint): string
    {
        $override = self::OVERRIDES['title'];
        if ($blueprint->hasField($override) && $entry->get($override)) {
            return (string) $entry->get($override);
        }

        return (string) $entry->get('title');
    }

    private function resolveContent(Entry $entry, Blueprint $blueprint): ?array
    {
        $override = self::OVERRIDES['content'];
        $contentHandle = null;

        if ($blueprint->hasField($override) && $entry->get($override)) {
            $contentHandle = $override;
        } else {
            // Convention: field with handle 'content'
            if ($blueprint->hasField('content')) {
                $contentHandle = 'content';
            }
        }

        if ($contentHandle === null) {
            return null;
        }

        $value = $entry->get($contentHandle);
        $markdown = $this->converter->toMarkdown($value);

        if ($markdown === '') {
            return null;
        }

        // Determine flavor based on field type
        $field = $blueprint->field($contentHandle);
        $fieldtype = $field?->type() ?? 'markdown';
        $flavor = $fieldtype === 'bard' ? 'commonmark' : 'gfm';

        return [
            '$type' => 'at.markpub.markdown',
            'flavor' => $flavor,
            'text' => [
                '$type' => 'at.markpub.text',
                'markdown' => $markdown,
            ],
        ];
    }

    private function resolveTextContent(Entry $entry, Blueprint $blueprint): ?string
    {
        $override = self::OVERRIDES['content'];
        $contentHandle = null;

        if ($blueprint->hasField($override) && $entry->get($override)) {
            $contentHandle = $override;
        } else {
            if ($blueprint->hasField('content')) {
                $contentHandle = 'content';
            }
        }

        if ($contentHandle === null) {
            return null;
        }

        $value = $entry->get($contentHandle);
        $text = $this->converter->toTextContent($value);

        return $text !== '' ? $text : null;
    }

    private function resolvePath(Entry $entry, Blueprint $blueprint): ?string
    {
        $override = self::OVERRIDES['path'];
        if ($blueprint->hasField($override) && $entry->get($override)) {
            return (string) $entry->get($override);
        }

        // Convention: derive from entry URL
        $url = $entry->url();
        if ($url === null) {
            return null;
        }

        // Strip the domain, keep the path
        $path = parse_url($url, PHP_URL_PATH);
        return $path ?: null;
    }

    private function resolveDescription(Entry $entry, Blueprint $blueprint): ?string
    {
        $override = self::OVERRIDES['description'];
        if ($blueprint->hasField($override) && $entry->get($override)) {
            $value = (string) $entry->get($override);
            return $value !== '' ? $value : null;
        }

        // Convention: check common handles
        foreach (self::CONVENTION['description'] as $handle) {
            if ($blueprint->hasField($handle) && $entry->get($handle)) {
                return (string) $entry->get($handle);
            }
        }

        return null;
    }

    private function resolvePublishedAt(Entry $entry, Blueprint $blueprint): string
    {
        $override = self::OVERRIDES['published_at'];
        if ($blueprint->hasField($override) && $entry->get($override)) {
            return $this->toIso8601($entry->get($override));
        }

        // Convention: entry date
        if ($entry->date()) {
            return $entry->date()->toIso8601String();
        }

        // Convention: check published_at field
        foreach (self::CONVENTION['published_at'] as $handle) {
            if ($blueprint->hasField($handle) && $entry->get($handle)) {
                return $this->toIso8601($entry->get($handle));
            }
        }

        // Fallback: now
        return now()->toIso8601String();
    }

    private function resolveTags(Entry $entry, Blueprint $blueprint): ?array
    {
        $override = self::OVERRIDES['tags'];
        if ($blueprint->hasField($override) && $entry->get($override)) {
            $tags = $entry->get($override);
            return $this->normalizeTags($tags);
        }

        // Convention: check tags field
        foreach (self::CONVENTION['tags'] as $handle) {
            if ($blueprint->hasField($handle) && $entry->get($handle)) {
                $tags = $entry->get($handle);
                return $this->normalizeTags($tags);
            }
        }

        return null;
    }

    /**
     * Resolve the entry's last-modified timestamp as ISO 8601 for updatedAt.
     *
     * Uses $entry->lastModified() which is confirmed in the Statamic 6 Entry API.
     * Returns null if the entry has no last-modified date.
     */
    private function resolveUpdatedAt(Entry $entry): ?string
    {
        $lastModified = $entry->lastModified();
        if ($lastModified === null) {
            return null;
        }

        if ($lastModified instanceof \Carbon\Carbon) {
            return $lastModified->toIso8601String();
        }

        return $this->toIso8601($lastModified);
    }

    /**
     * Normalize tags to an array of strings.
     *
     * @param mixed $tags
     * @return array<string>|null
     */
    private function normalizeTags(mixed $tags): ?array
    {
        if ($tags === null) {
            return null;
        }

        if (is_string($tags)) {
            return [$tags];
        }

        if (is_array($tags)) {
            $normalized = array_map(fn ($t) => is_string($t) ? $t : (string) $t, $tags);
            return array_filter($normalized, fn ($t) => $t !== '');
        }

        return null;
    }

    /**
     * Convert a date value to ISO 8601 string.
     *
     * @param mixed $date
     */
    private function toIso8601(mixed $date): string
    {
        if ($date instanceof \Carbon\Carbon) {
            return $date->toIso8601String();
        }

        if (is_string($date)) {
            try {
                return \Carbon\Carbon::parse($date)->toIso8601String();
            } catch (\Throwable) {
                return now()->toIso8601String();
            }
        }

        return now()->toIso8601String();
    }
}
