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

    protected $fieldtypes = [
        \PublishPhp\StatamicStandardSite\Fieldtypes\PublicationManagerFieldtype::class,
    ];

    protected $vite = [
        'input' => [
            'resources/js/addon.js',
            'resources/css/addon.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function bootAddon(): void
    {
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
                                        'type' => 'publication-manager',
                                        'display' => 'Publication Record',
                                        'instructions' => 'Select an existing publication or create a new one.',
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
        $this->app->singleton(EntryMapper::class);
        $this->app->singleton(SyncManager::class);
    }
}
