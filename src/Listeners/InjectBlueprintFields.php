<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite\Listeners;

use Statamic\Events\EntryBlueprintFound;

/**
 * Injects a "Standard Site" tab into all entry blueprints with optional
 * override fields (SEO Pro pattern). Override fields contain actual values,
 * not field handle references. Empty = use convention detection.
 *
 * Content is intentionally NOT overridable per-entry — it's determined by
 * convention (the field with handle 'content').
 */
class InjectBlueprintFields
{
    public function handle(EntryBlueprintFound $event): void
    {
        $event->blueprint->ensureFieldsInTab([
            'standard_site_title' => [
                'type' => 'text',
                'display' => 'Document Title',
                'instructions' => 'Override the title used for the standard.site document. Leave empty to use the entry title.',
                'listable' => false,
            ],
            'standard_site_description' => [
                'type' => 'textarea',
                'display' => 'Document Description',
                'instructions' => 'Override the description for the standard.site document. Leave empty to use the description/summary/excerpt field.',
                'listable' => false,
            ],
            'standard_site_path' => [
                'type' => 'text',
                'display' => 'Document Path',
                'instructions' => 'Override the URL path for the document. Leave empty to derive from the entry URL.',
                'listable' => false,
            ],
            'standard_site_published_at' => [
                'type' => 'text',
                'display' => 'Published At',
                'instructions' => 'Override the publish date (ISO 8601). Leave empty to use the entry date.',
                'listable' => false,
            ],
            'standard_site_tags' => [
                'type' => 'text',
                'display' => 'Document Tags',
                'instructions' => 'Override tags for the document. Comma-separated. Leave empty to use the tags field.',
                'listable' => false,
            ],
        ], 'standard_site');
    }
}
