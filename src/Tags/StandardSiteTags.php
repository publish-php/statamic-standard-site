<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite\Tags;

use Statamic\Tags\Tags;

/**
 * Standard Site template tags.
 *
 * Usage in Antlers:
 *   {{ standard_site:document_link }}
 *
 * Usage in Blade:
 *   <s:standard_site:document_link />
 *
 * Outputs a <link rel="site.standard.document"> tag for the current entry,
 * using the sync state stored on the entry by the SyncOnEntrySaved listener.
 *
 * Per the user's design philosophy: explicit template placement, not auto-injection.
 *
 * @see https://standard.site/docs/verification/
 */
class StandardSiteTags extends Tags
{
    protected static $handle = 'standard_site';

    /**
     * Output the document verification link tag.
     *
     * {{ standard_site:document_link }}
     */
    public function documentLink(): ?string
    {
        // Get the entry from the template context
        $entry = $this->context['entry'] ?? null;
        if (! $entry) {
            return null;
        }

        // Get the synced AT-URI stored by SyncOnEntrySaved
        $uri = $entry->get('standard_site_synced_uri');
        if (! $uri) {
            return null;
        }

        return '<link rel="site.standard.document" href="' . e($uri) . '" />';
    }
}
