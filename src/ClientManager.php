<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite;

use PublishPhp\AtprotoStandardSite\Client;
use PublishPhp\AtprotoStandardSite\Exception\AuthenticationException;
use Statamic\Facades\Statamic;

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
        $identifier = Statamic::get('standard-site.identifier');
        $appPassword = Statamic::get('standard-site.app_password');
        $pdsHost = Statamic::get('standard-site.pds_host', Client::DEFAULT_PDS);

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
        $uri = Statamic::get('standard-site.publication_uri');

        if (! $uri) {
            throw new \RuntimeException(
                'No publication record selected. Configure one in Settings → Standard Site.'
            );
        }

        return $uri;
    }
}
