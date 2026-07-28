<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite;

/**
 * Converts Statamic content fields to formats suitable for site.standard.document records.
 *
 * Handles two source types:
 * - Bard (ProseMirror JSON) → Markdown + plaintext
 * - Markdown fieldtype → passthrough + plaintext extraction
 *
 * For Bard with sets, flattens set content to Markdown and respects
 * per-blueprint exclusion lists for set types to skip.
 *
 * @see https://standard.site/docs/lexicons/document/
 * @see https://markpub.at/
 */
class ContentConverter
{
    /**
     * Set types to exclude from conversion (e.g. 'newsletter_signup', 'poll').
     *
     * @param list<string> $excludedSets
     * @param callable|null $assetUrlResolver Resolves asset references to public URLs.
     *   Signature: fn(string $assetRef): string|null
     *   Example ref: 'asset::assets::posts/image.png'
     *   If null, asset references are passed through as-is.
     */
    /**
     * Per-call resolver for Bard set fields.
     *
     * Set by {@see toMarkdown()} / {@see toTextContent()} for the duration of a
     * single conversion. Signature:
     *   fn(string $setType, array $values, int $index): list<array>  // descriptors
     * See {@see SetContentResolver} for the descriptor contract. Null means no
     * resolver (unit tests, or content without asset-bearing sets) — the
     * converter falls back to generic text flattening and never emits a raw
     * asset path.
     *
     * @var callable|null
     */
    private $setResolver = null;

    public function __construct(
        private readonly array $excludedSets = [],
        private readonly mixed $assetUrlResolver = null,
    ) {}

    /**
     * Convert a content field value to Markdown.
     *
     * Accepts Bard JSON (with or without sets), HTML strings, or plain Markdown.
     *
     * @param mixed $value The raw field value from Statamic
     * @param callable|null $setResolver Resolves a Bard set's fields to
     *   descriptors (see {@see SetContentResolver}). Entry-specific, so it is
     *   passed per-call rather than injected in the constructor.
     * @return string Markdown text
     */
    public function toMarkdown(mixed $value, ?callable $setResolver = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $this->setResolver = $setResolver;

        // If it's already a string, it could be HTML (Bard "save as HTML" mode)
        // or Markdown (Markdown fieldtype passthrough).
        if (is_string($value)) {
            if ($this->looksLikeHtml($value)) {
                return $this->htmlToMarkdown($value);
            }
            return $value; // Assume Markdown passthrough
        }

        // If it's an array, it's Bard ProseMirror JSON
        if (is_array($value)) {
            return $this->bardToMarkdown($value);
        }

        return '';
    }

    /**
     * Convert a content field value to plaintext (for textContent).
     *
     * Strips all formatting — no Markdown, no HTML tags.
     *
     * @param mixed $value The raw field value from Statamic
     * @param callable|null $setResolver Resolves a Bard set's fields to
     *   descriptors (see {@see SetContentResolver}).
     * @return string Plaintext
     */
    public function toTextContent(mixed $value, ?callable $setResolver = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $this->setResolver = $setResolver;

        if (is_string($value)) {
            if ($this->looksLikeHtml($value)) {
                return strip_tags($this->decodeHtmlEntities($value));
            }
            // Markdown passthrough — strip Markdown syntax
            return $this->stripMarkdown($value);
        }

        if (is_array($value)) {
            return $this->bardToTextContent($value);
        }

        return '';
    }

