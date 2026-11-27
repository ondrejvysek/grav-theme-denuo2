<?php

declare(strict_types=1);

namespace Grav\Theme\Denuo2;

use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Plugin\D2Components\Renderer;
use Grav\Plugin\Pagination\PaginationHelper;

/**
 * Search and social metadata for every page, generated from a few inputs (in the spirit of the
 * Aura plugin): the page's `seo` block (title, description, image, image_alt, robots, canonical),
 * the SEO and Brand settings, the author profiles and the editorial dates. Older `aura:` and
 * `metadata:` values are read as fallbacks, so content written for Aura keeps working.
 *
 * Output: document title, meta description, robots, canonical, Open Graph, article:* and X tags,
 * and one schema.org graph (publisher, WebSite, WebPage, BreadcrumbList, BlogPosting, image).
 */
final class Seo
{
    private const DESCRIPTION_LENGTH = 155;
    private const HEADLINE_LENGTH = 110;
    private const SOCIAL = [1200, 630];
    /** Aspect ratios Google recommends for article images. */
    private const SCHEMA_RATIOS = ['16x9' => 16 / 9, '4x3' => 4 / 3, '1x1' => 1.0];
    private const LOCALES = ['cs' => 'cs_CZ', 'sk' => 'sk_SK', 'en' => 'en_US', 'de' => 'de_DE', 'pl' => 'pl_PL',
        'fr' => 'fr_FR', 'es' => 'es_ES', 'it' => 'it_IT', 'hu' => 'hu_HU', 'nl' => 'nl_NL', 'pt' => 'pt_PT'];
    private const ROBOTS = ['noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex'];

    public function __construct(
        private Grav $grav,
        private Media $media,
        private Site $site,
        private Authors $authors,
        private Dates $dates,
        private Content $content,
    ) {
    }

    /** Name used for search and social (WebSite, og:site_name, the title suffix). */
    public function siteName(): string
    {
        return trim((string) $this->grav['config']->get('themes.denuo2.seo.site_name', '')) ?: $this->media->brand()['name'];
    }

