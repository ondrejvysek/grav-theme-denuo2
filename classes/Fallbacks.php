<?php

declare(strict_types=1);

namespace Grav\Theme\Denuo2;

use Grav\Common\Config\Config;
use Grav\Common\Page\Interfaces\PageInterface;
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Content components (callouts, lead blocks, badges) and smart excerpts come from the optional
 * d2-components plugin. Without it, register plain stand-ins so the theme works on any Grav site.
 */
final class Fallbacks
{
    public static function register(Environment $twig, Config $config): void
    {
        if (!$config->get('plugins.d2-components.enabled')) {
            $twig->addFilter(new TwigFilter('render_components', static fn ($html): string => (string) $html, ['is_safe' => ['html']]));
            $twig->addFunction(new TwigFunction('article_summary', [self::class, 'summary']));
        }
    }

    /** Excerpt from the description or Grav's summary, cut at a word boundary. */
    public static function summary(PageInterface $page, int $length): string
    {
        $header = $page->header();
        $text = (string) (($header->seo['description'] ?? null) ?: ($header->metadata['description'] ?? null)
            ?: ($header->aura['description'] ?? null) ?: $page->summary());
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length - 1);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space ? mb_substr($cut, 0, $space) : $cut) . '…';
    }
}