    /**
     * Convert Bard JSON to Markdown.
     *
     * Handles both the "with sets" format (array of {type, ...} objects)
     * and the "without sets" format (ProseMirror doc with {type: "doc", content: [...]}).
     *
     * @param array<mixed> $bard
     * @return string
     */
    private function bardToMarkdown(array $bard): string
    {
        // Check if this is a ProseMirror document (has type: "doc" with content)
        if (($bard['type'] ?? null) === 'doc' && isset($bard['content'])) {
            return $this->renderNodes($bard['content']);
        }

        // Check if this is a flat array of ProseMirror block nodes
        // (Statamic's on-disk Bard format — array of paragraph, heading, set, etc.).
        // Sets appear as `type: set` items interspersed with regular block nodes,
        // so `set` is a valid top-level type here.
        if (isset($bard[0]) && is_array($bard[0]) && isset($bard[0]['type'])) {
            $firstType = $bard[0]['type'];
            $isProseMirrorNodes = in_array($firstType, [
                'paragraph', 'heading', 'bulletList', 'orderedList',
                'blockquote', 'codeBlock', 'horizontalRule', 'table',
                'hardBreak', 'image', 'set',
            ], true);

            if ($isProseMirrorNodes) {
                return $this->renderNodes($bard);
            }
        }

        // "With sets" format — array of top-level items where text items
        // have type "text" with a "text" key containing HTML, or type "set"
        $markdown = '';
        foreach ($bard as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? '';

            if ($type === 'text') {
                // Text content — "text" key contains HTML
                $html = $item['text'] ?? '';
                $markdown .= $this->htmlToMarkdown($html);
            } elseif ($type === 'set') {
                // Set content — has "attrs" with "values" containing the set's fields.
                // Route through the node renderer so asset/media resolution and the
                // poster convention apply uniformly (exclusion is handled there too).
                $markdown .= $this->renderSetNode($item);
            }
        }

        return trim($markdown);
    }

    /**
     * Convert Bard JSON to plaintext.
     *
     * @param array<mixed> $bard
     * @return string
     */
    private function bardToTextContent(array $bard): string
    {
        if (($bard['type'] ?? null) === 'doc' && isset($bard['content'])) {
            return $this->renderNodesTextContent($bard['content']);
        }

        // Flat array of ProseMirror block nodes (Statamic's on-disk format,
        // with sets interspersed as `type: set` items).
        if (isset($bard[0]) && is_array($bard[0]) && isset($bard[0]['type'])) {
            $firstType = $bard[0]['type'];
            $isProseMirrorNodes = in_array($firstType, [
                'paragraph', 'heading', 'bulletList', 'orderedList',
                'blockquote', 'codeBlock', 'horizontalRule', 'table',
                'hardBreak', 'image', 'set',
            ], true);

            if ($isProseMirrorNodes) {
                return $this->renderNodesTextContent($bard);
            }
        }

        // Legacy "text + HTML" fallback format.
        $text = '';
        foreach ($bard as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? '';

            if ($type === 'text') {
                $html = $item['text'] ?? '';
                $text .= strip_tags($this->decodeHtmlEntities($html));
            } elseif ($type === 'set') {
                $text .= $this->renderSetNodeTextContent($item);
            }
        }

        return trim($text);
    }

    /**
     * Render an array of ProseMirror nodes to Markdown.
     *
     * @param array<mixed> $nodes
     * @return string
     */
    private function renderNodes(array $nodes): string
    {
        $markdown = '';
        foreach ($nodes as $node) {
            $markdown .= $this->renderNode($node);
        }
        return trim($markdown);
    }

    /**
     * Render a single ProseMirror node to Markdown.
     *
     * @param array<mixed> $node
     * @return string
     */
    private function renderNode(array $node): string
    {
        $type = $node['type'] ?? '';
        $content = $node['content'] ?? [];
        $text = $node['text'] ?? '';
        $marks = $node['marks'] ?? [];
        $attrs = $node['attrs'] ?? [];

        return match ($type) {
            'paragraph' => $this->renderInlineContent($content) . "\n\n",
            'heading' => str_repeat('#', (int) ($attrs['level'] ?? 1)) . ' ' . $this->renderInlineContent($content) . "\n\n",
            'bulletList', 'bullet_list' => $this->renderList($content, false),
            'orderedList', 'ordered_list' => $this->renderList($content, true),
            'listItem', 'list_item' => $this->renderInlineContent($content),
            'codeBlock', 'code_block' => $this->renderCodeBlock($node),
            'blockquote' => $this->renderBlockquote($content),
            'image' => $this->renderImage($attrs),
            'horizontalRule', 'horizontal_rule' => "---\n\n",
            'table' => $this->renderTable($content),
            'hardBreak', 'hard_break' => "  \n",
            'text' => $this->renderTextWithMarks($text, $marks),
            'set' => $this->renderSetNode($node),
            default => $this->renderInlineContent($content),
        };
    }

    /**
     * Render inline content (text nodes with marks) to Markdown.
     *
     * @param array<mixed> $content
     * @return string
     */
    private function renderInlineContent(array $content): string
    {
        $text = '';
        foreach ($content as $node) {
            $text .= $this->renderNode($node);
        }
        return $text;
    }

