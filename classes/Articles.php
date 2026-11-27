<?php

declare(strict_types=1);

namespace Grav\Theme\Denuo2;

use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;

/** Article helpers: reading time, filter chips, related articles, previous/next and the TOC. */
final class Articles
{
    /** @var array<string, int|null> */
    private array $readingTimes = [];

    public function __construct(private Grav $grav)
    {
    }

    /**
     * Minutes to read: the article's readingtime field, otherwise estimated from the raw markdown
     * (about 170 words a minute, calibrated against articles that set the field).
     */
    public function readingTime(PageInterface $page): ?int
    {
        $key = $page->path() ?? $page->route();
        if (array_key_exists($key, $this->readingTimes)) {
            return $this->readingTimes[$key];
        }

        $declared = $page->header()->readingtime ?? null;
        if (is_numeric($declared) && (int) $declared > 0) {
            return $this->readingTimes[$key] = (int) $declared;
        }

        $markdown = (string) $page->rawMarkdown();
        // Drop code fences, shortcodes, images, link targets and HTML before counting words.
        $text = preg_replace(
            ['~^ {0,3}(`{3,}|\~{3,})[^\n]*\n.*?^ {0,3}\1[ \t]*$~ms', '~\[/?[A-Za-z][^\]]*\]~', '~!\[[^\]]*\]\([^)]*\)~', '~\]\([^)]*\)~', '~<[^>]+>~'],
            ' ',
            $markdown
        ) ?? '';
        $words = preg_match_all('~[\p{L}\p{N}]+~u', $text);
        if (!$words) {
            return $this->readingTimes[$key] = null;
        }
        return $this->readingTimes[$key] = max(1, (int) ceil($words / 170));
    }

    /** The tag filter from the URL (/blog.html/tag:Name), decoded, or null. */
    public function activeTag(): ?string
    {
        $tag = $this->grav['uri']->param('tag');
        return is_string($tag) && trim($tag) !== '' ? trim(rawurldecode($tag)) : null;
    }

