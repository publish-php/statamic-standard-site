<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite\Listeners;

use Illuminate\Support\Facades\Log;
use PublishPhp\StatamicStandardSite\SyncManager;
use Statamic\Events\EntrySaved;

class SyncOnEntrySaved
{
    public function __construct(
        private readonly SyncManager $syncManager,
    ) {}

    public function handle(EntrySaved $event): void
    {
        // Skip non-initial localizations to avoid duplicate syncs
        if (! $event->isInitial()) {
            return;
        }

        $entry = $event->entry;

        // Only sync published entries — the AT Protocol is currently 100% public
        if (! $entry->published()) {
            return;
        }

        $result = $this->syncManager->sync($entry);

        if ($result->success) {
            // Store sync state on the entry (without re-triggering events)
            $entry->set('standard_site_synced_uri', $result->uri);
            $entry->set('standard_site_synced_at', now()->toIso8601String());
            $entry->saveQuietly();

            Log::info("Standard Site: synced entry {$entry->id()} ({$result->action}) → {$result->uri}");
        } else {
            Log::error("Standard Site: sync failed for entry {$entry->id()}: {$result->error}");
        }
    }
}
