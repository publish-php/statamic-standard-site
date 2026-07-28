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

    public function test_bard_flat_prosemirror_nodes_format(): void
    {
        // Statamic stores Bard-without-sets as a flat array of ProseMirror
        // block nodes, NOT wrapped in a {type: doc, content: [...]} envelope.
        // This is the actual format in .md files on disk.
        $bard = [
            [
                'type' => 'paragraph',
                'attrs' => ['textAlign' => null],
                'content' => [
                    ['type' => 'text', 'text' => 'First paragraph of content.'],
                ],
            ],
            [
                'type' => 'heading',
                'attrs' => ['textAlign' => null, 'level' => 2],
                'content' => [
                    ['type' => 'text', 'text' => 'A Section Heading'],
                ],
            ],
            [
                'type' => 'paragraph',
                'attrs' => ['textAlign' => null],
                'content' => [
                    ['type' => 'text', 'text' => 'Second paragraph.'],
                ],
            ],
        ];

        $converter = new ContentConverter();

        $markdown = $converter->toMarkdown($bard);
        $this->assertStringContainsString('First paragraph of content.', $markdown);
        $this->assertStringContainsString('## A Section Heading', $markdown);
        $this->assertStringContainsString('Second paragraph.', $markdown);

        $textContent = $converter->toTextContent($bard);
        $this->assertStringContainsString('First paragraph of content.', $textContent);
        $this->assertStringContainsString('A Section Heading', $textContent);
        $this->assertStringContainsString('Second paragraph.', $textContent);
        $this->assertStringNotContainsString('##', $textContent);
    }

    public function test_image_asset_reference_resolved_to_url(): void
    {
        $resolver = function (string $ref): ?string {
            // Match the production resolver: strip 'asset::' prefix before lookup
            if (str_starts_with($ref, 'asset::')) {
                $id = substr($ref, strlen('asset::'));
                [$container, $path] = explode('::', $id, 2);
                return 'https://example.com/assets/' . $path;
            }
            return null;
        };

        $converter = new ContentConverter(excludedSets: [], assetUrlResolver: $resolver);

        $bard = [
            [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'image',
                        'attrs' => [
                            'src' => 'asset::assets::posts/image.png',
                            'alt' => 'A photo',
                        ],
                    ],
                ],
            ],
        ];

        $markdown = $converter->toMarkdown($bard);
        $this->assertStringContainsString('![A photo](https://example.com/assets/posts/image.png)', $markdown);
        $this->assertStringNotContainsString('asset::', $markdown);
    }

    public function test_image_without_resolver_passes_src_through(): void
    {
        $converter = new ContentConverter();

        $bard = [
            [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'image',
                        'attrs' => [
                            'src' => 'https://example.com/image.png',
                            'alt' => 'Direct URL',
                        ],
                    ],
                ],
            ],
        ];

        $markdown = $converter->toMarkdown($bard);
        $this->assertStringContainsString('![Direct URL](https://example.com/image.png)', $markdown);
    }

    // ── Bard sets with resolved asset/media fields ──

    /**
     * Build a fake set resolver mimicking SetContentResolver's descriptor
     * output, so ContentConverter can be exercised without a Statamic runtime.
     * The fixtures model the real talk `video_slide` / `image_slide` shapes.
     *
     * @param array<string,list<array<string,mixed>>> $map setType => descriptors
     */
    private function fakeSetResolver(array $map): callable
    {
        return static function (string $setType, array $values, int $index) use ($map): array {
            return $map[$setType] ?? [];
        };
    }

    public function test_video_slide_renders_html_embed_with_poster(): void
    {
        $descriptors = [
            ['kind' => 'asset', 'handle' => 'video', 'media' => 'video', 'url' => 'https://cdn.example.com/talks/slide2.mp4', 'alt' => 'MySpace profiles float in', 'mime' => 'video/mp4'],
            ['kind' => 'asset', 'handle' => 'poster', 'media' => 'image', 'url' => 'https://cdn.example.com/talks/slide2_poster.jpg', 'alt' => null, 'mime' => 'image/jpeg'],
            ['kind' => 'bard', 'handle' => 'description', 'value' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'The video opens to a blank screen.']]],
            ]],
        ];

        $bard = [
            ['type' => 'set', 'attrs' => ['id' => 'abc', 'values' => ['type' => 'video_slide']]],
        ];

        $resolver = $this->fakeSetResolver(['video_slide' => $descriptors]);
        $markdown = $this->converter->toMarkdown($bard, $resolver);

        // Video → HTML embed with resolved absolute src
        $this->assertStringContainsString('<video controls', $markdown);
        $this->assertStringContainsString('poster="https://cdn.example.com/talks/slide2_poster.jpg"', $markdown);
        $this->assertStringContainsString('<source src="https://cdn.example.com/talks/slide2.mp4" type="video/mp4">', $markdown);
        $this->assertStringContainsString('</video>', $markdown);

        // Poster is consumed as the attribute — NOT a standalone image
        $this->assertStringNotContainsString('![](https://cdn.example.com/talks/slide2_poster.jpg)', $markdown);

        // Nested Bard description rendered via the clean node walker
        $this->assertStringContainsString('The video opens to a blank screen.', $markdown);

        // No raw stored paths leak through (the original bug)
        $this->assertStringNotContainsString('talks/creative-web', $markdown);
    }

    public function test_image_slide_renders_markdown_image(): void
    {
        $descriptors = [
            ['kind' => 'asset', 'handle' => 'image', 'media' => 'image', 'url' => 'https://cdn.example.com/talks/neocities.png', 'alt' => 'Neocities', 'mime' => 'image/png'],
            ['kind' => 'bard', 'handle' => 'description', 'value' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Geocities rises again.']]],
            ]],
        ];

        $bard = [
            ['type' => 'set', 'attrs' => ['id' => 'def', 'values' => ['type' => 'image_slide']]],
        ];

        $resolver = $this->fakeSetResolver(['image_slide' => $descriptors]);
        $markdown = $this->converter->toMarkdown($bard, $resolver);

        $this->assertStringContainsString('![Neocities](https://cdn.example.com/talks/neocities.png)', $markdown);
        $this->assertStringContainsString('Geocities rises again.', $markdown);
    }

    public function test_audio_asset_renders_html_embed(): void
    {
        $descriptors = [
            ['kind' => 'asset', 'handle' => 'track', 'media' => 'audio', 'url' => 'https://cdn.example.com/clip.mp3', 'alt' => 'A jingle', 'mime' => 'audio/mpeg'],
        ];

        $bard = [
            ['type' => 'set', 'attrs' => ['id' => 'ghi', 'values' => ['type' => 'audio_block']]],
        ];

        $resolver = $this->fakeSetResolver(['audio_block' => $descriptors]);
        $markdown = $this->converter->toMarkdown($bard, $resolver);

        $this->assertStringContainsString('<audio controls>', $markdown);
        $this->assertStringContainsString('<source src="https://cdn.example.com/clip.mp3" type="audio/mpeg">', $markdown);
        $this->assertStringContainsString('</audio>', $markdown);
    }

    public function test_poster_without_video_falls_back_to_image(): void
    {
        // A lone poster image with no video/audio to attach to should NOT vanish.
        $descriptors = [
            ['kind' => 'asset', 'handle' => 'poster', 'media' => 'image', 'url' => 'https://cdn.example.com/lonely_poster.jpg', 'alt' => 'Standalone', 'mime' => 'image/jpeg'],
        ];

        $bard = [
            ['type' => 'set', 'attrs' => ['id' => 'jkl', 'values' => ['type' => 'poster_only']]],
        ];

        $resolver = $this->fakeSetResolver(['poster_only' => $descriptors]);
        $markdown = $this->converter->toMarkdown($bard, $resolver);

        $this->assertStringContainsString('![Standalone](https://cdn.example.com/lonely_poster.jpg)', $markdown);
    }

    public function test_prose_and_sets_interleave_in_order(): void
    {
        $descriptors = [
            ['kind' => 'asset', 'handle' => 'image', 'media' => 'image', 'url' => 'https://cdn.example.com/mid.png', 'alt' => 'Middle', 'mime' => 'image/png'],
        ];

        $bard = [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Intro']]],
            ['type' => 'set', 'attrs' => ['id' => 'mno', 'values' => ['type' => 'image_slide']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'After the slide.']]],
        ];

        $resolver = $this->fakeSetResolver(['image_slide' => $descriptors]);
        $markdown = $this->converter->toMarkdown($bard, $resolver);

        // Order preserved: heading, image, trailing paragraph
        $posHeading = strpos($markdown, '## Intro');
        $posImage = strpos($markdown, '![Middle]');
        $posPara = strpos($markdown, 'After the slide.');
        $this->assertNotFalse($posHeading);
        $this->assertNotFalse($posImage);
        $this->assertNotFalse($posPara);
        $this->assertLessThan($posImage, $posHeading);
        $this->assertLessThan($posPara, $posImage);
    }

    public function test_excluded_set_skipped_even_with_resolver(): void
    {
        $converter = new ContentConverter(excludedSets: ['newsletter_signup']);

        $descriptors = [
            ['kind' => 'text', 'handle' => 'heading', 'value' => 'Subscribe!'],
        ];

        $bard = [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Before.']]],
            ['type' => 'set', 'attrs' => ['id' => 'pqr', 'values' => ['type' => 'newsletter_signup']]],
        ];

        $resolver = $this->fakeSetResolver(['newsletter_signup' => $descriptors]);
        $markdown = $converter->toMarkdown($bard, $resolver);

        $this->assertStringContainsString('Before.', $markdown);
        $this->assertStringNotContainsString('Subscribe!', $markdown);
    }

    public function test_set_video_alt_flows_to_textcontent(): void
    {
        $descriptors = [
            ['kind' => 'asset', 'handle' => 'video', 'media' => 'video', 'url' => 'https://cdn.example.com/v.mp4', 'alt' => 'A dancing nun', 'mime' => 'video/mp4'],
            ['kind' => 'bard', 'handle' => 'description', 'value' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Clip from Sister Act.']]],
            ]],
        ];

        $bard = [
            ['type' => 'set', 'attrs' => ['id' => 'stu', 'values' => ['type' => 'video_slide']]],
        ];

        $resolver = $this->fakeSetResolver(['video_slide' => $descriptors]);
        $text = $this->converter->toTextContent($bard, $resolver);

        $this->assertStringContainsString('A dancing nun', $text);
        $this->assertStringContainsString('Clip from Sister Act.', $text);
        $this->assertStringNotContainsString('<video', $text);
        $this->assertStringNotContainsString('http', $text);
    }

    public function test_set_without_resolver_omits_asset_paths(): void
    {
        // Regression guard: without a resolver, bare asset paths must NOT leak
        // into output (the original talk bug).
        $bard = [
            ['type' => 'set', 'attrs' => ['id' => 'vwx', 'values' => [
                'type' => 'video_slide',
                'video' => 'talks/creative-web/return_of_the_creative_web_2.mp4',
                'poster' => 'talks/creative-web/return_of_the_creative_web_2_poster.jpg',
                'description' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A blank screen.']]],
                ],
            ]]],
        ];

        $markdown = $this->converter->toMarkdown($bard);

        $this->assertStringNotContainsString('.mp4', $markdown);
        $this->assertStringNotContainsString('talks/creative-web', $markdown);
        // The nested description prose is still representable and kept.
        $this->assertStringContainsString('A blank screen.', $markdown);
    }
}
