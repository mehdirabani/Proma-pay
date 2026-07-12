<?php

class ContractTemplateRenderer
{
    const FORMAT_PLAIN = 'plain_text_v1';
    const FORMAT_HTML = 'structured_html_v1';

    public static function render($source, $format = self::FORMAT_PLAIN, array $replacements = [])
    {
        $source = self::normalizeSource($source);
        $html = $format === self::FORMAT_HTML
            ? self::sanitizeHtml($source)
            : self::renderPlainText($source);

        if ($replacements) {
            $html = strtr($html, $replacements);
        }
        return trim($html);
    }

    public static function normalizeSource($source)
    {
        $source = str_replace(["\r\n", "\r"], "\n", (string) $source);
        $lines = array_map(static function ($line) {
            return rtrim((string) $line, " \t");
        }, explode("\n", $source));
        $source = trim(implode("\n", $lines));
        return preg_replace('/\n{3,}/u', "\n\n", $source);
    }

    public static function renderPlainText($source)
    {
        $source = self::normalizeSource($source);
        if ($source === '') {
            return '';
        }

        $blocks = preg_split('/\n{2}/u', $source) ?: [];
        $html = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            if (preg_match('/^\{\{[a-z0-9_]+\}\}$/i', $block)) {
                $html[] = $block;
                continue;
            }
            $lines = array_values(array_filter(explode("\n", $block), static function ($line) {
                return trim((string) $line) !== '';
            }));
            if (!$lines) {
                continue;
            }

            $allListItems = true;
            $ordered = true;
            foreach ($lines as $line) {
                if (!preg_match('/^\s*(?:[-*•]|\d+[.)])\s+(.+)$/u', $line)) {
                    $allListItems = false;
                    break;
                }
                if (!preg_match('/^\s*\d+[.)]\s+/u', $line)) {
                    $ordered = false;
                }
            }
            if ($allListItems) {
                $tag = $ordered ? 'ol' : 'ul';
                $items = '';
                foreach ($lines as $line) {
                    $text = preg_replace('/^\s*(?:[-*•]|\d+[.)])\s+/u', '', $line);
                    $items .= '<li>' . self::renderImportantInline($text) . '</li>';
                }
                $html[] = '<' . $tag . ' class="contract-list">' . $items . '</' . $tag . '>';
                continue;
            }

            if (count($lines) === 1 && preg_match('/^(?:ماده|تبصره|بند)\s*[۰-۹0-9]*/u', trim($lines[0]))) {
                $html[] = '<h2 class="contract-section-title">' . self::renderImportantInline(trim($lines[0])) . '</h2>';
                continue;
            }

            if (count($lines) === 1 && self::isCompleteImportantParagraph($lines[0])) {
                $content = mb_substr(trim($lines[0]), 2, -2, 'UTF-8');
                $html[] = '<p class="contract-paragraph contract-important-paragraph"><strong class="contract-important-clause">' . e($content) . '</strong></p>';
                continue;
            }

            $rendered = array_map([self::class, 'renderImportantInline'], $lines);
            $html[] = '<p class="contract-paragraph">' . implode('<br>', $rendered) . '</p>';
        }
        return implode("\n", $html);
    }

    public static function sanitizeHtml($html)
    {
        $html = self::decodeEntities(trim((string) $html));
        if ($html === '') {
            return '';
        }
        if (!preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $html)) {
            return self::renderPlainText($html);
        }

        if (!class_exists('DOMDocument')) {
            return self::sanitizeFallback($html);
        }

        $allowed = array_flip([
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'h4', 'ul', 'ol', 'li',
            'blockquote', 'span', 'section', 'div', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        ]);
        $approvedClasses = array_flip([
            'contract-paragraph', 'contract-section-title', 'contract-list', 'contract-note',
            'contract-print-table', 'text-center', 'text-right', 'text-left', 'ltr',
            'contract-document-body', 'contract-signature-grid', 'contract-signature-box',
            'contract-guarantors-section', 'contract-guarantor-box', 'contract-empty', 'full',
            'contract-important-clause', 'contract-important-paragraph',
        ]);

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="utf-8" ?><section id="contract-sanitize-root">' . $html . '</section>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $root = $document->getElementById('contract-sanitize-root');
        if (!$root) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return '';
        }

        $walk = function (DOMNode $node) use (&$walk, $allowed, $approvedClasses) {
            for ($child = $node->firstChild; $child;) {
                $next = $child->nextSibling;
                if ($child instanceof DOMComment) {
                    $node->removeChild($child);
                } elseif ($child instanceof DOMElement) {
                    $tag = strtolower($child->tagName);
                    if (!isset($allowed[$tag])) {
                        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'link', 'meta'], true)) {
                            $node->removeChild($child);
                        } else {
                            while ($child->firstChild) {
                                $node->insertBefore($child->firstChild, $child);
                            }
                            $node->removeChild($child);
                        }
                    } else {
                        $classValue = '';
                        if ($child->hasAttribute('class')) {
                            $classes = preg_split('/\s+/', trim($child->getAttribute('class'))) ?: [];
                            $classes = array_values(array_filter($classes, static function ($class) use ($approvedClasses) {
                                return isset($approvedClasses[$class]);
                            }));
                            $classValue = implode(' ', array_unique($classes));
                        }
                        $dirValue = $child->hasAttribute('dir') && in_array($child->getAttribute('dir'), ['rtl', 'ltr'], true)
                            ? $child->getAttribute('dir')
                            : '';
                        $colspan = in_array($tag, ['th', 'td'], true) ? max(0, min(12, (int) $child->getAttribute('colspan'))) : 0;
                        $rowspan = in_array($tag, ['th', 'td'], true) ? max(0, min(12, (int) $child->getAttribute('rowspan'))) : 0;
                        while ($child->attributes && $child->attributes->length) {
                            $child->removeAttributeNode($child->attributes->item(0));
                        }
                        if ($classValue !== '') {
                            $child->setAttribute('class', $classValue);
                        }
                        if ($dirValue !== '') {
                            $child->setAttribute('dir', $dirValue);
                        }
                        if ($colspan > 1) {
                            $child->setAttribute('colspan', (string) $colspan);
                        }
                        if ($rowspan > 1) {
                            $child->setAttribute('rowspan', (string) $rowspan);
                        }
                        $walk($child);
                    }
                }
                $child = $next;
            }
        };
        $walk($root);
        self::applyImportantMarkupToDom($document, $root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return trim($output);
    }

    protected static function sanitizeFallback($html)
    {
        $allowed = '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><blockquote><span><section><div><table><thead><tbody><tfoot><tr><th><td>';
        $html = strip_tags((string) $html, $allowed);
        $html = preg_replace('/\s+(?:on[a-z]+|style|src|href)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/\s+(?!class\b|dir\b|colspan\b|rowspan\b)[a-z0-9_:-]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        if (substr_count($html, '**') % 2 === 0) {
            $html = preg_replace_callback('/\*\*([^<]+?)\*\*/u', static function ($match) {
                return '<strong class="contract-important-clause">' . $match[1] . '</strong>';
            }, $html);
        }
        return trim($html);
    }

    public static function hasBalancedImportantMarkers($source)
    {
        return substr_count((string) $source, '**') % 2 === 0;
    }

    protected static function renderImportantInline($text)
    {
        $text = (string) $text;
        if (strpos($text, '**') === false || !self::hasBalancedImportantMarkers($text)) {
            return e($text);
        }
        $parts = preg_split('/(\*\*.+?\*\*)/us', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $html = '';
        foreach ($parts as $part) {
            if (preg_match('/^\*\*(.+)\*\*$/us', $part, $match)) {
                $html .= '<strong class="contract-important-clause">' . e($match[1]) . '</strong>';
            } else {
                $html .= e($part);
            }
        }
        return $html;
    }

    protected static function isCompleteImportantParagraph($text)
    {
        $text = trim((string) $text);
        return substr_count($text, '**') === 2 && preg_match('/^\*\*.+\*\*$/us', $text) === 1;
    }

    protected static function applyImportantMarkupToDom(DOMDocument $document, DOMNode $root)
    {
        $nodes = [];
        $collect = function (DOMNode $node) use (&$collect, &$nodes) {
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMText) {
                    $nodes[] = $child;
                } elseif ($child instanceof DOMElement) {
                    $collect($child);
                }
            }
        };
        $collect($root);

        foreach ($nodes as $node) {
            if (!$node->parentNode || strpos($node->nodeValue, '**') === false || !self::hasBalancedImportantMarkers($node->nodeValue)) {
                continue;
            }
            $parent = $node->parentNode;
            if ($parent instanceof DOMElement && strpos(' ' . $parent->getAttribute('class') . ' ', ' contract-important-clause ') !== false) {
                continue;
            }
            $complete = $parent instanceof DOMElement
                && strtolower($parent->tagName) === 'p'
                && $parent->childNodes->length === 1
                && self::isCompleteImportantParagraph($node->nodeValue);
            if ($complete) {
                $classes = preg_split('/\s+/', trim($parent->getAttribute('class'))) ?: [];
                $classes[] = 'contract-paragraph';
                $classes[] = 'contract-important-paragraph';
                $parent->setAttribute('class', implode(' ', array_unique(array_filter($classes))));
            }

            $parts = preg_split('/(\*\*.+?\*\*)/us', $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$node->nodeValue];
            $fragment = $document->createDocumentFragment();
            foreach ($parts as $part) {
                if (preg_match('/^\*\*(.+)\*\*$/us', $part, $match)) {
                    $strong = $document->createElement('strong');
                    $strong->setAttribute('class', 'contract-important-clause');
                    $strong->appendChild($document->createTextNode($match[1]));
                    $fragment->appendChild($strong);
                } elseif ($part !== '') {
                    $fragment->appendChild($document->createTextNode($part));
                }
            }
            $parent->replaceChild($fragment, $node);
        }
    }

    protected static function decodeEntities($value)
    {
        for ($i = 0; $i < 2; $i++) {
            $decoded = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }
        return (string) $value;
    }
}