    /**
     * Render a text node with its marks applied.
     *
     * @param string $text
     * @param array<mixed> $marks
     * @return string
     */
    private function renderTextWithMarks(string $text, array $marks): string
    {
        if (empty($marks)) {
            return $text;
        }

        $prefix = '';
        $suffix = '';

        foreach ($marks as $mark) {
            $type = $mark['type'] ?? '';
            $attrs = $mark['attrs'] ?? [];

            switch ($type) {
                case 'bold':
                case 'strong':
                    $prefix .= '**';
                    $suffix = '**' . $suffix;
                    break;
                case 'italic':
                case 'em':
                    $prefix .= '*';
                    $suffix = '*' . $suffix;
                    break;
                case 'code':
                    $prefix .= '`';
                    $suffix = '`' . $suffix;
                    break;
                case 'link':
                    $href = $attrs['href'] ?? '';
                    $title = $attrs['title'] ?? '';
                    if ($title) {
                        $prefix .= '[';
                        $suffix = "]({$href} \"{$title}\")" . $suffix;
                    } else {
                        $prefix .= '[';
                        $suffix = "]({$href})" . $suffix;
                    }
                    break;
                case 'strike':
                case 'strikethrough':
                    $prefix .= '~~';
                    $suffix = '~~' . $suffix;
                    break;
            }
        }

        return $prefix . $text . $suffix;
    }

    /**
     * Render a list (ordered or unordered) to Markdown.
     *
     * @param array<mixed> $content
     * @param bool $ordered
     * @return string
     */
    private function renderList(array $content, bool $ordered): string
    {
        $markdown = '';
        $i = 1;
        foreach ($content as $node) {
            $marker = $ordered ? "{$i}. " : '- ';
            $itemText = $this->renderInlineContent($node['content'] ?? []);
            $markdown .= $marker . $itemText . "\n";
            $i++;
        }
        $markdown .= "\n";
        return $markdown;
    }

    /**
     * Render a code block to Markdown.
     *
     * @param array<mixed> $node
     * @return string
     */
    private function renderCodeBlock(array $node): string
    {
        $language = $node['attrs']['language'] ?? '';
        $content = $node['content'] ?? [];

        $text = '';
        foreach ($content as $child) {
            if (($child['type'] ?? '') === 'text') {
                $text .= $child['text'] ?? '';
            }
        }

        if ($language) {
            return "```{$language}\n{$text}\n```\n\n";
        }
        return "```\n{$text}\n```\n\n";
    }

    /**
     * Render a blockquote to Markdown.
     *
     * @param array<mixed> $content
     * @return string
     */
    private function renderBlockquote(array $content): string
    {
        $inner = $this->renderNodes($content);
        $lines = explode("\n", $inner);
        $quoted = '';
        foreach ($lines as $line) {
            $quoted .= '> ' . $line . "\n";
        }
        return $quoted . "\n";
    }

    /**
     * Render an image node to Markdown.
     *
     * @param array<mixed> $attrs
     * @return string
     */
    private function renderImage(array $attrs): string
    {
        $src = $attrs['src'] ?? '';
        $alt = $attrs['alt'] ?? '';
        $title = $attrs['title'] ?? '';

        // Resolve Statamic asset references (e.g. 'asset::assets::posts/image.png')
        // to public URLs using the injected resolver.
        if ($src !== '' && $this->assetUrlResolver !== null) {
            $resolved = ($this->assetUrlResolver)($src);
            if ($resolved !== null) {
                $src = $resolved;
            }
        }

        if ($src === '') {
            return '';
        }

        if ($title) {
            return "![{$alt}]({$src} \"{$title}\")\n\n";
        }
        return "![{$alt}]({$src})\n\n";
    }

    /**
     * Render a table to Markdown (basic pipe table).
     *
     * @param array<mixed> $content
     * @return string
     */
    private function renderTable(array $content): string
    {
        $rows = [];
        foreach ($content as $rowNode) {
            if (($rowNode['type'] ?? '') !== 'tableRow' && ($rowNode['type'] ?? '') !== 'table_row') {
                continue;
            }
            $cells = [];
            foreach ($rowNode['content'] ?? [] as $cellNode) {
                $cellText = $this->renderInlineContent($cellNode['content'] ?? []);
                $cells[] = trim($cellText);
            }
            $rows[] = $cells;
        }

        if (empty($rows)) {
            return '';
        }

        $markdown = '';
        $numCols = count($rows[0]);

        // Header row
        $markdown .= '| ' . implode(' | ', $rows[0]) . " |\n";
        $markdown .= '|' . implode('|', array_fill(0, $numCols, ' --- ')) . "|\n";

        // Data rows
        for ($i = 1, $count = count($rows); $i < $count; $i++) {
            $markdown .= '| ' . implode(' | ', $rows[$i]) . " |\n";
        }
        $markdown .= "\n";

        return $markdown;
    }

