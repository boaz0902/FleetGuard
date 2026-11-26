<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\HtmlString;

class LegalPolicyContent
{
    private const ALLOWED_ELEMENTS = [
        'b', 'blockquote', 'br', 'del', 'div', 'em', 'font', 'h1', 'h2', 'h3', 'h4',
        'i', 'li', 'ol', 'p', 's', 'span', 'strike', 'strong', 'sub', 'sup', 'u', 'ul',
    ];

    private const DISALLOWED_ELEMENTS = [
        'audio', 'base', 'canvas', 'embed', 'form', 'iframe', 'img', 'input', 'link',
        'math', 'meta', 'noscript', 'object', 'script', 'style', 'svg', 'template', 'video',
    ];

    private const FONT_FAMILIES = [
        'Arial', 'Courier New', 'Georgia', 'Tahoma', 'Times New Roman', 'Trebuchet MS', 'Verdana',
    ];

    public function render(string $content): HtmlString
    {
        return new HtmlString($this->containsMarkup($content)
            ? $this->sanitizeHtml($content)
            : $this->renderPlainText($content));
    }

    public function renderDocument(string $content): HtmlString
    {
        $html = $this->render($content)->toHtml();
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="legal-document-root">'.$html.'</div></body></html>',
                LIBXML_NONET,
            );
            $root = $document->getElementById('legal-document-root');

            if (! $root) {
                return new HtmlString($html);
            }

            $previousLevel = 1;
            $this->normalizeDocumentHeadings($root, $previousLevel);
            $rendered = '';

            foreach ($root->childNodes as $child) {
                $rendered .= $document->saveHTML($child);
            }

            return new HtmlString(trim($rendered));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    public function editorContent(string $content): HtmlString
    {
        return $this->render($content);
    }

    public function sanitizeForStorage(string $content): string
    {
        return $this->containsMarkup($content)
            ? $this->sanitizeHtml($content)
            : trim($content);
    }

    public function containsUnsafeMarkup(string $content): bool
    {
        return preg_match(
            '/<\s*(?:audio|base|canvas|embed|form|iframe|img|input|link|math|meta|noscript|object|script|style|svg|template|video)\b|(?:^|\s)on[a-z0-9_-]+\s*=|javascript\s*:/i',
            $content,
        ) === 1;
    }

