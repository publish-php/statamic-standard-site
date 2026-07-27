<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__ . '/../routes/cp.php',
        'web' => __DIR__ . '/../routes/web.php',
    ];

    protected $scripts = [
        __DIR__ . '/../resources/js/publication-manager.js',
    ];

    public function bootAddon(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/standard-site.php',
            'statamic.standard-site'
        );

        $this->registerSettingsBlueprint([
            'tabs' => [
                'credentials' => [
                    'sections' => [
                        [
                            'display' => 'AT Protocol Credentials',
                            'fields' => [
                                [
                                    'handle' => 'identifier',
                                    'field' => [
                                        'type' => 'text',
                                        'display' => 'Handle or Email',
                                        'instructions' => 'Your Bluesky handle (e.g. mydomain.com) or email address.',
                                        'validate' => 'required',
                                    ],
                                ],
                                [
                                    'handle' => 'app_password',
                                    'field' => [
                                        'type' => 'text',
                                        'input_type' => 'password',
                                        'display' => 'App Password',
                                        'instructions' => 'Generate one at Settings → App Passwords in Bluesky. Not your account password.',
                                        'validate' => 'required',
                                        'width' => 50,
                                    ],
                                ],
                                [
                                    'handle' => 'pds_host',
                                    'field' => [
                                        'type' => 'text',
                                        'display' => 'PDS Host',
                                        'instructions' => 'Personal Data Server URL. Defaults to https://bsky.social for Bluesky-hosted accounts.',
                                        'default' => 'https://bsky.social',
                                        'width' => 50,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'publication' => [
                    'sections' => [
                        [
                            'display' => 'Publication Record',
                            'instructions' => 'Manage your standard.site publication record. Use the actions below to check for an existing record or create a new one.',
                            'fields' => [
                                [
                                    'handle' => 'publication_uri',
                                    'field' => [
                                        'type' => 'textarea',
                                        'display' => 'Publication AT-URI',
                                        'instructions' => 'The AT-URI of your selected publication record. Populated by the actions below.',
                                        'read_only' => true,
                                        'rows' => 2,
                                    ],
                                ],
                                [
                                    'handle' => 'publication_name',
                                    'field' => [
                                        'type' => 'text',
                                        'display' => 'Publication Name',
                                        'instructions' => 'Display name for your publication. Used when creating a new record.',
                                        'width' => 50,
                                    ],
                                ],
                                [
                                    'handle' => 'publication_description',
                                    'field' => [
                                        'type' => 'textarea',
                                        'display' => 'Publication Description',
                                        'instructions' => 'Brief description. Used when creating a new record.',
                                        'width' => 50,
                                        'rows' => 2,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'sync' => [
                    'sections' => [
                        [
                            'display' => 'Sync Configuration',
                            'fields' => [
                                [
                                    'handle' => 'rkey_namespace',
                                    'field' => [
                                        'type' => 'text',
                                        'display' => 'Record Key Namespace',
                                        'instructions' => 'Prefix for derived record keys. Default: statamic. Record keys look like {namespace}-{entryId}.',
                                        'default' => 'statamic',
                                        'width' => 50,
                                        'validate' => 'required|alpha_dash',
                                    ],
                                ],
                                [
                                    'handle' => 'delete_on_entry_delete',
                                    'field' => [
                                        'type' => 'toggle',
                                        'display' => 'Delete records on entry deletion',
                                        'instructions' => 'When an entry is deleted, also delete the corresponding AT Protocol record.',
                                        'default' => true,
                                        'width' => 50,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function register(): void
    {
        $this->app->singleton(ClientManager::class);
    }
}
