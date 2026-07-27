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
use Statamic\Facades\Statamic;

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
                $validated,
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
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
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
                $validated,
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
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function createClient(array $validated): Client
    {
        $identifier = $validated['identifier'] ?? Statamic::get('standard-site.identifier');
        $appPassword = $validated['app_password'] ?? Statamic::get('standard-site.app_password');
        $pdsHost = $validated['pds_host'] ?? Statamic::get('standard-site.pds_host', Client::DEFAULT_PDS);

        if (! $identifier || ! $appPassword) {
            throw new \RuntimeException(
                'Standard Site credentials not configured. Set them in Settings → Standard Site.'
            );
        }

        return new Client($identifier, $appPassword, pdsHost: $pdsHost);
    }
}
