<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite;

use PublishPhp\AtprotoStandardSite\Client;
use PublishPhp\AtprotoStandardSite\Exception\AuthenticationException;
use Statamic\Facades\Addon;

/**
 * Centralized factory for the Layer 1 AT Protocol Client.
 *
 * Reads credentials from Statamic settings and constructs an authenticated
 * Client instance. The rest of the add-on uses this to avoid scattering
 * credential access throughout the codebase.
 */
class ClientManager
{
    /**
     * Create an authenticated Client from saved Statamic settings.
     *
     * @throws AuthenticationException  If authentication with the PDS fails
     * @throws \RuntimeException  If credentials are not configured
     */
    public function client(): Client
    {
        $settings = Addon::get('publish-php/statamic-standard-site')->settings();

        $identifier = $settings->get('identifier');
        $appPassword = $settings->get('app_password');
        $pdsHost = $settings->get('pds_host', Client::DEFAULT_PDS);

        if (! $identifier || ! $appPassword) {
            throw new \RuntimeException(
                'Standard Site credentials not configured. Set them in Settings → Standard Site.'
            );
        }

        return new Client($identifier, $appPassword, pdsHost: $pdsHost);
    }

    /**
     * Get the selected publication AT-URI.
     *
     * @throws \RuntimeException  If no publication is selected
     */
    public function publicationUri(): string
    {
        $uri = Addon::get('publish-php/statamic-standard-site')->settings()->get('publication_uri');

        if (! $uri) {
            throw new \RuntimeException(
                'No publication record selected. Configure one in Settings → Standard Site.'
            );
        }

        return $uri;
    }
}