    /**
     * Filter chips for a category: the page's own `chips` list if set, otherwise the most used
     * tags among its published articles.
     *
     * @return list<string>
     */
    public function chips(PageInterface $page, int $limit = 5): array
    {
        $declared = array_values(array_filter(array_map('trim', (array) ($page->header()->chips ?? [])), 'strlen'));
        if ($declared) {
            return $declared;
        }

        // Never offered automatically: tags in the theme's exclude list (a series the owner does
        // not want promoted) and tags that just repeat the category name.
        $exclude = array_map(
            'mb_strtolower',
            array_merge(
                (array) $this->grav['config']->get('themes.denuo2.category_chip_exclude', []),
                [(string) $page->title(), (string) $page->menu()]
            )
        );

        $counts = [];
        foreach ($page->children()->published()->routable() as $child) {
            foreach ((array) ($child->taxonomy()['tag'] ?? []) as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '' && !in_array(mb_strtolower($tag), $exclude, true)) {
                    $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                }
            }
        }
        // A tag on a single article is not a useful filter.
        $counts = array_filter($counts, static fn (int $count): bool => $count > 1);
        uksort($counts, static fn (string $a, string $b): int => [$counts[$b], $a] <=> [$counts[$a], $b]);
        return array_slice(array_keys($counts), 0, $limit);
    }

    /**
     * Related articles: the most shared tags first, then same category, newest first.
     * Articles from excluded series (theme setting category_chip_exclude) are left out
     * unless the current article belongs to that series too.
     *
     * @return list<PageInterface>
     */
    public function related(PageInterface $page, int $limit = 3): array
    {
        $tags = array_values(array_filter(array_map('trim', (array) ($page->taxonomy()['tag'] ?? [])), 'strlen'));
        $own = array_map('mb_strtolower', $tags);
        $excluded = array_diff(
            array_map('mb_strtolower', (array) $this->grav['config']->get('themes.denuo2.category_chip_exclude', [])),
            $own
        );
        $parent = $page->parent();

        $candidates = [];
        if ($tags) {
            foreach ($this->grav['taxonomy']->findTaxonomy(['tag' => $tags], 'or') as $candidate) {
                $candidates[$candidate->path()] = $candidate;
            }
        }
        if ($parent) {
            foreach ($parent->children()->published()->routable() as $candidate) {
                $candidates[$candidate->path()] = $candidate;
            }
        }

        $scored = [];
        foreach ($candidates as $candidate) {
            if ($candidate->path() === $page->path() || !$this->isPublicArticle($candidate)) {
                continue;
            }
            $candidateTags = array_map(static fn ($tag): string => mb_strtolower(trim((string) $tag)), (array) ($candidate->taxonomy()['tag'] ?? []));
            if (array_intersect($candidateTags, $excluded)) {
                continue;
            }
            $score = 2 * count(array_intersect($candidateTags, $own))
                + ($parent && $candidate->parent()?->path() === $parent->path() ? 1 : 0);
            $scored[] = [$score, (int) $candidate->date(), $candidate];
        }
        usort($scored, static fn (array $a, array $b): int => [$b[0], $b[1]] <=> [$a[0], $a[1]]);

        return array_map(static fn (array $row) => $row[2], array_slice($scored, 0, $limit));
    }

    /**
     * Previous and next article by date within the same category, so the links follow the
     * listing order instead of folder names.
     *
     * @return array{newer: ?PageInterface, older: ?PageInterface}
     */
    public function adjacent(PageInterface $page): array
    {
        $result = ['newer' => null, 'older' => null];
        $parent = $page->parent();
        if (!$parent) {
            return $result;
        }
        $siblings = array_values(array_filter(
            iterator_to_array($parent->children()->published()->routable()),
            fn ($sibling): bool => $this->isPublicArticle($sibling)
        ));
        usort($siblings, static fn ($a, $b): int => [(int) $b->date(), $b->path()] <=> [(int) $a->date(), $a->path()]);
        foreach ($siblings as $index => $sibling) {
            if ($sibling->path() === $page->path()) {
                $result['newer'] = $siblings[$index - 1] ?? null;
                $result['older'] = $siblings[$index + 1] ?? null;
                break;
            }
        }
        return $result;
    }

    /**
     * Table of contents for the sidebar: H2 sections only, without headings inside the lead
     * block, with labels cut at ": " ("Governance: who decides…" → "Governance").
     *
     * @param array<int, array{id: string, label: string, level: int}> $headings
     * @return list<array{id: string, label: string, full: string}>
     */
    public function toc(array $headings, string $html): array
    {
        $leadIds = [];
        if (preg_match_all('~<section[^>]*class="[^"]*\b(?:lead-block|d2-lead)\b.*?</section>~s', $html, $sections)) {
            foreach ($sections[0] as $section) {
                preg_match_all('~\bid="([^"]+)"~', $section, $ids);
                $leadIds = array_merge($leadIds, $ids[1]);
            }
        }

        $items = [];
        foreach ($headings as $heading) {
            if ((int) ($heading['level'] ?? 0) !== 2 || in_array($heading['id'], $leadIds, true)) {
                continue;
            }
            $full = (string) $heading['label'];
            $label = $full;
            $colon = mb_strpos($full, ': ');
            if ($colon !== false && $colon >= 3 && $colon <= 40) {
                $label = mb_substr($full, 0, $colon);
            }
            $items[] = ['id' => (string) $heading['id'], 'label' => $label, 'full' => $full];
        }
        return $items;
    }

    private function isPublicArticle(PageInterface $page): bool
    {
        $header = $page->header();
        $route = (string) $page->route();
        $language = $this->grav['language']->getActive();
        return $page->template() === 'blog_item'
            && $page->published()
            && $page->routable()
            && !$page->isModule()
            && empty($header->access)
            && !str_starts_with($route, '/_modules/')
            && !str_starts_with($route, '/home/')
            && (!$language || !$page->language() || $page->language() === $language);
    }
}
