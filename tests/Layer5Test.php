<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PublishPhp\StatamicStandardSite\SyncResult;

/**
 * Tests for Layer 5 listeners and EntryMapper changes.
 *
 * Listener classes require Statamic's Entry/Blueprint classes and the Addon facade,
 * which need a running Statamic instance. These tests cover what can be verified
 * without that bootstrap: SyncResult behavior, EntryMapper content convention,
 * and listener class structure.
 */
class Layer5Test extends TestCase
{
    public function test_sync_result_success_with_uri(): void
    {
        $result = SyncResult::success('at://did:plc:abc/site.standard.document/123', 'created');
        $this->assertTrue($result->success);
        $this->assertSame('at://did:plc:abc/site.standard.document/123', $result->uri);
        $this->assertSame('created', $result->action);
    }

    public function test_sync_result_success_updated(): void
    {
        $result = SyncResult::success('at://test/uri', 'updated');
        $this->assertSame('updated', $result->action);
    }

    public function test_sync_result_success_deleted(): void
    {
        $result = SyncResult::success('', 'deleted');
        $this->assertTrue($result->success);
        $this->assertSame('', $result->uri);
    }

    public function test_sync_result_success_noop(): void
    {
        $result = SyncResult::success('', 'noop');
        $this->assertSame('noop', $result->action);
    }

    public function test_sync_result_failure_with_error(): void
    {
        $result = SyncResult::failure('No publication record configured.');
        $this->assertFalse($result->success);
        $this->assertSame('No publication record configured.', $result->error);
        $this->assertNull($result->uri);
        $this->assertNull($result->action);
    }

    public function test_content_converter_markdown_passthrough(): void
    {
        $converter = new \PublishPhp\StatamicStandardSite\ContentConverter();
        $input = "# Title\n\n**bold**";
        $this->assertSame($input, $converter->toMarkdown($input));
    }

    public function test_content_converter_bard_to_markdown(): void
    {
        $converter = new \PublishPhp\StatamicStandardSite\ContentConverter();
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 2],
                    'content' => [['type' => 'text', 'text' => 'Section']],
                ],
            ],
        ];
        $result = $converter->toMarkdown($bard);
        $this->assertStringContainsString('## Section', $result);
    }

    public function test_content_converter_excludes_sets(): void
    {
        $converter = new \PublishPhp\StatamicStandardSite\ContentConverter(excludedSets: ['newsletter']);
        $bard = [
            ['type' => 'text', 'text' => '<p>Content</p>'],
            ['type' => 'set', 'attrs' => ['values' => ['type' => 'newsletter', 'heading' => 'Skip me']]],
        ];
        $result = $converter->toMarkdown($bard);
        $this->assertStringContainsString('Content', $result);
        $this->assertStringNotContainsString('Skip me', $result);
    }

    public function test_entry_mapper_uses_content_convention_not_override(): void
    {
        // EntryMapper should NOT have 'content' in OVERRIDES
        // We verify by reflection since the constant is private
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\EntryMapper::class);
        $overridesProp = $reflection->getConstant('OVERRIDES');

        $this->assertArrayNotHasKey('content', $overridesProp);
        $this->assertArrayHasKey('title', $overridesProp);
        $this->assertArrayHasKey('description', $overridesProp);
        $this->assertArrayHasKey('path', $overridesProp);
        $this->assertArrayHasKey('published_at', $overridesProp);
        $this->assertArrayHasKey('tags', $overridesProp);
    }

    public function test_listener_classes_exist(): void
    {
        $this->assertTrue(class_exists(\PublishPhp\StatamicStandardSite\Listeners\SyncOnEntrySaved::class));
        $this->assertTrue(class_exists(\PublishPhp\StatamicStandardSite\Listeners\DeleteOnEntryDeleted::class));
        $this->assertTrue(class_exists(\PublishPhp\StatamicStandardSite\Listeners\InjectBlueprintFields::class));
    }

    public function test_sync_on_entry_saved_has_handle_method(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Listeners\SyncOnEntrySaved::class);
        $this->assertTrue($reflection->hasMethod('handle'));
    }

    public function test_delete_on_entry_deleted_has_handle_method(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Listeners\DeleteOnEntryDeleted::class);
        $this->assertTrue($reflection->hasMethod('handle'));
    }

    public function test_inject_blueprint_fields_has_handle_method(): void
    {
        $reflection = new \ReflectionClass(\PublishPhp\StatamicStandardSite\Listeners\InjectBlueprintFields::class);
        $this->assertTrue($reflection->hasMethod('handle'));
    }
}