    private function containsMarkup(string $content): bool
    {
        return preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $content) === 1;
    }

    private function renderPlainText(string $content): string
    {
        $blocks = [];
        $listType = null;
        $listItems = [];

        foreach (preg_split('/\R/u', $content) ?: [] as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                $this->flushList($blocks, $listType, $listItems);

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/u', $line, $matches)) {
                $this->startList('ul', $blocks, $listType, $listItems);
                $listItems[] = $this->inline($matches[1]);

                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/u', $line, $matches)) {
                $this->startList('ol', $blocks, $listType, $listItems);
                $listItems[] = $this->inline($matches[1]);

                continue;
            }

            $this->flushList($blocks, $listType, $listItems);

            if (str_starts_with($line, '### ')) {
                $blocks[] = '<h3>'.$this->inline(substr($line, 4)).'</h3>';
            } elseif (str_starts_with($line, '## ')) {
                $blocks[] = '<h2>'.$this->inline(substr($line, 3)).'</h2>';
            } else {
                $blocks[] = '<p>'.$this->inline($line).'</p>';
            }
        }

        $this->flushList($blocks, $listType, $listItems);

        return implode("\n", $blocks);
    }

    private function sanitizeHtml(string $content): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="legal-content-root">'.$content.'</div></body></html>',
                LIBXML_NONET,
            );
            $root = $document->getElementById('legal-content-root');

            if (! $root) {
                return '';
            }

            $this->sanitizeChildren($root);

            $html = '';
            foreach ($root->childNodes as $child) {
                $html .= $document->saveHTML($child);
            }

            return trim($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType !== XML_TEXT_NODE) {
                    $parent->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DISALLOWED_ELEMENTS, true)) {
                $parent->removeChild($child);

                continue;
            }

            $this->sanitizeChildren($child);

            if ($tag === 'font') {
                $this->replaceFontElement($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_ELEMENTS, true)) {
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }

                $parent->removeChild($child);

                continue;
            }

            $this->sanitizeAttributes($child);
        }
    }

    private function normalizeDocumentHeadings(DOMNode $parent, int &$previousLevel): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (preg_match('/^h([1-6])$/', $tag, $matches)) {
                $level = max(2, min((int) $matches[1], $previousLevel + 1));

                if ($level !== (int) $matches[1]) {
                    $replacement = $child->ownerDocument->createElement('h'.$level);

                    foreach (iterator_to_array($child->attributes) as $attribute) {
                        $replacement->setAttribute($attribute->name, $attribute->value);
                    }

                    while ($child->firstChild) {
                        $replacement->appendChild($child->firstChild);
                    }

                    $child->parentNode?->replaceChild($replacement, $child);
                    $child = $replacement;
                }

                $previousLevel = $level;
            }

            $this->normalizeDocumentHeadings($child, $previousLevel);
        }
    }

    private function replaceFontElement(DOMElement $font): void
    {
        $span = $font->ownerDocument->createElement('span');
        $styles = [];
        $face = trim($font->getAttribute('face'), " \t\n\r\0\x0B\"'");
        $size = trim($font->getAttribute('size'));
        $color = trim($font->getAttribute('color'));
        $sizeMap = ['1' => '8pt', '2' => '10pt', '3' => '12pt', '4' => '14pt', '5' => '18pt', '6' => '24pt', '7' => '36pt'];

        if (in_array($face, self::FONT_FAMILIES, true)) {
            $styles['font-family'] = $this->quoteFontFamily($face);
        }

        if (isset($sizeMap[$size])) {
            $styles['font-size'] = $sizeMap[$size];
        } elseif (preg_match('/^(?:[6-9]|[1-6]\d|7[0-2])(?:\.\d+)?(?:pt|px)$/i', $size)) {
            $styles['font-size'] = strtolower($size);
        }

        if ($this->isColor($color)) {
            $styles['color'] = strtolower($color);
        }

        if ($styles !== []) {
            $span->setAttribute('style', implode('; ', array_map(
                fn (string $property, string $value): string => $property.': '.$value,
                array_keys($styles),
                array_values($styles),
            )));
        }

        while ($font->firstChild) {
            $span->appendChild($font->firstChild);
        }

        $font->parentNode?->replaceChild($span, $font);
        $this->sanitizeAttributes($span);
    }

    private function sanitizeAttributes(DOMElement $element): void
    {
        $style = $this->sanitizeStyle($element->getAttribute('style'));

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttributeNode($attribute);
        }

        if ($style !== '') {
            $element->setAttribute('style', $style);
        }
    }

    private function sanitizeStyle(string $style): string
    {
        $safe = [];

        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$property, $value] = array_map('trim', $parts);
            $property = strtolower($property);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $safeValue = match ($property) {
                'font-family' => $this->sanitizeFontFamily($value),
                'font-size' => preg_match('/^(?:[6-9]|[1-6]\d|7[0-2])(?:\.\d+)?(?:pt|px)$/i', $value) ? strtolower($value) : null,
                'font-weight' => in_array(strtolower($value), ['normal', 'bold', '400', '500', '600', '700'], true) ? strtolower($value) : null,
                'color', 'background-color' => $this->isColor($value) ? strtolower($value) : null,
                'text-align' => in_array(strtolower($value), ['left', 'center', 'right', 'justify'], true) ? strtolower($value) : null,
                'line-height' => in_array($value, ['1', '1.15', '1.5', '2', '2.5'], true) ? $value : null,
                'margin' => $this->sanitizeIndentMargin($value),
                'margin-left' => preg_match('/^(?:[0-4](?:\.\d+)?em|(?:0|[1-9]\d?)px)$/i', $value) ? strtolower($value) : null,
                default => null,
            };

            if ($safeValue !== null) {
                $safe[$property === 'margin' ? 'margin-left' : $property] = $safeValue;
            }
        }

        $declarations = [];
        foreach ($safe as $property => $value) {
            $declarations[] = $property.': '.$value;
        }

        return implode('; ', $declarations);
    }

    private function sanitizeFontFamily(string $value): ?string
    {
        $family = trim($value, " \t\n\r\0\x0B\"'");

        return in_array($family, self::FONT_FAMILIES, true)
            ? $this->quoteFontFamily($family)
            : null;
    }

    private function sanitizeIndentMargin(string $value): ?string
    {
        $parts = preg_split('/\s+/', strtolower(trim($value))) ?: [];

        if (count($parts) !== 4 || ! in_array($parts[0], ['0', '0px'], true)
            || ! in_array($parts[1], ['0', '0px'], true) || ! in_array($parts[2], ['0', '0px'], true)
            || ! preg_match('/^(?:0|[1-7]?\d)px$/', $parts[3])) {
            return null;
        }

        return $parts[3];
    }

    private function quoteFontFamily(string $family): string
    {
        return str_contains($family, ' ') ? '"'.$family.'"' : $family;
    }

    private function isColor(string $value): bool
    {
        return preg_match('/^#[a-f0-9]{3,8}$/i', $value) === 1
            || preg_match('/^rgba?\([\d\s.,%]+\)$/i', $value) === 1
            || in_array(strtolower($value), ['black', 'blue', 'gray', 'green', 'orange', 'purple', 'red', 'white', 'yellow'], true);
    }

    private function inline(string $text): string
    {
        $escaped = e($text);
        $bold = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $escaped);
        $formatted = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u', '<em>$1</em>', $bold ?? $escaped);

        return $formatted ?? $escaped;
    }

    private function startList(string $type, array &$blocks, ?string &$listType, array &$listItems): void
    {
        if ($listType !== $type) {
            $this->flushList($blocks, $listType, $listItems);
            $listType = $type;
        }
    }

    private function flushList(array &$blocks, ?string &$listType, array &$listItems): void
    {
        if ($listType === null) {
            return;
        }

        $items = array_map(fn (string $item): string => '<li>'.$item.'</li>', $listItems);
        $blocks[] = '<'.$listType.'>'.implode('', $items).'</'.$listType.'>';
        $listType = null;
        $listItems = [];
    }
}