    public function metadata(PageInterface $page, ?Collection $collection = null): array
    {
        $config = $this->grav['config'];
        $header = $page->header();
        $seo = (array) ($header->seo ?? []);
        $aura = (array) ($header->aura ?? []);
        $metadata = (array) ($header->metadata ?? []);
        $template = $page->template();
        $article = in_array($template, ['blog_item', 'news_item'], true) && empty($header->content);
        $category = in_array($template, ['blog_list', 'news_list'], true);
        $error = $template === 'error';
        $siteName = $this->siteName();
        $tagline = trim((string) $config->get('site.title', ''));
        $language = $this->language($page);

        // Canonical URL; filtered listings and invalid pages are not indexed.
        $url = $page->url(true, true);
        $noindex = $error || $page->route() === '/_modules/socialshare';
        if ($category) {
            $uri = $this->grav['uri'];
            foreach ((array) $config->get('site.taxonomies') as $taxonomy) {
                $filter = $uri->params(rawurlencode((string) $taxonomy));
                if ($filter !== null) {
                    $url .= $filter;
                    $noindex = true;
                }
            }
            $number = $uri->currentPage();
            $pagination = $collection?->params()['pagination'] ?? null;
            if ($number > 1) {
                if ($pagination instanceof PaginationHelper && isset($pagination[$number])) {
                    $url .= $pagination[$number]->url;
                } else {
                    $noindex = true;
                }
            }
        }
        $robots = $this->robots((string) ($seo['robots'] ?? ''), $noindex);
        $indexable = !str_contains($robots, 'noindex');
        $canonical = $indexable ? ($this->absoluteUrl((string) ($seo['canonical'] ?? '')) ?: $url) : null;

        // Titles: "<title> | <site name>"; the home page (titled like the site) gets the tagline.
        $base = trim((string) ($seo['title'] ?? ''));
        if ($category) {
            $pattern = $this->categoryPattern();
            $base = $base ?: (trim((string) ($header->seo_title ?? '')) ?: str_replace('{title}', (string) $page->title(), $pattern));
        }
        $base = $base ?: trim((string) $page->title());
        $context = [];
        if ($category) {
            $tag = $this->grav['uri']->param('tag');
            if (is_string($tag) && $tag !== '') {
                $context[] = $this->site->translate('THEME_DENUO2.TITLE_TAG', rawurldecode($tag));
            }
            if ($this->grav['uri']->currentPage() > 1) {
                $context[] = $this->site->translate('THEME_DENUO2.TITLE_PAGE', $this->grav['uri']->currentPage());
            }
        }
        $suffix = mb_strtolower($base) === mb_strtolower($siteName) ? ($tagline !== $siteName ? $tagline : '') : $siteName;
        $title = $base . ($context ? ' – ' . implode(', ', $context) : '') . ($suffix !== '' ? ' | ' . $suffix : '');
        $socialTitle = $base . ($context ? ' – ' . implode(', ', $context) : '');

        $description = $this->description($page, $seo, $aura, $metadata, $article, $category);
        $image = $error ? null : $this->image($page, $seo, $aura, $metadata, $article, $category, $socialTitle);
        $author = $article ? $this->authors->author($header->author ?? null) : null;
        $dates = $article ? $this->dates->forPage($page) : null;
        $tags = $article ? array_values(array_filter(array_map('strval', (array) ($page->taxonomy()['tag'] ?? [])))) : [];
        $section = $article && $page->parent() ? trim((string) $page->parent()->menu()) : '';

        $result = [
            'title' => $title,
            'site_name' => $siteName,
            'description' => $description,
            'keywords' => (string) (($metadata['keywords'] ?? '') ?: ($aura['metadata']['keywords'] ?? '')),
            'canonical' => $canonical,
            'robots' => $robots,
            'noindex' => !$indexable,
            'locale' => self::LOCALES[$language] ?? str_replace('-', '_', $language),
            'social_url' => $canonical ?? $url,
            'social_title' => $socialTitle,
            'social_type' => $article ? 'article' : 'website',
            'image' => $image['social'] ?? null,
            'article' => $article ? [
                'published' => $dates['published'] ? date('c', $dates['published']) : null,
                'modified' => $dates['modified'] ? date('c', $dates['modified']) : null,
                'section' => $section,
                'tags' => $tags,
                'author' => $author['url'] ?? '',
            ] : null,
            'x_site' => $this->handle((string) $config->get('themes.denuo2.seo.x_handle', '')),
            'x_creator' => $author ? $this->handle($author['x'] ?? '') : '',
            'graph' => null,
        ];

        if ($indexable && !$error) {
            $result['graph'] = $this->graph($page, $result, $image, $author, $dates, $tags, $section, $language, $tagline, $category, $article);
        }
        return $result;
    }

    /**
     * Category title pattern from the translations (THEME_DENUO2.CATEGORY.TITLE_PATTERN), so each
     * language has its own; the theme setting of older installs is used while the translation is
     * the plain default.
     */
    private function categoryPattern(): string
    {
        $pattern = trim($this->site->translate('THEME_DENUO2.CATEGORY.TITLE_PATTERN'));
        if ($pattern === '' || $pattern === 'THEME_DENUO2.CATEGORY.TITLE_PATTERN' || $pattern === '{title}') {
            $pattern = trim((string) $this->grav['config']->get('themes.denuo2.category_title_pattern', '')) ?: '{title}';
        }
        return $pattern;
    }

    /** seo → Aura → metadata → category intro → excerpt of the text → home hero → site description. */
    private function description(PageInterface $page, array $seo, array $aura, array $metadata, bool $article, bool $category): string
    {
        $header = $page->header();
        $design = (array) ($header->design ?? []);
        $text = trim((string) ($seo['description'] ?? ''))
            ?: trim((string) ($aura['description'] ?? ''))
            ?: trim((string) ($metadata['description'] ?? ''))
            ?: trim((string) ($metadata['og:description'] ?? ''))
            ?: ($category ? trim((string) (($header->intro ?? '') ?: ($design['text-lead'] ?? ''))) : '');
        if ($text === '' && !$category && !$page->home()) {
            $text = $this->excerpt($page);
        }
        if ($text === '' && $page->home()) {
            $text = trim((string) ($this->content->hero()['lead'] ?? ''));
        }
        $text = $text ?: trim((string) $this->grav['config']->get('site.metadata.description', ''));
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    }

