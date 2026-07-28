<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite;

/**
 * Manages persistent sync error state for CP display.
 *
 * Stores sync errors in Laravel's cache so they survive across requests.
 * The settings page reads and clears them to show a badge indicator.
 *
 * Since the AT Protocol is public and sync failures are rare (credentials
 * issues, network problems), we keep this lightweight — just a count and
 * the most recent error message.
 */
class SyncErrorStore
{
    private const CACHE_KEY = 'standard-site-sync-errors';
    private const CACHE_TTL = 604800; // 7 days

    public function record(string $entryId, string $error): void
    {
        $errors = $this->getAll();
        $errors[] = [
            'entry_id' => $entryId,
            'error' => $error,
            'timestamp' => now()->toIso8601String(),
        ];
        cache([self::CACHE_KEY => $errors], self::CACHE_TTL);
    }

    /**
     * Get all recorded errors (without clearing).
     *
     * @return list<array{entry_id: string, error: string, timestamp: string}>
     */
    public function getAll(): array
    {
        return cache(self::CACHE_KEY, []);
    }

    /**
     * Get the count of unread errors.
     */
    public function count(): int
    {
        return count($this->getAll());
    }

    /**
     * Clear all errors (called when the settings page is viewed).
     */
    public function clear(): void
    {
        cache([self::CACHE_KEY => []], self::CACHE_TTL);
    }

    /**
     * Get and clear all errors in one call.
     *
     * @return list<array{entry_id: string, error: string, timestamp: string}>
     */
    public function flush(): array
    {
        $errors = $this->getAll();
        $this->clear();
        return $errors;
    }
}
