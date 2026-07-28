<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite;

use PublishPhp\AtprotoStandardSite\Exception\ApiErrorException;
use PublishPhp\AtprotoStandardSite\Exception\AuthenticationException;
use PublishPhp\AtprotoStandardSite\Model\Document;
use PublishPhp\AtprotoStandardSite\Service\Record;
use Statamic\Entries\Entry;
use Statamic\Facades\Addon;

/**
 * Orchestrates syncing Statamic entries to site.standard.document records on the PDS.
 *
 * Uses deterministic rkeys ({namespace}-{entryId}) so the same entry always maps
 * to the same record. On sync, checks if a record exists first (getRecord), then
 * creates or updates accordingly — surfaces errors for unexpected scenarios.
 *
 * On delete, removes the corresponding PDS record.
 */
class SyncManager
{
    public function __construct(
        private readonly ClientManager $clientManager,
        private readonly EntryMapper $mapper,
    ) {}

    /**
     * Sync an entry to the PDS.
     *
     * Checks if a document record exists for this entry's rkey.
     * If not, creates it. If yes, updates it (putRecord).
     *
     * @param Entry $entry
     * @return SyncResult Result of the sync operation
     */
    public function sync(Entry $entry): SyncResult
    {
        // Validate configuration
        $settings = Addon::get('publish-php/statamic-standard-site')->settings();
        $publicationUri = $settings->get('publication_uri');

        if (! $publicationUri) {
            return SyncResult::failure(
                'No publication record configured. Set one in Settings → Standard Site.'
            );
        }

        // Get credentials + create client
        try {
            $client = $this->clientManager->client();
        } catch (\RuntimeException $e) {
            return SyncResult::failure($e->getMessage());
        } catch (AuthenticationException $e) {
            return SyncResult::failure('Authentication failed: ' . $e->getMessage());
        }

        $rkey = $this->deriveRkey($entry);
        $records = new Record($client);

        // Map entry to document fields
        $mapped = $this->mapper->map($entry);

        $document = new Document(
            site: $publicationUri,
            title: $mapped['title'],
            publishedAt: $mapped['publishedAt'],
            path: $mapped['path'],
            description: $mapped['description'],
            content: $mapped['content'],
            textContent: $mapped['textContent'],
            tags: $mapped['tags'],
            updatedAt: $mapped['updatedAt'],
        );

        // Check if record exists (get-then-create/update pattern)
        try {
            $existing = $records->get($rkey, Document::COLLECTION);
            $exists = true;
        } catch (ApiErrorException $e) {
            // If the record doesn't exist, we'll get an error.
            // Not all API errors mean "not found" — surface unexpected ones.
            if ($this->isNotFoundError($e)) {
                $exists = false;
            } else {
                return SyncResult::failure('Error checking existing record: ' . $e->getMessage());
            }
        }

        try {
            if ($exists) {
                // Update existing record
                $uri = $records->put($rkey, Document::COLLECTION, $document->toArray());
                return SyncResult::success($uri, 'updated');
            } else {
                // Create new record with deterministic rkey
                $uri = $this->createWithRkey($records, $rkey, $document);
                return SyncResult::success($uri, 'created');
            }
        } catch (ApiErrorException $e) {
            return SyncResult::failure('API error during sync: ' . $e->getMessage());
        } catch (AuthenticationException $e) {
            return SyncResult::failure('Authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete an entry's document record from the PDS.
     *
     * @param Entry $entry
     * @return SyncResult
     */
    public function delete(Entry $entry): SyncResult
    {
        try {
            $client = $this->clientManager->client();
        } catch (\RuntimeException $e) {
            return SyncResult::failure($e->getMessage());
        } catch (AuthenticationException $e) {
            return SyncResult::failure('Authentication failed: ' . $e->getMessage());
        }

        $rkey = $this->deriveRkey($entry);
        $records = new Record($client);

        try {
            $records->delete($rkey, Document::COLLECTION);
            return SyncResult::success('', 'deleted');
        } catch (ApiErrorException $e) {
            if ($this->isNotFoundError($e)) {
                // Record doesn't exist — nothing to delete
                return SyncResult::success('', 'noop');
            }
            return SyncResult::failure('API error during delete: ' . $e->getMessage());
        }
    }

    /**
     * Derive the deterministic record key for an entry.
     *
     * Format: {namespace}-{entryId}
     * Default namespace: "statamic" (configurable in CP settings)
     *
     * @throws \InvalidArgumentException If the namespace contains invalid rkey characters
     */
    public function deriveRkey(Entry $entry): string
    {
        $settings = Addon::get('publish-php/statamic-standard-site')->settings();
        $namespace = $settings->get('rkey_namespace', 'statamic');

        // Validate namespace against AT Protocol rkey syntax
        // Allowed characters: A-Za-z0-9 . - _ : ~
        // The '-' separator and entryId (which contains characters outside TID's base32 [a-z2-7])
        // ensures the resulting rkey cannot collide with TID-generated rkeys.
        if (!preg_match('/^[A-Za-z0-9.\-_:~]+$/', $namespace)) {
            throw new \InvalidArgumentException(
                "Invalid rkey namespace '{$namespace}'. Allowed characters: letters, digits, period, dash, underscore, colon, tilde."
            );
        }

        return $namespace . '-' . $entry->id();
    }

    /**
     * Create a record with a specific rkey.
     *
     * The Layer 1 createRecord endpoint auto-generates rkeys, so we use
     * putRecord instead to set our deterministic rkey.
     */
    private function createWithRkey(Record $records, string $rkey, Document $document): string
    {
        return $records->put($rkey, Document::COLLECTION, $document->toArray());
    }

    /**
     * Check if an API error indicates the record was not found.
     */
    private function isNotFoundError(ApiErrorException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'not found')
            || str_contains($message, 'could not find')
            || str_contains($message, 'could not locate')
            || str_contains($message, 'does not exist')
            || $e->getCode() === 404;
    }
}
