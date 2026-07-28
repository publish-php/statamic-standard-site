<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PublishPhp\StatamicStandardSite\SyncResult;
use PublishPhp\StatamicStandardSite\SyncErrorStore;
use PublishPhp\StatamicStandardSite\Tags\StandardSiteTags;
use Statamic\Fields\Value;
use Statamic\Tags\Context;

/**
 * Tests for Layer 6 components.
 *
 * Tag and controller classes need Statamic bootstrap — tested via class
 * existence and structure. SyncErrorStore uses Laravel's cache facade
 * which needs a bootstrap, so only structure is verified.
 */
class Layer6Test extends TestCase
{
    public function test_standard_site_tags_class_exists(): void
    {
        $this->assertTrue(class_exists(\PublishPhp\StatamicStandardSite\Tags\StandardSiteTags::class));
    }

    public function test_standard_site_tags_has_document_link_method(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Tags\StandardSiteTags::class);
        $this->assertTrue($reflection->hasMethod('documentLink'));
    }

    public function test_standard_site_tags_handle(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Tags\StandardSiteTags::class);
        $handleProp = $reflection->getStaticProperties()['handle'] ?? null;
        $this->assertSame('standard_site', $handleProp);
    }

    public function test_document_link_renders_link_from_context_uri(): void
    {
        // Statamic exposes the entry's fields at the top level of the template
        // context (no `entry` variable), so the tag reads the synced URI there.
        $tag = new StandardSiteTags();
        $tag->setContext(['standard_site_synced_uri' => 'at://did:plc:abc/site.standard.document/xyz']);

        $this->assertSame(
            '<link rel="site.standard.document" href="at://did:plc:abc/site.standard.document/xyz" />',
            $tag->documentLink(),
        );
    }

    public function test_document_link_unwraps_augmented_value(): void
    {
        // On the front end the field arrives augmented (a Value wrapper);
        // Context::value() must unwrap it.
        $tag = new StandardSiteTags();
        $tag->setContext([
            'standard_site_synced_uri' => new Value(
                'at://did:plc:abc/site.standard.document/xyz',
                'standard_site_synced_uri',
                null,
                null,
            ),
        ]);

        $this->assertStringContainsString(
            'href="at://did:plc:abc/site.standard.document/xyz"',
            (string) $tag->documentLink(),
        );
    }

    public function test_document_link_returns_null_when_unsynced(): void
    {
        // Homepage / listing / never-synced entry: no URI in context → nothing.
        $tag = new StandardSiteTags();

        $tag->setContext([]);
        $this->assertNull($tag->documentLink());

        $tag->setContext(['standard_site_synced_uri' => null]);
        $this->assertNull($tag->documentLink());
    }

    public function test_document_link_escapes_uri(): void
    {
        $tag = new StandardSiteTags();
        $tag->setContext(['standard_site_synced_uri' => 'at://x/"><script>']);

        $out = (string) $tag->documentLink();
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    public function test_status_controller_class_exists(): void
    {
        $this->assertTrue(class_exists(\PublishPhp\StatamicStandardSite\Http\Controllers\StatusController::class));
    }

    public function test_status_controller_has_errors_method(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Http\Controllers\StatusController::class);
        $this->assertTrue($reflection->hasMethod('errors'));
    }

    public function test_status_controller_has_documents_method(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Http\Controllers\StatusController::class);
        $this->assertTrue($reflection->hasMethod('documents'));
    }

    public function test_sync_error_store_class_exists(): void
    {
        $this->assertTrue(class_exists(\PublishPhp\StatamicStandardSite\SyncErrorStore::class));
    }

    public function test_sync_error_store_has_record_method(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\SyncErrorStore::class);
        $this->assertTrue($reflection->hasMethod('record'));
    }

    public function test_sync_error_store_has_flush_method(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\SyncErrorStore::class);
        $this->assertTrue($reflection->hasMethod('flush'));
    }

    public function test_sync_error_store_has_count_method(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\SyncErrorStore::class);
        $this->assertTrue($reflection->hasMethod('count'));
    }

    public function test_status_fieldtype_class_exists(): void
    {
        $this->assertTrue(class_exists(\PublishPhp\StatamicStandardSite\Fieldtypes\StatusFieldtype::class));
    }

    public function test_status_fieldtype_handle(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Fieldtypes\StatusFieldtype::class);
        $handleProp = $reflection->getStaticProperties()['handle'] ?? null;
        $this->assertSame('standard-site-status', $handleProp);
    }

    public function test_sync_on_entry_saved_has_send_failure_email_method(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Listeners\SyncOnEntrySaved::class);
        $this->assertTrue($reflection->hasMethod('sendFailureEmail'));
    }

    public function test_sync_result_factories(): void
    {
        $success = SyncResult::success('at://test', 'created');
        $this->assertTrue($success->success);
        $this->assertSame('created', $success->action);

        $failure = SyncResult::failure('error');
        $this->assertFalse($failure->success);
        $this->assertSame('error', $failure->error);
    }
}
