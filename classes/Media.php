<?php

declare(strict_types=1);

namespace Grav\Theme\Denuo2;

use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Medium\MediumFactory;

/** Brand settings and image choice: logo and icons, local images, card and hero sources, avatars. */
final class Media
{
    /** @var array<string, string>|null */
    private ?array $brand = null;

    public function __construct(private Grav $grav)
    {
    }

    /**
     * Site name, logo, icons and fallback image from the Brand settings. The theme ships no logo
     * of its own: an empty logo shows the name only and empty icons are simply left out.
     * Image paths must be site-relative (/user/images/...) and exist.
     *
     * @return array{name: string, logo: string, touch_icon: string, publisher_logo: string, fallback_image: string}
     */
    public function brand(): array
    {
        if ($this->brand !== null) {
            return $this->brand;
        }
        $config = (array) $this->grav['config']->get('themes.denuo2.brand', []);
        $pick = function (string $key) use ($config): string {
            $value = trim((string) ($config[$key] ?? ''));
            if ($value !== '' && preg_match('#^/[A-Za-z0-9_./-]+$#', $value) && !str_contains($value, '..')
                && is_file(GRAV_ROOT . $value)) {
                return $this->grav['uri']->rootUrl() . $value;
            }
            return '';
        };
        return $this->brand = [
            'name' => trim((string) ($config['name'] ?? '')) ?: (string) $this->grav['config']->get('site.title', ''),
            'logo' => $pick('logo'),
            'touch_icon' => $pick('touch_icon'),
            'publisher_logo' => $pick('publisher_logo'),
            'fallback_image' => $pick('fallback_image'),
        ];
    }

    /**
     * URL and intrinsic size of an image under /user/images, for width/height attributes.
     *
     * @return array{url: string, width: int, height: int}|null
     */
    public function localImage(?string $path): ?array
    {
        $path = '/' . ltrim(trim((string) $path), '/');
        if (!preg_match('#^/user/images/[A-Za-z0-9_./-]+\.(avif|gif|jpe?g|png|webp)$#i', $path) || str_contains($path, '..')) {
            return null;
        }
        $file = GRAV_ROOT . $path;
        $size = is_file($file) ? @getimagesize($file) : false;
        if (!$size) {
            return null;
        }
        return ['url' => $this->grav['uri']->rootUrl() . $path, 'width' => (int) $size[0], 'height' => (int) $size[1]];
    }

    /**
     * Best source for a 16:9 card thumbnail among the article's designated images.
     * Many articles set preview_image_file to a 600x150 banner while aura.image is the
     * 1500px original, so pick the candidate with the tallest crop instead of the first.
     *
     * @param float $ratio crop ratio, 16/9 for cards, 16/7 for the article hero
     * @return array{image: mixed, max_width: int}|null max_width is the widest crop without upscaling
     */
    public function cardImage(PageInterface $page, float $ratio = 16 / 9): ?array
    {
        $header = $page->header();
        $images = $page->media()->images();
        if (!$images) {
            return null;
        }

        $summary = $header->image['summary'] ?? [];
        $names = [
            $header->seo['image'] ?? null,
            $header->aura['image'] ?? null,
            $header->preview_image_file ?? null,
            $header->hero_image_file_lg ?? null,
            $header->header_image_file ?? null,
            is_array($summary) && ($summary['enabled'] ?? true) !== false && is_string($summary['file'] ?? null) ? $summary['file'] : null,
        ];

        $best = null;
        $bestWidth = 0;
        foreach (array_unique(array_filter($names, 'is_string')) as $name) {
            $medium = $images[$name] ?? null;
            if (!$medium) {
                continue;
            }
            $width = (int) $medium->get('width');
            $height = (int) $medium->get('height');
            // Width of the largest crop of this ratio that fits inside the source.
            $cropWidth = (int) min($width, floor($height * $ratio));
            if ($cropWidth > $bestWidth) {
                $best = $medium;
                $bestWidth = $cropWidth;
            }
        }

        if (!$best) {
            $best = reset($images);
            $bestWidth = (int) min((int) $best->get('width'), floor((int) $best->get('height') * $ratio));
        }

        return ['image' => $best, 'max_width' => $bestWidth];
    }

    /** Square WebP avatar resized from an image under /user/images (author photos are often large PNGs). */
    public function avatar(?string $path, int $size = 96): ?string
    {
        $image = $this->localImage($path);
        if (!$image) {
            return null;
        }
        $medium = MediumFactory::fromFile(GRAV_ROOT . parse_url($image['url'], PHP_URL_PATH));
        if (!$medium || !method_exists($medium, 'cropZoom')) {
            return $image['url'];
        }
        return $medium->cropZoom($size, $size)->format('webp')->url();
    }
}
