<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite\Listeners;

use Illuminate\Support\Facades\Log;
use PublishPhp\StatamicStandardSite\SyncManager;
use Statamic\Events\EntryDeleted;
use Statamic\Facades\Addon;

class DeleteOnEntryDeleted
{
    public function __construct(
        private readonly SyncManager $syncManager,
    ) {}

    public function handle(EntryDeleted $event): void
    {
        $settings = Addon::get('publish-php/statamic-standard-site')->settings();
        $deleteOnDelete = $settings->get('delete_on_entry_delete', true);

        if (! $deleteOnDelete) {
            return;
        }

        // Only delete records for entries in collections that had opted in
        $collection = $event->entry->collection();
        if (! $collection || ! $collection->cascade()->get('standard_site_enabled', false)) {
            return;
        }

        $result = $this->syncManager->delete($event->entry);

        if ($result->success) {
            Log::info("Standard Site: deleted record for entry {$event->entry->id()} ({$result->action})");
        } else {
            Log::error("Standard Site: delete failed for entry {$event->entry->id()}: {$result->error}");
        }
    }
}