    private function excerpt(PageInterface $page): string
    {
        $summary = $page->header()->summary ?? null;
        if (class_exists(Renderer::class)) {
            return Renderer::excerpt(is_string($summary) ? $summary : null, static fn () => $page->rawMarkdown(), self::DESCRIPTION_LENGTH);
        }
        return Fallbacks::summary($page, self::DESCRIPTION_LENGTH);
    }

    /**
     * The page's main image: seo.image → aura.image → legacy og:image → the theme's card choice →
     * category illustration → site fallback. Page images are cropped (never upscaled) to 1200×630
     * JPEG for social cards and to 16:9, 4:3 and 1:1 WebP for schema.org.
     *
     * @return array{social: array, schema: list<array>}|null
     */
    private function image(PageInterface $page, array $seo, array $aura, array $metadata, bool $article, bool $category, string $title): ?array
    {
        $alt = trim((string) ($seo['image_alt'] ?? '')) ?: $title;
        $config = $this->grav['config'];

        if ($page->home() && $config->get('themes.denuo2.home.hero.enabled')) {
            $heroSettings = $this->content->hero();
            $hero = $this->media->localImage((string) ($heroSettings['image'] ?? ''));
            if ($hero) {
                $alt = trim((string) ($heroSettings['image_alt'] ?? '')) ?: $alt;
                return $this->staticImage($hero, $alt);
            }
        }

        $images = $page->media()->images();
        $medium = null;
        foreach ([$seo['image'] ?? null, $aura['image'] ?? null, $this->legacyImageName($metadata, $images)] as $name) {
            if (is_string($name) && isset($images[$name])) {
                $medium = $images[$name];
                break;
            }
        }
        if (!$medium && ($article || $images)) {
            $medium = $this->media->cardImage($page, self::SOCIAL[0] / self::SOCIAL[1])['image'] ?? null;
        }
        if ($medium && (int) $medium->get('width') > 0) {
            return $this->croppedImage($medium, $alt);
        }

        $design = (array) ($page->header()->design ?? []);
        $fallback = $category ? ($design['image'] ?? '') : '';
        if (!$fallback && $article) {
            $fallback = (string) (($page->parent()?->header()->design['image'] ?? '') ?: '');
        }
        $local = $this->media->localImage($fallback ?: (string) $this->media->brand()['fallback_image']);
        if (!$local && $fallback) {
            $local = $this->media->localImage((string) $this->media->brand()['fallback_image']);
        }
        return $local ? $this->staticImage($local, $alt) : null;
    }

    /** Older content stored og:image as a URL; match it back to a page image (also old cache names). */
    private function legacyImageName(array $metadata, array $images): ?string
    {
        $path = parse_url((string) ($metadata['og:image'] ?? ''), PHP_URL_PATH) ?: '';
        if ($path === '') {
            return null;
        }
        $filename = (string) preg_replace('/^[a-f0-9]{40}-/', '', basename(rawurldecode($path)));
        if (isset($images[$filename])) {
            return $filename;
        }
        $loose = static fn (string $name): string => (string) preg_replace('/[^a-z0-9.]+/', '-', mb_strtolower($name));
        foreach (array_keys($images) as $name) {
            if ($loose((string) $name) === $loose($filename)) {
                return (string) $name;
            }
        }
        return null;
    }

    private function croppedImage(object $medium, string $alt): array
    {
        $width = (int) $medium->get('width');
        $height = (int) $medium->get('height');
        $crop = function (float $ratio, int $max, string $format) use ($medium, $width, $height): ?array {
            $w = (int) min($max, $width, floor($height * $ratio));
            $h = (int) round($w / $ratio);
            if ($w < 200 || $h < 100) {
                return null;
            }
            $url = $medium->cropZoom($w, $h)->format($format)->quality(85)->url();
            return ['url' => $this->grav['uri']->base() . $url, 'width' => $w, 'height' => $h,
                'type' => $format === 'jpg' ? 'image/jpeg' : 'image/webp'];
        };

        $social = $crop(self::SOCIAL[0] / self::SOCIAL[1], self::SOCIAL[0], 'jpg');
        if (!$social) {
            $social = ['url' => $this->grav['uri']->base() . $medium->url(), 'width' => $width, 'height' => $height,
                'type' => (string) ($medium->get('mime') ?: 'image/jpeg')];
        }
        $social['alt'] = $alt;

        $schema = [];
        foreach (self::SCHEMA_RATIOS as $ratio) {
            $image = $crop($ratio, 1200, 'webp');
            if ($image && $image['width'] * $image['height'] >= 50000) {
                $schema[] = $image;
            }
        }
        return ['social' => $social, 'schema' => $schema ?: [$social]];
    }