    /**
     * Render ProseMirror nodes to plaintext.
     *
     * @param array<mixed> $nodes
     * @return string
     */
    private function renderNodesTextContent(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= $this->renderNodeTextContent($node);
        }
        return trim($text);
    }

    /**
     * Render a single ProseMirror node to plaintext.
     *
     * @param array<mixed> $node
     * @return string
     */
    private function renderNodeTextContent(array $node): string
    {
        $type = $node['type'] ?? '';
        $content = $node['content'] ?? [];
        $text = $node['text'] ?? '';

        return match ($type) {
            'paragraph', 'heading' => $this->renderInlineTextContent($content) . "\n\n",
            'bulletList', 'bullet_list', 'orderedList', 'ordered_list' => $this->renderListTextContent($content),
            'listItem', 'list_item' => $this->renderInlineTextContent($content),
            'codeBlock', 'code_block' => $this->renderCodeBlockTextContent($content) . "\n\n",
            'blockquote' => $this->renderNodesTextContent($content),
            'image' => $node['attrs']['alt'] ?? '',
            'horizontalRule', 'horizontal_rule' => '',
            'table' => $this->renderTableTextContent($content),
            'hardBreak', 'hard_break' => "\n",
            'text' => $text,
            'set' => $this->renderSetNodeTextContent($node),
            default => $this->renderInlineTextContent($content),
        };
    }

    /**
     * Render inline content to plaintext.
     *
     * @param array<mixed> $content
     * @return string
     */
    private function renderInlineTextContent(array $content): string
    {
        $text = '';
        foreach ($content as $node) {
            $text .= $this->renderNodeTextContent($node);
        }
        return $text;
    }

    /**
     * Render a list to plaintext.
     *
     * @param array<mixed> $content
     * @return string
     */
    private function renderListTextContent(array $content): string
    {
        $text = '';
        foreach ($content as $node) {
            $itemText = $this->renderInlineTextContent($node['content'] ?? []);
            $text .= $itemText . "\n";
        }
        return $text . "\n";
    }

    /**
     * Render a code block to plaintext.
     *
     * @param array<mixed> $content
     * @return string
     */
    private function renderCodeBlockTextContent(array $content): string
    {
        $text = '';
        foreach ($content as $child) {
            if (($child['type'] ?? '') === 'text') {
                $text .= $child['text'] ?? '';
            }
        }
        return $text;
    }

    /**
     * Render a table to plaintext.
     *
     * @param array<mixed> $content
     * @return string
     */
    private function renderTableTextContent(array $content): string
    {
        $text = '';
        foreach ($content as $rowNode) {
            foreach ($rowNode['content'] ?? [] as $cellNode) {
                $cellText = $this->renderInlineTextContent($cellNode['content'] ?? []);
                $text .= trim($cellText) . "\t";
            }
            $text .= "\n";
        }
        return $text . "\n";
    }

    /**
     * Render a Bard `set` node to Markdown.
     *
     * When a set resolver is available (production), each set field is resolved
     * through Statamic's own augmentation into framework-agnostic descriptors —
     * so `assets` fields become fully-qualified URLs with a known media kind,
     * and nested Bard fields come back as raw ProseMirror for high-fidelity
     * rendering. Without a resolver (unit tests, or content lacking asset-bearing
     * sets), it falls back to generic flattening of the raw values.
     *
     * @param array<mixed> $node A `{type: set, attrs: {id, values}}` node.
     * @return string
     */
    private function renderSetNode(array $node): string
    {
        $values = $node['attrs']['values'] ?? [];
        $setType = (string) ($values['type'] ?? $node['attrs']['type'] ?? '');

        if ($setType !== '' && in_array($setType, $this->excludedSets, true)) {
            return '';
        }

        $index = $this->setIndexFromNode($node);
        $descriptors = $this->resolveSetDescriptors($setType, $values, $index);

        if ($descriptors !== null) {
            return $this->renderSetDescriptors($descriptors);
        }

        // Fallback: no resolver — flatten raw values generically.
        return $this->renderSetRawFallback($values, $setType);
    }

    /**
     * Render resolved set descriptors to Markdown, applying the poster→video
     * association convention.
     *
     * Convention (documented in README): within a single set, an image asset
     * whose field handle contains "poster" is treated as the poster frame for a
     * preceding/following video (or audio) asset. It is rendered as the media
     * element's `poster` attribute and NOT emitted as a standalone image.
     *
     * @param list<array<string,mixed>> $descriptors
     * @return string
     */
    private function renderSetDescriptors(array $descriptors): string
    {
        // First pass: find a poster image (handle contains "poster") and the
        // media (video/audio) descriptor it should attach to.
        $posterIndex = null;
        $posterUrl = null;
        foreach ($descriptors as $i => $d) {
            if (($d['kind'] ?? '') === 'asset'
                && ($d['media'] ?? '') === 'image'
                && str_contains(strtolower((string) $d['handle']), 'poster')
            ) {
                $posterIndex = $i;
                $posterUrl = $d['url'];
                break;
            }
        }

        $attachPosterTo = null;
        if ($posterIndex !== null) {
            foreach ($descriptors as $i => $d) {
                if (($d['kind'] ?? '') === 'asset'
                    && in_array($d['media'] ?? '', ['video', 'audio'], true)
                ) {
                    $attachPosterTo = $i;
                    break;
                }
            }
        }

        // If there is no media element to attach the poster to, keep the poster
        // as a normal image rather than dropping it silently.
        if ($attachPosterTo === null) {
            $posterIndex = null;
            $posterUrl = null;
        }

        $markdown = '';
        foreach ($descriptors as $i => $d) {
            if ($i === $posterIndex) {
                continue; // consumed as a poster attribute
            }

            $markdown .= match ($d['kind']) {
                'asset' => $this->renderAssetDescriptor(
                    $d,
                    poster: $i === $attachPosterTo ? $posterUrl : null,
                ),
                'bard' => $this->bardToMarkdown($d['value']) . "\n\n",
                'text' => $this->toMarkdown($d['value']) . "\n\n",
                default => '',
            };
        }

        return $markdown;
    }

    /**
     * Render a single asset descriptor to Markdown.
     *
     * - image → Markdown image `![alt](url)`
     * - video/audio → HTML embed (Markdown cannot represent these; per the
     *   markpub spec raw HTML in CommonMark is preserved by renderers)
     * - file/other → Markdown link
     *
     * @param array<string,mixed> $d
     * @param string|null $poster Optional poster URL for video/audio.
     * @return string
     */
    private function renderAssetDescriptor(array $d, ?string $poster = null): string
    {
        $url = (string) $d['url'];
        $alt = (string) ($d['alt'] ?? '');

        return match ($d['media']) {
            'image' => "![{$alt}]({$url})\n\n",
            'video' => $this->renderVideoEmbed($url, $poster, $d['mime'] ?? null, $alt),
            'audio' => $this->renderAudioEmbed($url, $d['mime'] ?? null, $alt),
            default => $this->renderFileLink($url, $alt),
        };
    }

    private function renderVideoEmbed(string $url, ?string $poster, ?string $mime, string $alt): string
    {
        $posterAttr = $poster !== null ? ' poster="' . $this->escapeAttr($poster) . '"' : '';
        $type = $mime ? ' type="' . $this->escapeAttr($mime) . '"' : '';
        $fallback = $alt !== '' ? $this->escapeAttr($alt) : 'Your browser does not support the video tag.';

        return '<video controls' . $posterAttr . '>'
            . '<source src="' . $this->escapeAttr($url) . '"' . $type . '>'
            . $fallback
            . "</video>\n\n";
    }

    private function renderAudioEmbed(string $url, ?string $mime, string $alt): string
    {
        $type = $mime ? ' type="' . $this->escapeAttr($mime) . '"' : '';
        $fallback = $alt !== '' ? $this->escapeAttr($alt) : 'Your browser does not support the audio tag.';

        return '<audio controls>'
            . '<source src="' . $this->escapeAttr($url) . '"' . $type . '>'
            . $fallback
            . "</audio>\n\n";
    }

    private function renderFileLink(string $url, string $alt): string
    {
        $label = $alt !== '' ? $alt : $url;
        return "[{$label}]({$url})\n\n";
    }

    private function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generic fallback set flattening when no resolver is available.
     *
     * NOTE: this path intentionally does NOT emit asset field values — without
     * the resolver we cannot turn a stored asset path into a usable URL, and
     * emitting the bare path (the original bug) is worse than omitting it. Only
     * string/markdown/HTML field values are flattened here.
     *
     * @param array<string,mixed> $values
     * @param string $setType
     * @return string
     */
    private function renderSetRawFallback(array $values, string $setType): string
    {
        unset($values['type']);

        $markdown = '';
        foreach ($values as $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            // Only render values the converter can represent without a resolver:
            // raw ProseMirror arrays (nested Bard) and prose strings. Bare asset
            // paths (e.g. "talks/x.mp4") cannot be resolved to a URL here, and
            // emitting the raw path is the original bug — so skip them.
            if (is_array($value)) {
                $fieldMarkdown = $this->toMarkdown($value);
                if ($fieldMarkdown !== '') {
                    $markdown .= $fieldMarkdown . "\n\n";
                }
            } elseif (is_string($value) && ! $this->looksLikeAssetPath($value)) {
                $fieldMarkdown = $this->toMarkdown($value);
                if ($fieldMarkdown !== '') {
                    $markdown .= $fieldMarkdown . "\n\n";
                }
            }
        }

        if ($markdown === '' && $setType !== '') {
            $markdown = "<!-- set: {$setType} -->\n\n";
        }

        return $markdown;
    }

    /**
     * Render a Bard `set` node to plaintext (textContent).
     *
     * Assets contribute their alt text (if any); nested Bard and text fields
     * contribute their stripped text. Media embeds have no plaintext beyond alt.
     *
     * @param array<mixed> $node
     * @return string
     */
    private function renderSetNodeTextContent(array $node): string
    {
        $values = $node['attrs']['values'] ?? [];
        $setType = (string) ($values['type'] ?? $node['attrs']['type'] ?? '');

        if ($setType !== '' && in_array($setType, $this->excludedSets, true)) {
            return '';
        }

        $index = $this->setIndexFromNode($node);
        $descriptors = $this->resolveSetDescriptors($setType, $values, $index);

        if ($descriptors !== null) {
            $text = '';
            foreach ($descriptors as $d) {
                $text .= match ($d['kind']) {
                    'asset' => ($d['alt'] ?? '') !== '' ? $d['alt'] . "\n" : '',
                    'bard' => $this->bardToTextContent($d['value']) . "\n",
                    'text' => $this->toTextContent($d['value']) . "\n",
                    default => '',
                };
            }
            return $text;
        }

        // Fallback: flatten raw string/array values.
        unset($values['type']);
        $text = '';
        foreach ($values as $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if (is_array($value)) {
                $fieldText = $this->toTextContent($value);
                if ($fieldText !== '') {
                    $text .= $fieldText . "\n";
                }
            } elseif (is_string($value) && ! $this->looksLikeAssetPath($value)) {
                $fieldText = $this->toTextContent($value);
                if ($fieldText !== '') {
                    $text .= $fieldText . "\n";
                }
            }
        }

        return $text;
    }

    /**
     * Heuristic: does a bare string look like an unresolved asset path?
     *
     * Used ONLY in the no-resolver set fallback to avoid leaking raw stored
     * paths (e.g. "talks/creative-web/slide.mp4") into output. Matches a
     * single token with no whitespace that ends in a file extension. In
     * production the resolver is always present, so this path is not exercised;
     * it is a safety net for unit tests and legacy content.
     */
    private function looksLikeAssetPath(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '' || preg_match('/\s/', $trimmed)) {
            return false;
        }

        return (bool) preg_match('#^[\w\-./]+\.[A-Za-z0-9]{2,5}$#', $trimmed);
    }

    /**
     * Resolve a set's descriptors via the per-call resolver, or null if none.
     *
     * @param array<string,mixed> $values
     * @return list<array<string,mixed>>|null
     */
    private function resolveSetDescriptors(string $setType, array $values, int $index): ?array
    {
        if ($this->setResolver === null || $setType === '') {
            return null;
        }

        $descriptors = ($this->setResolver)($setType, $values, $index);
        return is_array($descriptors) ? $descriptors : null;
    }

    /**
     * Best-effort positional index for a set node (used only for Statamic's
     * field path hashing during augmentation). Any stable int works.
     */
    private function setIndexFromNode(array $node): int
    {
        return 0;
    }

    /**
     * Check if a string looks like HTML.
     */
    private function looksLikeHtml(string $value): bool
    {
        return $value !== strip_tags($value);
    }

    /**
     * Convert HTML to Markdown.
     *
     * Uses a lightweight approach: handles common HTML elements that Bard
     * produces in its "text" set entries and "save as HTML" mode.
     */
    private function htmlToMarkdown(string $html): string
    {
        // Decode entities first
        $html = $this->decodeHtmlEntities($html);

        // Convert common HTML elements to Markdown
        $conversions = [
            '/<h([1-6])[^>]*>(.*?)<\/h\1>/is' => fn($m) => str_repeat('#', (int) $m[1]) . ' ' . trim($m[2]) . "\n\n",
            '/<strong[^>]*>(.*?)<\/strong>/is' => '**$1**',
            '/<b[^>]*>(.*?)<\/b>/is' => '**$1**',
            '/<em[^>]*>(.*?)<\/em>/is' => '*$1*',
            '/<i[^>]*>(.*?)<\/i>/is' => '*$1*',
            '/<code[^>]*>(.*?)<\/code>/is' => '`$1`',
            '/<del[^>]*>(.*?)<\/del>/is' => '~~$1~~',
            '/<s[^>]*>(.*?)<\/s>/is' => '~~$1~~',
            '/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is' => '[$2]($1)',
            '/<img[^>]*src=["\']([^"\']*)["\'][^>]*alt=["\']([^"\']*)["\'][^>]*>/is' => '![$2]($1)',
            '/<img[^>]*src=["\']([^"\']*)["\'][^>]*>/is' => '![]($1)',
            '/<blockquote[^>]*>(.*?)<\/blockquote>/is' => fn($m) => '> ' . str_replace("\n", "\n> ", trim($m[1])) . "\n\n",
            '/<pre[^>]*><code[^>]*>(.*?)<\/code><\/pre>/is' => fn($m) => "```\n" . html_entity_decode($m[1]) . "\n```\n\n",
            '/<br\s*\/?>/i' => "  \n",
            '/<p[^>]*>(.*?)<\/p>/is' => "$1\n\n",
            '/<li[^>]*>(.*?)<\/li>/is' => '- $1',
            '/<\/?(ul|ol|div|span|section|article|figure|figcaption)[^>]*>/i' => '',
        ];

        foreach ($conversions as $pattern => $replacement) {
            if (is_callable($replacement)) {
                $html = preg_replace_callback($pattern, $replacement, $html);
            } else {
                $html = preg_replace($pattern, $replacement, $html);
            }
        }

        // Strip any remaining tags
        $html = strip_tags($html);

        // Clean up excessive whitespace
        $html = preg_replace("/\n{3,}/", "\n\n", $html);

        return trim($html);
    }

    /**
     * Strip Markdown syntax to get plaintext.
     */
    private function stripMarkdown(string $markdown): string
    {
        // Remove code blocks
        $markdown = preg_replace('/```.*?```/s', '', $markdown);
        // Remove inline code
        $markdown = preg_replace('/`([^`]+)`/', '$1', $markdown);
        // Remove images (keep alt text)
        $markdown = preg_replace('/!\[([^\]]*)\]\([^)]+\)/', '$1', $markdown);
        // Remove links (keep text)
        $markdown = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $markdown);
        // Remove bold/italic markers
        $markdown = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $markdown);
        $markdown = preg_replace('/_{1,2}([^_]+)_{1,2}/', '$1', $markdown);
        // Remove strikethrough
        $markdown = preg_replace('/~~([^~]+)~~/', '$1', $markdown);
        // Remove heading markers
        $markdown = preg_replace('/^#{1,6}\s+/m', '', $markdown);
        // Remove list markers
        $markdown = preg_replace('/^[\s]*[-*+]\s+/m', '', $markdown);
        $markdown = preg_replace('/^[\s]*\d+\.\s+/m', '', $markdown);
        // Remove blockquote markers
        $markdown = preg_replace('/^>\s*/m', '', $markdown);
        // Remove horizontal rules
        $markdown = preg_replace('/^---+$/m', '', $markdown);

        return trim($markdown);
    }

    /**
     * Decode HTML entities to their text equivalents.
     */
    private function decodeHtmlEntities(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
