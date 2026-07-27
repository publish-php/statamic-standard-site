<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite\Fieldtypes;

use Statamic\Fields\Fieldtype;

class PublicationManagerFieldtype extends Fieldtype
{
    protected static $handle = 'publication-manager';

    public function preload(): array
    {
        return [];
    }
}
