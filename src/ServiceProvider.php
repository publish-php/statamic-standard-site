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
        \PublishPhp\StatamicStandardSite\Fieldtypes\StatusFieldtype::class,
        \PublishPhp\StatamicStandardSite\Fieldtypes\CollectionFieldtype::class,
    ];

    protected $tags = [
        \PublishPhp\StatamicStandardSite\Tags\StandardSiteTags::class,
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
                'collections' => [
                    'sections' => [
                        [
                            'display' => 'Collection Sync',
                            'instructions' => 'Enable or disable Standard Site syncing for each collection. Only enabled collections will have their published entries synced to the AT Protocol.',
                            'fields' => [
                                [
                                    'handle' => 'collections_manager',
                                    'field' => [
                                        'type' => 'standard-site-collections',
                                        'display' => 'Collections',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'notifications' => [
                    'sections' => [
                        [
                            'display' => 'Failure Notifications',
                            'fields' => [
                                [
                                    'handle' => 'notify_on_failure',
                                    'field' => [
                                        'type' => 'toggle',
                                        'display' => 'Send email on sync failure',
                                        'instructions' => 'When enabled, an email is sent on the first sync failure (throttled — not sent again until the settings page is viewed).',
                                        'default' => false,
                                        'width' => 50,
                                    ],
                                ],
                                [
                                    'handle' => 'notification_email',
                                    'field' => [
                                        'type' => 'text',
                                        'input_type' => 'email',
                                        'display' => 'Notification Email Address',
                                        'instructions' => 'Email address for sync failure notifications. Leave empty to use the site\'s default mail address.',
                                        'width' => 50,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'status' => [
                    'sections' => [
                        [
                            'display' => 'Sync Status',
                            'instructions' => 'View sync errors and recently synced documents. Click Refresh to load documents from the PDS.',
                            'fields' => [
                                [
                                    'handle' => 'status_dashboard',
                                    'field' => [
                                        'type' => 'standard-site-status',
                                        'display' => 'Status Dashboard',
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
        $this->app->singleton(ContentConverter::class);
        $this->app->singleton(EntryMapper::class);
        $this->app->singleton(SyncManager::class);
        $this->app->singleton(SyncErrorStore::class);
    }
}
