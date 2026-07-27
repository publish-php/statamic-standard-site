<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PublishPhp\StatamicStandardSite\ContentConverter;

class ContentConverterTest extends TestCase
{
    private ContentConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ContentConverter();
    }

    // ── Markdown passthrough ──

    public function test_markdown_passthrough_to_markdown(): void
    {
        $input = "# Hello World\n\nThis is **bold** text.";
        $this->assertSame($input, $this->converter->toMarkdown($input));
    }

    public function test_markdown_passthrough_to_textcontent(): void
    {
        $input = "# Hello World\n\nThis is **bold** text.";
        $result = $this->converter->toTextContent($input);
        $this->assertStringNotContainsString('**', $result);
        $this->assertStringNotContainsString('#', $result);
        $this->assertStringContainsString('Hello World', $result);
        $this->assertStringContainsString('bold', $result);
    }

    public function test_empty_string_returns_empty(): void
    {
        $this->assertSame('', $this->converter->toMarkdown(''));
        $this->assertSame('', $this->converter->toTextContent(''));
    }

    public function test_null_returns_empty(): void
    {
        $this->assertSame('', $this->converter->toMarkdown(null));
        $this->assertSame('', $this->converter->toTextContent(null));
    }

    // ── Bard without sets (ProseMirror doc) ──

    public function test_bard_heading_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 2],
                    'content' => [
                        ['type' => 'text', 'text' => 'My Heading'],
                    ],
                ],
            ],
        ];

        $this->assertSame('## My Heading', $this->converter->toMarkdown($bard));
    }

    public function test_bard_paragraph_with_bold_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Hello '],
                        ['type' => 'text', 'text' => 'world', 'marks' => [['type' => 'bold']]],
                        ['type' => 'text', 'text' => '!'],
                    ],
                ],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('Hello **world**!', $result);
    }

    public function test_bard_link_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Check out '],
                        [
                            'type' => 'text',
                            'text' => 'this link',
                            'marks' => [
                                ['type' => 'link', 'attrs' => ['href' => 'https://example.com', 'title' => '']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('[this link](https://example.com)', $result);
    }

    public function test_bard_link_with_title_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'link text',
                            'marks' => [
                                ['type' => 'link', 'attrs' => ['href' => 'https://example.com', 'title' => 'Example']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('[link text](https://example.com "Example")', $result);
    }

    public function test_bard_bullet_list_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'bulletList',
                    'content' => [
                        [
                            'type' => 'listItem',
                            'content' => [
                                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Item 1']]],
                            ],
                        ],
                        [
                            'type' => 'listItem',
                            'content' => [
                                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Item 2']]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('- Item 1', $result);
        $this->assertStringContainsString('- Item 2', $result);
    }

    public function test_bard_ordered_list_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'orderedList',
                    'content' => [
                        [
                            'type' => 'listItem',
                            'content' => [
                                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'First']]],
                            ],
                        ],
                        [
                            'type' => 'listItem',
                            'content' => [
                                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Second']]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('1. First', $result);
        $this->assertStringContainsString('2. Second', $result);
    }

    public function test_bard_code_block_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'codeBlock',
                    'attrs' => ['language' => 'php'],
                    'content' => [
                        ['type' => 'text', 'text' => "echo 'hello';"],
                    ],
                ],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('```php', $result);
        $this->assertStringContainsString("echo 'hello';", $result);
        $this->assertStringContainsString('```', $result);
    }

    public function test_bard_code_block_without_language_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'codeBlock',
                    'attrs' => ['language' => null],
                    'content' => [
                        ['type' => 'text', 'text' => 'plain code'],
                    ],
                ],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString("```\nplain code\n```", $result);
    }

    public function test_bard_blockquote_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'blockquote',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => 'A quote']],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('> A quote', $result);
    }

    public function test_bard_image_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'image',
                    'attrs' => [
                        'src' => 'https://example.com/img.jpg',
                        'alt' => 'An image',
                        'title' => '',
                    ],
                ],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('![An image](https://example.com/img.jpg)', $result);
    }

    public function test_bard_horizontal_rule_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                ['type' => 'horizontalRule'],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('---', $result);
    }

    public function test_bard_inline_code_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Use '],
                        ['type' => 'text', 'text' => 'var_dump', 'marks' => [['type' => 'code']]],
                        ['type' => 'text', 'text' => ' for debugging'],
                    ],
                ],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('Use `var_dump` for debugging', $result);
    }

    // ── Bard textContent extraction ──

    public function test_bard_to_textcontent_strips_formatting(): void
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
                        ['type' => 'text', 'text' => 'Bold ', 'marks' => [['type' => 'bold']]],
                        ['type' => 'text', 'text' => 'text'],
                    ],
                ],
            ],
        ];

        $result = $this->converter->toTextContent($bard);
        $this->assertStringNotContainsString('#', $result);
        $this->assertStringNotContainsString('**', $result);
        $this->assertStringContainsString('Title', $result);
        $this->assertStringContainsString('Bold text', $result);
    }

    public function test_bard_code_block_to_textcontent(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'codeBlock',
                    'attrs' => ['language' => 'php'],
                    'content' => [
                        ['type' => 'text', 'text' => "echo 'hello';"],
                    ],
                ],
            ],
        ];

        $result = $this->converter->toTextContent($bard);
        $this->assertStringContainsString("echo 'hello';", $result);
    }

    public function test_bard_image_alt_as_textcontent(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'image',
                    'attrs' => [
                        'src' => 'https://example.com/img.jpg',
                        'alt' => 'A photo of a cat',
                        'title' => '',
                    ],
                ],
            ],
        ];

        $result = $this->converter->toTextContent($bard);
        $this->assertStringContainsString('A photo of a cat', $result);
    }

    // ── Bard with sets ──

    public function test_bard_with_sets_text_item_to_markdown(): void
    {
        $bard = [
            [
                'type' => 'text',
                'text' => '<p>Hello <strong>world</strong>!</p>',
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('Hello **world**!', $result);
    }

    public function test_bard_with_sets_excluded_set_is_skipped(): void
    {
        $converter = new ContentConverter(excludedSets: ['newsletter_signup']);

        $bard = [
            [
                'type' => 'text',
                'text' => '<p>Before signup</p>',
            ],
            [
                'type' => 'set',
                'attrs' => [
                    'values' => [
                        'type' => 'newsletter_signup',
                        'heading' => 'Subscribe!',
                    ],
                ],
            ],
            [
                'type' => 'text',
                'text' => '<p>After signup</p>',
            ],
        ];

        $result = $converter->toMarkdown($bard);
        $this->assertStringContainsString('Before signup', $result);
        $this->assertStringContainsString('After signup', $result);
        $this->assertStringNotContainsString('Subscribe!', $result);
        $this->assertStringNotContainsString('newsletter_signup', $result);
    }

    public function test_bard_with_sets_included_set_is_flattened(): void
    {
        $bard = [
            [
                'type' => 'text',
                'text' => '<p>Intro text</p>',
            ],
            [
                'type' => 'set',
                'attrs' => [
                    'values' => [
                        'type' => 'slides',
                        'title' => 'Slide Title',
                        'content' => '<p>Slide body text</p>',
                    ],
                ],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('Intro text', $result);
        $this->assertStringContainsString('Slide Title', $result);
        $this->assertStringContainsString('Slide body text', $result);
    }

    // ── HTML input (Bard "save as HTML" mode) ──

    public function test_html_heading_to_markdown(): void
    {
        $html = '<h2>Section Title</h2>';
        $result = $this->converter->toMarkdown($html);
        $this->assertStringContainsString('## Section Title', $result);
    }

    public function test_html_paragraph_with_bold_to_markdown(): void
    {
        $html = '<p>This is <strong>bold</strong> text.</p>';
        $result = $this->converter->toMarkdown($html);
        $this->assertStringContainsString('This is **bold** text.', $result);
    }

    public function test_html_link_to_markdown(): void
    {
        $html = '<p>Visit <a href="https://example.com">our site</a> today.</p>';
        $result = $this->converter->toMarkdown($html);
        $this->assertStringContainsString('[our site](https://example.com)', $result);
    }

    public function test_html_to_textcontent(): void
    {
        $html = '<h1>Title</h1><p>Some <strong>formatted</strong> text.</p>';
        $result = $this->converter->toTextContent($html);
        $this->assertStringNotContainsString('<', $result);
        $this->assertStringContainsString('Title', $result);
        $this->assertStringContainsString('formatted', $result);
    }

    // ── Complex document ──

    public function test_complex_document_to_markdown(): void
    {
        $bard = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 1],
                    'content' => [['type' => 'text', 'text' => 'Post Title']],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Intro paragraph with '],
                        ['type' => 'text', 'text' => 'emphasis', 'marks' => [['type' => 'italic']]],
                        ['type' => 'text', 'text' => '.'],
                    ],
                ],
                [
                    'type' => 'bulletList',
                    'content' => [
                        [
                            'type' => 'listItem',
                            'content' => [
                                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'First point']]],
                            ],
                        ],
                        [
                            'type' => 'listItem',
                            'content' => [
                                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Second point']]],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'codeBlock',
                    'attrs' => ['language' => 'bash'],
                    'content' => [['type' => 'text', 'text' => 'echo "hello"']],
                ],
            ],
        ];

        $result = $this->converter->toMarkdown($bard);
        $this->assertStringContainsString('# Post Title', $result);
        $this->assertStringContainsString('*emphasis*', $result);
        $this->assertStringContainsString('- First point', $result);
        $this->assertStringContainsString('- Second point', $result);
        $this->assertStringContainsString('```bash', $result);
        $this->assertStringContainsString('echo "hello"', $result);
    }
}
