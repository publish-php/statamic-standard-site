<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Renders the CollectionManager Vue component in the CP settings page.
 *
 * Shows a list of all collections with their Standard Site sync status
 * and a toggle button to enable/disable syncing per collection.
 */
class CollectionFieldtype extends Fieldtype
{
    protected static $handle = 'standard-site-collections';

    protected $component = 'standard-site-collections';

    public function preload(): array
    {
        // Generate the toggle URL with a real placeholder the Vue component can replace.
        // We can't pass '{handle}' to cp_route() — it expects actual parameter values.
        $toggleBase = cp_route('standard-site.collections.toggle', '__HANDLE__');

        return [
            'collections_url' => cp_route('standard-site.collections.index'),
            'toggle_url' => $toggleBase,
        ];
    }
}
