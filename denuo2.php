<?php

declare(strict_types=1);

namespace Grav\Theme;

use Grav\Common\Theme;
use Grav\Theme\Denuo2\Articles;
use Grav\Theme\Denuo2\Authors;
use Grav\Theme\Denuo2\Colors;
use Grav\Theme\Denuo2\Content;
use Grav\Theme\Denuo2\Dates;
use Grav\Theme\Denuo2\Fallbacks;
use Grav\Theme\Denuo2\Media;
use Grav\Theme\Denuo2\Outline;
use Grav\Theme\Denuo2\Seo;
use Grav\Theme\Denuo2\Site;
use Grav\Theme\Denuo2\Typography;
use RocketTheme\Toolbox\Event\Event;
use Twig\TwigFilter;
use Twig\TwigFunction;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Grav\\Theme\\Denuo2\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/classes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

/**
 * Denuo2 theme: wires Grav events and Twig functions to the helpers in classes/.
 *
 *   Colors    colour modes, CSS tokens, contrast
 *   Media     brand, local images, card/hero image choice, avatars
 *   Seo       titles, descriptions, canonical, robots, social image
 *   Articles  reading time, chips, related, previous/next, TOC
 *   Site      links, dates, translations, privacy settings
 *   Fallbacks stand-ins for optional plugins
 */
class Denuo2 extends Theme
{
    private ?Colors $colors = null;
    private ?Media $media = null;
    private ?Site $site = null;
    private ?Seo $seo = null;
    private ?Articles $articles = null;
    private ?Typography $typography = null;
    private ?Authors $authors = null;
    private ?Dates $dates = null;
    private ?Outline $outline = null;
    private ?Content $content = null;

    public static function getSubscribedEvents(): array
    {
        return [
            'onAdminPageTypes' => ['labelPageTypes', 0],
            'onTwigExtensions' => ['registerTwigFunctions', 0],
            'onAdminSave' => ['checkContrast', 0],
        ];
    }

    public function labelPageTypes(Event $event): void
    {
        $labels = [
            'blog_item' => 'Article',
            'blog_list' => 'Category listing',
            'default' => 'Standard page',
            'modular' => 'Modular page',
            'footer' => 'Footer content',
        ];

        $types = $event['types'];
        $event['types'] = array_replace($types, array_intersect_key($labels, $types));
    }

    public function registerTwigFunctions(): void
    {
        $twig = $this->grav['twig']->twig();
        $functions = [
            'denuo_tokens' => [$this->colors(), 'tokens'],
            'denuo_fonts' => [$this->typography(), 'fonts'],
            'denuo_metadata' => [$this->seo(), 'metadata'],
            'denuo_brand' => [$this->media(), 'brand'],
            'denuo_local_image' => [$this->media(), 'localImage'],
            'denuo_card_image' => [$this->media(), 'cardImage'],
            'denuo_avatar' => [$this->media(), 'avatar'],
            'denuo_reading_time' => [$this->articles(), 'readingTime'],
            'denuo_chips' => [$this->articles(), 'chips'],
            'denuo_active_tag' => [$this->articles(), 'activeTag'],
            'denuo_related' => [$this->articles(), 'related'],
            'denuo_adjacent' => [$this->articles(), 'adjacent'],
            'denuo_toc' => [$this->articles(), 'toc'],
            'denuo_link' => [$this->site(), 'link'],
            'denuo_date' => [$this->site(), 'date'],
            'denuo_privacy' => [$this->site(), 'privacy'],
            'denuo_author' => [$this->authors(), 'author'],
            'denuo_dates' => [$this->dates(), 'forPage'],
            'denuo_hero' => [$this->content(), 'hero'],
            'denuo_footer' => [$this->content(), 'footer'],
        ];
        foreach ($functions as $name => $callable) {
            $twig->addFunction(new TwigFunction($name, $callable));
        }
        $twig->addFilter(new TwigFilter('denuo_outline', [$this->outline(), 'build']));
        Fallbacks::register($twig, $this->config);
    }

    /** Warn in admin when the saved base colours make text or buttons hard to read. */
    public function checkContrast(Event $event): void
    {
        $object = $event['object'] ?? null;
        if (!is_object($object) || !method_exists($object, 'get')) {
            return;
        }
        $colors = $object->get('colors');
        if (!is_array($colors) || $object->get('color_mode') === null) {
            return;
        }

        $problems = array_map(
            static fn (array $p): string => sprintf('%s mode, %s: %.1f:1', $p['mode'], $p['pair'], $p['ratio']),
            $this->colors()->contrastProblems($colors)
        );
        if ($problems) {
            $this->grav['messages']->add(
                'Denuo2: some colours are below 4.5:1 contrast – ' . implode('; ', $problems) . '.',
                'warning'
            );
        }
    }

    private function colors(): Colors
    {
        return $this->colors ??= new Colors($this->config);
    }

    private function media(): Media
    {
        return $this->media ??= new Media($this->grav);
    }

    private function site(): Site
    {
        return $this->site ??= new Site($this->grav);
    }

    private function seo(): Seo
    {
        return $this->seo ??= new Seo($this->grav, $this->media(), $this->site(), $this->authors(), $this->dates(), $this->content());
    }

    private function articles(): Articles
    {
        return $this->articles ??= new Articles($this->grav);
    }

    private function typography(): Typography
    {
        return $this->typography ??= new Typography($this->grav);
    }

    private function authors(): Authors
    {
        return $this->authors ??= new Authors($this->grav);
    }

    private function dates(): Dates
    {
        return $this->dates ??= new Dates($this->grav);
    }

    private function content(): Content
    {
        return $this->content ??= new Content($this->grav);
    }

    private function outline(): Outline
    {
        return $this->outline ??= new Outline($this->grav);
    }
}
