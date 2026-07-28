<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Renders the StatusDashboard Vue component in the CP settings page.
 *
 * This fieldtype is a display-only component — it doesn't store a value.
 * It shows sync errors (badge) and a lazy-load button for PDS documents.
 */
class StatusFieldtype extends Fieldtype
{
    protected static $handle = 'standard-site-status';

    protected $component = 'standard-site-status';

    public function preload(): array
    {
        return [];
    }
}
