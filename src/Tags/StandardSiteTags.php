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
        // The synced AT-URI is stored on the entry (persisted to its front
        // matter) by SyncOnEntrySaved. On the front end Statamic spreads the
        // entry's fields across the TOP LEVEL of the template context — there is
        // no `entry` variable in the cascade — so read the value straight from
        // context. Context::value() unwraps any augmented Value wrapper. This
        // resolves in entry/show templates, the layout <head> (the entry
        // cascade reaches the layout), and inside collection loops.
        $uri = $this->context->value('standard_site_synced_uri');
        if (! $uri) {
            return null;
        }

        return '<link rel="site.standard.document" href="' . e((string) $uri) . '" />';
    }
}
