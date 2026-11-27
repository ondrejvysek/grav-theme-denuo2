<?php

declare(strict_types=1);

namespace Grav\Theme\Denuo2;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;

/**
 * Prepares rendered article HTML: ids on h2–h5 (for the table of contents and deep links),
 * scrollable, labelled wrappers around wide tables, and lazy loading plus width/height for
 * the page's own images (no layout shift).
 */
final class Outline
{
    private const HEADINGS = ['h2', 'h3', 'h4', 'h5'];

    public function __construct(private Grav $grav)
    {
    }

    /**
     * @return array{html: string, headings: list<array{id: string, label: string, level: int}>}
     */
    public function build(mixed $html, ?PageInterface $page = null): array
    {
        $html = (string) $html;
        $result = ['html' => $html, 'headings' => []];
        if (!preg_match($page ? '~<(?:h[2-5]|table|img)\b~i' : '~<(?:h[2-5]|table)\b~i', $html)) {
            return $result;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        if (!$loaded) {
            return $result;
        }
        $body = $dom->getElementsByTagName('body')->item(0);
        $xpath = new DOMXPath($dom);

        // Ids the page already uses (other than on headings) are never handed out again.
        $used = ['toc' => true, 'article-toc' => true, 'article-content' => true];
        $reserved = [];
        foreach ($xpath->query('.//*[@id]', $body) as $element) {
            $id = $element->getAttribute('id');
            if (!in_array($element->nodeName, self::HEADINGS, true)) {
                $used[$id] = true;
            }
            $reserved[$id] = true;
        }

        $query = './/*[self::h2 or self::h3 or self::h4 or self::h5][not(ancestor::pre or ancestor::code or ancestor::template or ancestor::svg)]';
        foreach ($xpath->query($query, $body) as $heading) {
            $label = trim((string) preg_replace('~\s+~u', ' ', $heading->textContent));
            if ($label === '') {
                continue;
            }
            $existing = $heading->getAttribute('id');
            $base = $existing !== '' ? $existing : ($this->slug($label) ?: 'section');
            $id = $base;
            for ($suffix = 1; isset($used[$id]) || (isset($reserved[$id]) && $id !== $existing); $suffix++) {
                $id = $base . '-' . $suffix;
            }
            $used[$id] = true;
            $heading->setAttribute('id', $id);
            $heading->setAttribute('tabindex', '-1');
            $result['headings'][] = ['id' => $id, 'label' => $label, 'level' => (int) $heading->nodeName[1]];
        }

        $tableLabel = (string) $this->grav['language']->translate(['THEME_DENUO2.ARTICLE.TABLE']);
        foreach ($xpath->query('.//table[not(ancestor::table or ancestor::template)]', $body) as $table) {
            $this->wrapTable($table, $tableLabel);
        }

        if ($page) {
            foreach ($xpath->query('.//img[not(ancestor::template or ancestor::svg)]', $body) as $image) {
                $this->prepareImage($image, $page);
            }
        }

        $result['html'] = '';
        foreach ($body->childNodes as $child) {
            $result['html'] .= $dom->saveHTML($child);
        }
        return $result;
    }

    private function slug(string $label): string
    {
        $text = function_exists('transliterator_transliterate')
            ? (transliterator_transliterate('Any-Latin; Latin-ASCII', $label) ?: $label)
            : $label;
        $text = (string) preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = (string) preg_replace('~[^-\w]+~', '', $text);
        return strtolower(trim((string) preg_replace('~-+~', '-', $text), '-'));
    }

    /** A focusable, labelled region that scrolls sideways; an existing wrapper is reused. */
    private function wrapTable(DOMElement $table, string $label): void
    {
        $parent = $table->parentNode;
        if ($parent instanceof DOMElement
            && preg_match('~(?:^|\s)(?:table-responsive|d2-table-wrap)(?:\s|$)~', $parent->getAttribute('class'))) {
            $wrapper = $parent;
            if (!preg_match('~(?:^|\s)d2-table-wrap(?:\s|$)~', $wrapper->getAttribute('class'))) {
                $wrapper->setAttribute('class', trim($wrapper->getAttribute('class') . ' d2-table-wrap'));
            }
        } else {
            $wrapper = $table->ownerDocument->createElement('div');
            $wrapper->setAttribute('class', 'd2-table-wrap');
            $parent->insertBefore($wrapper, $table);
            $wrapper->appendChild($table);
        }
        $wrapper->setAttribute('tabindex', '0');
        $wrapper->setAttribute('role', 'region');
        $caption = trim($table->getElementsByTagName('caption')->item(0)?->textContent ?? '');
        $wrapper->setAttribute('aria-label', $caption ?: $label);
    }

    /** Lazy loading and intrinsic size for local images that lack them. */
    private function prepareImage(DOMElement $image, PageInterface $page): void
    {
        if (!$image->hasAttribute('loading') && strtolower($image->getAttribute('fetchpriority')) !== 'high') {
            $image->setAttribute('loading', 'lazy');
        }
        if (!$image->hasAttribute('decoding') && $image->getAttribute('loading') === 'lazy') {
            $image->setAttribute('decoding', 'async');
        }
        if ($image->hasAttribute('width') && $image->hasAttribute('height')) {
            return;
        }

        $src = parse_url($image->getAttribute('src'));
        if (!$src || isset($src['scheme']) || isset($src['host'])) {
            return;
        }
        $path = rawurldecode($src['path'] ?? '');
        if (!preg_match('~\.(?:avif|gif|jpe?g|png|webp)$~i', $path)
            || str_contains($path, "\0") || str_contains($path, '\\')) {
            return;
        }

        $directory = $page->path();
        if (str_starts_with($path, '/')) {
            if (!preg_match('~^/(images|user/(?:pages|images)|system/images/media)/~', $path, $groups)) {
                return;
            }
            $directory = GRAV_ROOT . '/' . $groups[1];
            $path = substr($path, strlen($groups[0]));
        }
        $base = $directory ? realpath($directory) : false;
        $file = $base ? realpath($base . '/' . $path) : false;
        if (!$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return;
        }
        $dimensions = @getimagesize($file);
        if (!$dimensions || $dimensions[0] < 1 || $dimensions[1] < 1) {
            return;
        }

        [$width, $height] = $dimensions;
        $givenWidth = $image->getAttribute('width');
        $givenHeight = $image->getAttribute('height');
        if (!$image->hasAttribute('width') && ($givenHeight === '' || (ctype_digit($givenHeight) && (int) $givenHeight > 0))) {
            $image->setAttribute('width', (string) ($givenHeight === '' ? $width : max(1, round($width * (int) $givenHeight / $height))));
        }
        if (!$image->hasAttribute('height') && ($givenWidth === '' || (ctype_digit($givenWidth) && (int) $givenWidth > 0))) {
            $image->setAttribute('height', (string) ($givenWidth === '' ? $height : max(1, round($height * (int) $givenWidth / $width))));
        }
    }
}
