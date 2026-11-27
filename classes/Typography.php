<?php

declare(strict_types=1);

namespace Grav\Theme\Denuo2;

use Grav\Common\Grav;

/**
 * Fonts from the Brand settings: a heading and a body font, each one of the bundled families or
 * the system font. Only the chosen families get @font-face rules and preload links.
 */
final class Typography
{
    /** Bundled families: label, CSS family name and one WOFF2 file per weight (latin + latin-ext). */
    public const FONTS = [
        'manrope' => [
            'label' => 'Manrope',
            'family' => "'Manrope'",
            'files' => [
                400 => 'manrope-v23-latin_latin-ext-regular.woff2',
                600 => 'manrope-v23-latin_latin-ext-600.woff2',
                700 => 'manrope-v23-latin_latin-ext-700.woff2',
                800 => 'manrope-v23-latin_latin-ext-800.woff2',
            ],
        ],
        'open-sans' => [
            'label' => 'Open Sans',
            'family' => "'Open Sans'",
            'files' => [
                400 => 'open-sans-v40-latin_latin-ext-regular.woff2',
                600 => 'open-sans-v44-latin_latin-ext-600.woff2',
                700 => 'open-sans-v40-latin_latin-ext-700.woff2',
                800 => 'open-sans-v44-latin_latin-ext-800.woff2',
            ],
        ],
        'system' => [
            'label' => 'System font',
            'family' => null,
            'files' => [],
        ],
    ];

    public const DEFAULT_HEADING = 'manrope';
    public const DEFAULT_BODY = 'open-sans';
    public const SYSTEM_STACK = "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";

    /** Weights that are above the fold on every page: body text and the page heading. */
    private const PRELOAD_BODY = 400;
    private const PRELOAD_HEADING = 800;

    public function __construct(private Grav $grav)
    {
    }

    /**
     * @return array{css: string, preload: list<string>, heading: string, body: string}
     */
    public function fonts(): array
    {
        $config = (array) $this->grav['config']->get('themes.denuo2.brand', []);
        $heading = $this->pick($config['font_heading'] ?? null, self::DEFAULT_HEADING);
        $body = $this->pick($config['font_body'] ?? null, self::DEFAULT_BODY);

        $css = '';
        foreach (array_unique([$body, $heading]) as $key) {
            foreach (self::FONTS[$key]['files'] as $weight => $file) {
                $url = $this->url($file);
                if ($url !== '') {
                    $css .= '@font-face{font-family:' . self::FONTS[$key]['family'] . ';font-weight:' . $weight
                        . ';font-display:swap;src:url("' . $url . '") format("woff2");}';
                }
            }
        }
        $css .= ':root{--font:' . $this->stack($body) . ';--font-heading:' . $this->stack($heading) . ';}';

        $preload = [];
        foreach ([[$body, self::PRELOAD_BODY], [$heading, self::PRELOAD_HEADING]] as [$key, $weight]) {
            $file = self::FONTS[$key]['files'][$weight] ?? null;
            if ($file !== null && ($url = $this->url($file)) !== '') {
                $preload[] = $url;
            }
        }

        return ['css' => $css, 'preload' => array_values(array_unique($preload)), 'heading' => $heading, 'body' => $body];
    }

    private function pick(mixed $value, string $default): string
    {
        $value = is_string($value) ? $value : '';
        return isset(self::FONTS[$value]) ? $value : $default;
    }

    private function stack(string $key): string
    {
        $family = self::FONTS[$key]['family'];
        return $family ? $family . ', ' . self::SYSTEM_STACK : self::SYSTEM_STACK;
    }

    private function url(string $file): string
    {
        $path = $this->grav['locator']->findResource('theme://fonts/' . $file, false);
        return is_string($path) ? rtrim($this->grav['uri']->rootUrl(), '/') . '/' . ltrim($path, '/') : '';
    }
}
