<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AT Protocol Credentials
    |--------------------------------------------------------------------------
    | Configured via the CP settings page. These values are read at runtime
    | when constructing the Layer 1 Client.
    */

    'identifier' => null,
    'app_password' => null,
    'pds_host' => 'https://bsky.social',

    /*
    |--------------------------------------------------------------------------
    | Publication Record
    |--------------------------------------------------------------------------
    | The AT-URI of the selected publication record. Populated by the
    | publication management actions in the CP settings page.
    */

    'publication_uri' => null,

    /*
    |--------------------------------------------------------------------------
    | Record Key Configuration
    |--------------------------------------------------------------------------
    | Prefix for derived record keys. Default: statamic.
    | Record keys look like {namespace}-{entryId}.
    */

    'rkey_namespace' => 'statamic',

    /*
    |--------------------------------------------------------------------------
    | Sync Behavior
    |--------------------------------------------------------------------------
    */

    'delete_on_entry_delete' => true,

    /*
    |--------------------------------------------------------------------------
    | Bard Set Exclusions
    |--------------------------------------------------------------------------
    | Array of Bard set type handles to skip during content conversion.
    | Populated by blueprint configuration in later layers.
    */

    'skip_bard_sets' => [],

];
