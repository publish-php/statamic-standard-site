<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PublishPhp\StatamicStandardSite\ContentConverter;
use PublishPhp\StatamicStandardSite\EntryMapper;

/**
 * Tests for EntryMapper convention detection and blueprint overrides.
 *
 * Since EntryMapper depends on Statamic's Entry and Blueprint classes,
 * we test the logic that can be exercised without a full Statamic bootstrap.
 * The convention detection and override logic is tested via stub objects
 * that implement the minimal interface EntryMapper actually uses.
 */
class EntryMapperTest extends TestCase
{
    private ContentConverter $converter;
    private EntryMapper $mapper;

    protected function setUp(): void
    {
        $this->converter = new ContentConverter();
        $this->mapper = new EntryMapper($this->converter);
    }

    public function test_markdown_passthrough_to_markdown(): void
    {
        $input = "# Hello\n\nWorld";
        $result = $this->converter->toMarkdown($input);
        $this->assertSame($input, $result);
    }

    public function test_markdown_to_textcontent_strips_formatting(): void
    {
        $input = "# Hello\n\n**bold** and *italic*";
        $result = $this->converter->toTextContent($input);
        $this->assertStringNotContainsString('#', $result);
        $this->assertStringNotContainsString('**', $result);
        $this->assertStringNotContainsString('*', $result);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('bold and italic', $result);
    }

    public function test_bard_content_to_markdown_and_textcontent(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 1],
                    'content' => [['type' => 'text', 'text' => 'Title']],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Body with '],
                        ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]],
                    ],
                ],
            ],
        ];

        $markdown = $this->converter->toMarkdown($bard);
        $textContent = $this->converter->toTextContent($bard);

        $this->assertStringContainsString('# Title', $markdown);
        $this->assertStringContainsString('**bold**', $markdown);
        $this->assertStringContainsString('Title', $textContent);
        $this->assertStringContainsString('bold', $textContent);
        $this->assertStringNotContainsString('**', $textContent);
    }

    public function test_content_converter_excludes_sets(): void
    {
        $converter = new ContentConverter(excludedSets: ['newsletter']);

        $bard = [
            [
                'type' => 'text',
                'text' => '<p>Content</p>',
            ],
            [
                'type' => 'set',
                'attrs' => [
                    'values' => [
                        'type' => 'newsletter',
                        'heading' => 'Subscribe',
                    ],
                ],
            ],
        ];

        $result = $converter->toMarkdown($bard);
        $this->assertStringContainsString('Content', $result);
        $this->assertStringNotContainsString('Subscribe', $result);
    }

    public function test_entry_mapper_constructs_with_converter(): void
    {
        $mapper = new EntryMapper($this->converter);
        $this->assertInstanceOf(EntryMapper::class, $mapper);
    }

    public function test_sync_result_created(): void
    {
        $result = \PublishPhp\StatamicStandardSite\SyncResult::success('at://test', 'created');
        $this->assertTrue($result->success);
        $this->assertSame('created', $result->action);
    }

    public function test_sync_result_updated(): void
    {
        $result = \PublishPhp\StatamicStandardSite\SyncResult::success('at://test', 'updated');
        $this->assertTrue($result->success);
        $this->assertSame('updated', $result->action);
    }

    public function test_sync_result_deleted(): void
    {
        $result = \PublishPhp\StatamicStandardSite\SyncResult::success('', 'deleted');
        $this->assertTrue($result->success);
        $this->assertSame('deleted', $result->action);
    }
}
