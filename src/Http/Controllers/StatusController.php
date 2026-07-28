<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use PublishPhp\AtprotoStandardSite\Exception\ApiErrorException;
use PublishPhp\AtprotoStandardSite\Exception\AuthenticationException;
use PublishPhp\AtprotoStandardSite\Model\Document;
use PublishPhp\AtprotoStandardSite\Service\Record;
use PublishPhp\StatamicStandardSite\ClientManager;
use PublishPhp\StatamicStandardSite\SyncErrorStore;
use Statamic\Facades\Addon;

/**
 * Provides status endpoints for the CP settings page.
 *
 * - GET /status/errors: Returns sync errors and clears them (for the badge)
 * - GET /status/documents: Lists recently synced documents from the PDS (lazy-load)
 */
class StatusController extends Controller
{
    public function __construct(
        private readonly SyncErrorStore $errorStore,
        private readonly ClientManager $clientManager,
    ) {}

    /**
     * Get and clear sync errors (for the settings page badge).
     */
    public function errors(): JsonResponse
    {
        $errors = $this->errorStore->flush();

        return response()->json([
            'count' => count($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * List recently synced documents from the PDS (lazy-load via AJAX).
     */
    public function documents(): JsonResponse
    {
        $settings = Addon::get('publish-php/statamic-standard-site')->settings();
        $publicationUri = $settings->get('publication_uri');

        if (! $publicationUri) {
            return response()->json([
                'success' => false,
                'error' => 'No publication record configured.',
                'documents' => [],
            ]);
        }

        try {
            $client = $this->clientManager->client();
            $records = new Record($client);
            $response = $records->list(Document::COLLECTION, limit: 50);

            $documents = array_map(function (array $rec): array {
                $value = $rec['value'] ?? [];
                return [
                    'uri' => $rec['uri'] ?? '',
                    'title' => $value['title'] ?? '(untitled)',
                    'path' => $value['path'] ?? '',
                    'publishedAt' => $value['publishedAt'] ?? '',
                    'updatedAt' => $value['updatedAt'] ?? '',
                ];
            }, $response['records'] ?? []);

            return response()->json([
                'success' => true,
                'documents' => $documents,
                'count' => count($documents),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'documents' => [],
            ]);
        } catch (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication failed: ' . $e->getMessage(),
                'documents' => [],
            ]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'error' => 'API error: ' . $e->getMessage(),
                'documents' => [],
            ]);
        }
    }
}
