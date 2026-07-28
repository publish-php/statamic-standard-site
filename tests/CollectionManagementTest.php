<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class CollectionManagementTest extends TestCase
{
    public function test_collection_controller_class_exists(): void
    {
        $this->assertTrue(class_exists(\PublishPhp\StatamicStandardSite\Http\Controllers\CollectionController::class));
    }

    public function test_collection_controller_has_index_method(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Http\Controllers\CollectionController::class);
        $this->assertTrue($reflection->hasMethod('index'));
    }

    public function test_collection_controller_has_toggle_method(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Http\Controllers\CollectionController::class);
        $this->assertTrue($reflection->hasMethod('toggle'));
    }

    public function test_collection_fieldtype_class_exists(): void
    {
        $this->assertTrue(class_exists(\PublishPhp\StatamicStandardSite\Fieldtypes\CollectionFieldtype::class));
    }

    public function test_collection_fieldtype_handle(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Fieldtypes\CollectionFieldtype::class);
        $handleProp = $reflection->getStaticProperties()['handle'] ?? null;
        $this->assertSame('standard-site-collections', $handleProp);
    }

    public function test_collection_fieldtype_has_preload(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Fieldtypes\CollectionFieldtype::class);
        $this->assertTrue($reflection->hasMethod('preload'));
    }

    public function test_sync_on_entry_saved_checks_collection_enabled(): void
    {
        $reflection = new \ReflectionMethod(
            \PublishPhp\StatamicStandardSite\Listeners\SyncOnEntrySaved::class,
            'handle'
        );
        $source = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString('standard_site_enabled', $source);
        $this->assertStringContainsString('cascade()', $source);
    }

    public function test_delete_on_entry_deleted_checks_collection_enabled(): void
    {
        $reflection = new \ReflectionMethod(
            \PublishPhp\StatamicStandardSite\Listeners\DeleteOnEntryDeleted::class,
            'handle'
        );
        $source = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString('standard_site_enabled', $source);
        $this->assertStringContainsString('cascade()', $source);
    }
}
