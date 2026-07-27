<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PublishPhp\AtprotoStandardSite\Client;
use PublishPhp\AtprotoStandardSite\Exception\ApiErrorException;
use PublishPhp\AtprotoStandardSite\Exception\AuthenticationException;
use PublishPhp\AtprotoStandardSite\Model\Publication;
use PublishPhp\AtprotoStandardSite\Service\Record;

/**
 * Handles the publication management actions from the CP settings page.
 *
 * All actions are explicitly user-triggered — no hidden/automatic API calls.
 * Credentials are read from the addon's config (populated by the CP settings
 * page) so they never round-trip through the browser.
 */
class PublicationController extends Controller
{
    /**
     * Check for existing site.standard.publication records on the DID.
     *
     * Queries com.atproto.repo.listRecords for publication records.
     * Returns the list so the user can choose which to use.
     */
    public function check(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['nullable', 'string'],
            'app_password' => ['nullable', 'string'],
            'pds_host' => ['nullable', 'string'],
        ]);

        try {
            $client = $this->createClient(
                    $validated['identifier'] ?? config('statamic.standard-site.identifier'),
                    $validated['app_password'] ?? config('statamic.standard-site.app_password'),
                    $validated['pds_host'] ?? config('statamic.standard-site.pds_host') ?? Client::DEFAULT_PDS,
                );

            $did = $client->getDid();
            $records = new Record($client);

            $response = $records->list(
                Publication::COLLECTION,
                limit: 50,
                repo: $did,
            );

            $publications = array_map(function (array $rec): array {
                $value = $rec['value'] ?? [];
                return [
                    'uri' => $rec['uri'] ?? '',
                    'cid' => $rec['cid'] ?? '',
                    'name' => $value['name'] ?? '(unnamed)',
                    'url' => $value['url'] ?? '',
                    'description' => $value['description'] ?? '',
                ];
            }, $response['records'] ?? []);

            return response()->json([
                'success' => true,
                'did' => $did,
                'publications' => $publications,
            ]);
        } catch (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication failed: ' . $e->getMessage(),
            ], 401);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'error' => 'API error: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Create a new publication record.
     *
     * Uses the provided name and URL to create a site.standard.publication
     * record on the user's DID. Returns the new AT-URI.
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['nullable', 'string'],
            'app_password' => ['nullable', 'string'],
            'pds_host' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:500'],
            'url' => ['required', 'string', 'url'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            $client = $this->createClient(
                    $validated['identifier'] ?? config('statamic.standard-site.identifier'),
                    $validated['app_password'] ?? config('statamic.standard-site.app_password'),
                    $validated['pds_host'] ?? config('statamic.standard-site.pds_host') ?? Client::DEFAULT_PDS,
                );

            $records = new Record($client);
            $uri = $records->createPublication(new Publication(
                url: $validated['url'],
                name: $validated['name'],
                description: $validated['description'] ?? null,
            ));

            return response()->json([
                'success' => true,
                'uri' => $uri,
            ]);
        } catch (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication failed: ' . $e->getMessage(),
            ], 401);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'error' => 'API error: ' . $e->getMessage(),
            ], 502);
        }
    }

    private function createClient(?string $identifier, ?string $appPassword, ?string $pdsHost): Client
    {
        return new Client($identifier, $appPassword, pdsHost: $pdsHost ?? Client::DEFAULT_PDS);
    }
}
