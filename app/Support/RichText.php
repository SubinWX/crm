<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Formatted text written with the editor field (<x-editor>): bold, lists, headings, links.
 *
 * Text is cleaned to a small set of harmless tags when it is saved and again when it is shown,
 * so nothing typed or pasted into an editor can run scripts. Text saved before the editor
 * existed is plain; it is shown with its line breaks kept.
 */
class RichText
{
    /** Tags that survive cleaning. Everything else is unwrapped (its text is kept). */
    protected const ALLOWED = ['div', 'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'del', 's', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'blockquote', 'pre', 'a'];

    /** Tags removed together with everything inside them. */
    protected const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'link', 'meta', 'head', 'title', 'template', 'noscript', 'figure', 'img', 'video', 'audio'];

    public static function isPlain(?string $text): bool
    {
        // "Plain" means no HTML tags at all. A lone "<" (as in "a < b" or "<3") is still plain text.
        return ! preg_match('~<\s*/?\s*[a-zA-Z][^<>]*>~', (string) $text);
    }

    /** What gets stored: cleaned HTML, the original plain text, or null when there is nothing but whitespace. */
    public static function clean(?string $text): ?string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        if (self::isPlain($text)) {
            return $text;
        }
        $html = self::sanitize($text);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{A0}") === '' ? null : $html;
    }

    /** Safe HTML for a page or PDF. $autolink turns bare web addresses in plain text into links. */
    public static function render(?string $text, bool $autolink = false, ?string $linkColor = null): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        $style = $linkColor ? ' style="color:'.e($linkColor).';"' : '';

        if (self::isPlain($text)) {
            $html = nl2br(e($text));

            return $autolink
                ? preg_replace('~(https?://[^\s<]+)~i', '<a href="$1"'.$style.' target="_blank" rel="noopener noreferrer nofollow">$1</a>', $html)
                : $html;
        }

        $html = self::sanitize($text);

        return $linkColor ? str_replace('<a href=', '<a'.$style.' href=', $html) : $html;
    }

    /** What the editor loads. Plain text from before the editor existed keeps its line breaks. */
    public static function forEditor(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        return self::isPlain($text)
            ? '<div>'.str_replace(["\r\n", "\r", "\n"], '<br>', e($text)).'</div>'
            : self::sanitize($text);
    }

    /** Text only, for table previews, CSV exports and emails. List items and paragraphs become lines. */
    public static function plain(?string $text): string
    {
        $text = (string) $text;
        if (self::isPlain($text)) {
            return trim($text);
        }
        $text = preg_replace('~<li[^>]*>~i', '• ', $text);
        $text = preg_replace('~<br\s*/?>|</(div|p|li|h[1-6]|blockquote|pre|ul|ol)>~i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("~[ \t]*\n\s*~", "\n", str_replace("\u{A0}", ' ', $text)));
    }

    protected static function sanitize(string $html): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="rich-root">'.$html.'</div>', LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('rich-root');
        if (! $root) {
            return e(strip_tags($html));
        }
        self::cleanChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    protected static function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                continue;
            }
            if (! $node instanceof DOMElement) {
                $parent->removeChild($node); // comments, processing instructions, CDATA

                continue;
            }

            $tag = strtolower($node->nodeName);
            if (in_array($tag, self::DROPPED, true)) {
                $parent->removeChild($node);

                continue;
            }

            self::cleanChildren($node);

            if (! in_array($tag, self::ALLOWED, true)) {
                // Keep the text, lose the tag.
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            $href = $tag === 'a' ? trim($node->getAttribute('href')) : '';
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $node->removeAttribute($attribute->nodeName);
            }
            if ($tag === 'a') {
                if (preg_match('~^(https?://|mailto:|tel:)~i', $href)) {
                    $node->setAttribute('href', $href);
                    $node->setAttribute('target', '_blank');
                    $node->setAttribute('rel', 'noopener noreferrer nofollow');
                } else {
                    // Unsafe or relative address: keep the words, drop the link.
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                }
            }
        }
    }
}
