<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PublishPhp\StatamicStandardSite\SyncErrorStore;
use PublishPhp\StatamicStandardSite\SyncManager;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Addon;
use Statamic\Facades\CP\Toast;

class SyncOnEntrySaved
{
    public function __construct(
        private readonly SyncManager $syncManager,
        private readonly SyncErrorStore $errorStore,
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

        // Only sync entries in collections that have opted in
        $collection = $entry->collection();
        if (! $collection || ! $collection->cascade()->get('standard_site_enabled', false)) {
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
            $error = $result->error ?? 'Unknown error';
            Log::error("Standard Site: sync failed for entry {$entry->id()}: {$error}");

            // Persistent error store for the settings page badge
            $this->errorStore->record($entry->id(), $error);

            // Ephemeral toast notification for CP saves
            try {
                Toast::error("Standard Site sync failed: {$error}");
            } catch (\Throwable) {
                // Toast may not be available outside CP context (e.g. console)
            }

            // Email notification (throttled — first failure only)
            $this->sendFailureEmail($entry, $error);
        }
    }

    private function sendFailureEmail($entry, string $error): void
    {
        $settings = Addon::get('publish-php/statamic-standard-site')->settings();

        if (! $settings->get('notify_on_failure', false)) {
            return;
        }

        // Throttle: only send on the first failure (count == 1 after record())
        // Subsequent failures are skipped until the settings page clears the store
        if ($this->errorStore->count() > 1) {
            return;
        }

        $email = $settings->get('notification_email') ?: config('mail.from.address');
        if (! $email) {
            return;
        }

        try {
            Mail::raw(
                "Standard Site sync failed for entry {$entry->id()} ({$entry->get('title', 'untitled')}): {$error}",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Standard Site sync failure');
                }
            );
        } catch (\Throwable $e) {
            Log::error("Standard Site: failed to send notification email: {$e->getMessage()}");
        }
    }
}