    /** @param array{url: string, width: int, height: int} $image site-relative URL from Media::localImage() */
    private function staticImage(array $image, string $alt): array
    {
        $extension = strtolower(pathinfo(parse_url($image['url'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'avif' => 'image/avif'];
        $social = ['url' => $this->grav['uri']->base() . $image['url'], 'width' => $image['width'], 'height' => $image['height'],
            'type' => $types[$extension] ?? 'image/jpeg', 'alt' => $alt];
        return ['social' => $social, 'schema' => [$social]];
    }

    private function graph(PageInterface $page, array $seo, ?array $image, ?array $author, ?array $dates, array $tags,
        string $section, string $language, string $tagline, bool $category, bool $article): array
    {
        $config = $this->grav['config'];
        $home = rtrim($this->grav['uri']->rootUrl(true), '/') . '/';
        $url = (string) $seo['canonical'];
        $ids = ['publisher' => $home . '#publisher', 'website' => $home . '#website', 'webpage' => $url . '#webpage',
            'breadcrumb' => $url . '#breadcrumb', 'image' => $url . '#primaryimage', 'article' => $url . '#article'];

        $publisherName = trim((string) $config->get('themes.denuo2.seo.publisher_name', '')) ?: $seo['site_name'];
        $person = $config->get('themes.denuo2.seo.publisher_type') === 'person';
        $logo = $this->media->localImage((string) $config->get('themes.denuo2.brand.publisher_logo', ''));
        $publisher = array_filter([
            '@type' => $person ? 'Person' : 'Organization',
            '@id' => $ids['publisher'],
            'name' => $publisherName,
            'url' => $home,
            $person ? 'image' : 'logo' => $logo ? [
                '@type' => 'ImageObject', 'url' => $this->grav['uri']->base() . $logo['url'],
                'width' => $logo['width'], 'height' => $logo['height'], 'caption' => $publisherName,
            ] : null,
            'sameAs' => $this->urls((array) $config->get('themes.denuo2.seo.same_as', [])) ?: null,
        ]);

        $graph = [$publisher, array_filter([
            '@type' => 'WebSite',
            '@id' => $ids['website'],
            'url' => $home,
            'name' => $seo['site_name'],
            'alternateName' => $tagline !== '' && $tagline !== $seo['site_name'] ? $tagline : null,
            'inLanguage' => $language,
            'publisher' => ['@id' => $ids['publisher']],
        ])];

        if ($image) {
            $primary = $image['schema'][0];
            $graph[] = ['@type' => 'ImageObject', '@id' => $ids['image'], 'url' => $primary['url'], 'contentUrl' => $primary['url'],
                'width' => $primary['width'], 'height' => $primary['height'], 'caption' => $image['social']['alt'], 'inLanguage' => $language];
        }

        $crumbs = $page->home() ? [] : $this->breadcrumbs($page, $home);
        $graph[] = array_filter([
            '@type' => $category ? 'CollectionPage' : 'WebPage',
            '@id' => $ids['webpage'],
            'url' => $url,
            'name' => $seo['title'],
            'description' => $seo['description'] ?: null,
            'isPartOf' => ['@id' => $ids['website']],
            'inLanguage' => $language,
            'primaryImageOfPage' => $image ? ['@id' => $ids['image']] : null,
            'breadcrumb' => $crumbs ? ['@id' => $ids['breadcrumb']] : null,
            'datePublished' => $seo['article']['published'] ?? null,
            'dateModified' => ($seo['article']['modified'] ?? null) ?: ($seo['article']['published'] ?? null),
        ], static fn ($value) => $value !== null && $value !== '');

        if ($crumbs) {
            $graph[] = ['@type' => 'BreadcrumbList', '@id' => $ids['breadcrumb'], 'itemListElement' => $crumbs];
        }

        if ($article) {
            $authorNode = $author && $author['name'] !== '' ? array_filter([
                '@type' => 'Person',
                '@id' => $home . '#/schema/person/' . substr(sha1(mb_strtolower($author['name'])), 0, 12),
                'name' => $author['name'],
                'jobTitle' => $author['title'] ?: null,
                'description' => $author['bio'] ? strtok($author['bio'], "\n") : null,
                'image' => ($avatar = $this->media->localImage($author['avatar'])) ? $this->grav['uri']->base() . $avatar['url'] : null,
                'url' => $author['url'] ?: null,
                'sameAs' => $author['same_as'] ?: null,
            ]) : ['@id' => $ids['publisher']];
            $headline = trim((string) $page->title());
            if (mb_strlen($headline) > self::HEADLINE_LENGTH) {
                $headline = rtrim(mb_substr($headline, 0, self::HEADLINE_LENGTH - 1)) . '…';
            }
            $graph[] = array_filter([
                '@type' => 'BlogPosting',
                '@id' => $ids['article'],
                'isPartOf' => ['@id' => $ids['webpage']],
                'mainEntityOfPage' => ['@id' => $ids['webpage']],
                'headline' => $headline,
                'description' => $seo['description'] ?: null,
                'image' => $image ? array_map(static fn (array $i): string => $i['url'], $image['schema']) : null,
                'datePublished' => $seo['article']['published'],
                'dateModified' => $seo['article']['modified'] ?: $seo['article']['published'],
                'author' => $authorNode,
                'publisher' => ['@id' => $ids['publisher']],
                'inLanguage' => $language,
                'articleSection' => $section ?: null,
                'keywords' => $tags ?: null,
            ], static fn ($value) => $value !== null && $value !== '');
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /** Home → visible, routable ancestors → the page; the same trail as the visible breadcrumbs. */
    private function breadcrumbs(PageInterface $page, string $home): array
    {
        $trail = [];
        for ($ancestor = $page->parent(), $i = 0; $ancestor && $i < 6; $ancestor = $ancestor->parent(), $i++) {
            if ($ancestor->root() || $ancestor->home()) {
                break;
            }
            if ($ancestor->routable() && $ancestor->visible()) {
                array_unshift($trail, ['name' => trim((string) $ancestor->menu()), 'item' => $ancestor->url(true, true)]);
            }
        }
        array_unshift($trail, ['name' => $this->site->translate('THEME_DENUO2.NAV.HOME_CRUMB'), 'item' => $home]);
        $trail[] = ['name' => trim((string) $page->menu()), 'item' => $page->url(true, true)];
        $items = [];
        foreach ($trail as $index => $crumb) {
            $items[] = ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $crumb['name'], 'item' => $crumb['item']];
        }
        return $items;
    }

    private function robots(string $value, bool $noindex): string
    {
        $tokens = array_values(array_intersect(self::ROBOTS, preg_split('/[\s,]+/', strtolower($value)) ?: []));
        if ($noindex && !in_array('noindex', $tokens, true)) {
            $tokens[] = 'noindex';
        }
        if (in_array('noindex', $tokens, true)) {
            return implode(', ', array_merge($tokens, in_array('nofollow', $tokens, true) ? [] : ['follow']));
        }
        $tokens = array_merge(['index'], $tokens, in_array('nofollow', $tokens, true) ? [] : ['follow']);
        if ($this->grav['config']->get('themes.denuo2.seo.large_image_preview', true) && !in_array('nosnippet', $tokens, true)) {
            $tokens[] = 'max-image-preview:large';
        }
        return implode(', ', $tokens);
    }

    private function language(PageInterface $page): string
    {
        return (string) ($page->language() ?: $this->grav['language']->getActive() ?: $this->grav['config']->get('site.default_lang') ?: 'en');
    }

    private function absoluteUrl(string $value): string
    {
        $value = trim($value);
        return preg_match('~^https?://[^\s"<>]+$~i', $value) ? $value : '';
    }

    /** @return list<string> */
    private function urls(array $values): array
    {
        return array_values(array_filter(array_map(fn ($value): string => $this->absoluteUrl((string) $value), $values)));
    }

    private function handle(string $value): string
    {
        $value = ltrim(trim($value), '@');
        return preg_match('/^[A-Za-z0-9_]{1,15}$/', $value) ? '@' . $value : '';
    }
}
