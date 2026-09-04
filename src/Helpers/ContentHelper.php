<?php

declare(strict_types=1);

/**
 * ContentHelper.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Helpers;

class ContentHelper
{
    /**
     * Inline HTML tags supported by htmlToMarkdown. Drives containsKnownHtml.
     */
    private const KNOWN_HTML_TAGS = ['p', 'strong', 'em', 'u', 's', 'a', 'ul', 'ol', 'li', 'br', 'mark', 'code', 'del', 'ins'];

    /**
     * Post-strip pipeline used by cleanTitle. The HTML strip and entity decode run
     * before this map (they must happen first); these rules then drop markdown styling,
     * control characters and collapse whitespace, in order.
     */
    private const CLEAN_TITLE_RULES = [
        '/\[([^\]]+)\]\([^)]+\)/' => '$1',
        '/[`*_~#\[\]>]+/u'        => '',
        '/[\x00-\x1F\x7F]/u'      => ' ',
        '/\s+/u'                  => ' ',
    ];

    /**
     * Symmetric HTML pairs <tag>content</tag> converted by htmlToMarkdown.
     * Key: tag name. Value: replacement template where $1 is the inner content.
     */
    private const HTML_TO_MD_PAIRS = [
        'p'      => "\$1\n\n",
        'strong' => '**$1**',
        'em'     => '*$1*',
        'u'      => '++$1++',
        'ins'    => '++$1++',
        's'      => '~~$1~~',
        'del'    => '~~$1~~',
        'mark'   => '==$1==',
        'code'   => '`$1`',
        'li'     => "- \$1\n",
    ];

    /**
     * Post-processing applied at the end of htmlToMarkdown.
     */
    private const HTML_TO_MD_POSTPROCESS = [
        '/\n{3,}/'   => "\n\n",
        '/[ \t]+\n/' => "\n",
    ];

    /**
     * Markdown inline markers converted to HTML by markdownToHtml.
     * Order matters: multi-char markers (++, ~~, ==, **) must run before their single-char counterparts.
     */
    private const MD_INLINE_TO_HTML = [
        '/\+\+(.+?)\+\+/'      => '<u>$1</u>',
        '/~~(.+?)~~/'          => '<s>$1</s>',
        '/==(.+?)==/'          => '<mark>$1</mark>',
        '/\*\*(.+?)\*\*/'      => '<strong>$1</strong>',
        '/`(.+?)`/'            => '<code>$1</code>',
        '/\*(.+?)\*/'          => '<em>$1</em>',
        '/\[(.+?)\]\((.+?)\)/' => '<a href="$2" target="_blank">$1</a>',
    ];

    /**
     * Cleans a string intended to be used as a title (heading, og:title, JSON-LD name, ...).
     *
     * Replaces every HTML tag with a single space (so adjacent inline tags don't collapse
     * the surrounding words together), decodes HTML entities, then runs CLEAN_TITLE_RULES
     * to drop markdown styling, control characters and collapse whitespace.
     *
     * Example: "ertyuy erty<br><em>ghfgh</em>" -> "ertyuy erty ghfgh"
     */
    public static function cleanTitle(string $html): string
    {
        $cleaned = preg_replace('/<[^>]+>/', ' ', $html);
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        foreach (self::CLEAN_TITLE_RULES as $pattern => $replacement) {
            $cleaned = preg_replace($pattern, $replacement, $cleaned);
        }
        return trim($cleaned);
    }

    /**
     * If $line is a markdown heading (`# ...`, `## ...`, etc.), cleans its content via
     * cleanTitle() while preserving the leading `#` prefix. Otherwise returns $line unchanged.
     */
    public static function cleanHeadingLine(string $line): string
    {
        if (preg_match('/^(\s*#+\s+)(.*)$/', $line, $matches) === 1) {
            return $matches[1].self::cleanTitle($matches[2]);
        }
        return $line;
    }

    /**
     * Detects whether a string contains any of the inline HTML tags supported by htmlToMarkdown.
     */
    public static function containsKnownHtml(string $value): bool
    {
        $pattern = '#<(?:'.implode('|', self::KNOWN_HTML_TAGS).')\b[^>]*>#i';
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Converts a limited subset of HTML (rich text editor output) to markdown.
     *
     * Symmetric pairs come from HTML_TO_MD_PAIRS. Special cases (Quill artefacts, <br>,
     * <a href="...">, list wrappers) stay inline because they don't fit the simple
     * <tag>content</tag> -> template shape.
     */
    public static function htmlToMarkdown(string $html): string
    {
        $md = $html;

        $md = preg_replace('#<span class="ql-ui"[^>]*></span>#', '', $md);
        $md = preg_replace_callback(
            '#<ol>(\s*<li\s+data-list="(?<type>bullet|ordered)"[^>]*>.*?)</ol>#s',
            [self::class, 'normalizeQuillListBlock'],
            $md
        );

        foreach (self::HTML_TO_MD_PAIRS as $tag => $template) {
            $md = preg_replace('#<'.$tag.'>(.*?)</'.$tag.'>#s', $template, $md);
        }

        $md = preg_replace('#<br\s*/?>#i', "  \n", $md);
        $md = preg_replace('#<a\s+href="([^"]*)"[^>]*>(.*?)</a>#s', '[$2]($1)', $md);
        $md = preg_replace('#</?(?:ul|ol)>#s', '', $md);

        $md = strip_tags($md);

        foreach (self::HTML_TO_MD_POSTPROCESS as $pattern => $replacement) {
            $md = preg_replace($pattern, $replacement, $md);
        }

        return trim($md);
    }

    /**
     * Converts a limited subset of markdown back to HTML. Inverse of htmlToMarkdown().
     */
    public static function markdownToHtml(string $markdown): string
    {
        $md = trim($markdown);
        $lines = explode("\n", $md);

        $blocks = [];
        $currentBlock = [];
        $currentType = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if (empty($currentBlock) === false) {
                    $blocks[] = ['type' => $currentType, 'lines' => $currentBlock];
                    $currentBlock = [];
                    $currentType = null;
                }
                continue;
            }

            $lineType = str_starts_with($trimmed, '- ') === true ? 'list' : 'text';

            if ($currentType !== null && $currentType !== $lineType) {
                $blocks[] = ['type' => $currentType, 'lines' => $currentBlock];
                $currentBlock = [];
            }

            $currentType = $lineType;
            $currentBlock[] = $trimmed;
        }

        if (empty($currentBlock) === false) {
            $blocks[] = ['type' => $currentType, 'lines' => $currentBlock];
        }

        $html = '';
        foreach ($blocks as $block) {
            if ($block['type'] === 'list') {
                $items = '';
                foreach ($block['lines'] as $line) {
                    $items .= '<li>'.substr($line, 2).'</li>';
                }
                $html .= '<ul>'.$items.'</ul>';
            } else {
                $content = implode(' ', $block['lines']);
                $html .= '<p>'.$content.'</p>';
            }
        }

        foreach (self::MD_INLINE_TO_HTML as $pattern => $replacement) {
            $html = preg_replace($pattern, $replacement, $html);
        }

        return $html;
    }

    private static function normalizeQuillListBlock(array $matches): string
    {
        $tag = $matches['type'] === 'bullet' ? 'ul' : 'ol';
        $inner = preg_replace('# data-list="[^"]*"#', '', $matches[1]);
        return '<'.$tag.'>'.$inner.'</'.$tag.'>';
    }
}
