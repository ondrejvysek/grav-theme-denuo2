<?php

declare(strict_types=1);

namespace Grav\Theme\Denuo2;

use Grav\Common\Config\Config;

/**
 * Colour modes and tokens: validates the admin's base colours, prints them as CSS custom
 * properties (everything else is derived in CSS) and measures contrast.
 */
final class Colors
{
    /** Base colours an admin can set per mode; everything else is derived in CSS. */
    public const COLOR_KEYS = ['bg_start', 'bg_middle', 'bg_end', 'surface', 'text', 'primary', 'accent'];

    public const DEFAULT_COLORS = [
        'light' => [
            'bg_start' => '#E3EAFF', 'bg_middle' => '#F2ECFA', 'bg_end' => '#E8F2FB',
            'surface' => '#FFFFFF', 'text' => '#2B2D45', 'primary' => '#5B3FD9', 'accent' => '#D6E4FF',
        ],
        'dark' => [
            'bg_start' => '#08111F', 'bg_middle' => '#0C1A2E', 'bg_end' => '#0A1526',
            'surface' => '#112139', 'text' => '#E3ECF8', 'primary' => '#5AB0FF', 'accent' => '#1B3354',
        ],
        // Dusk: plum to wine background, warm surfaces, orange primary.
        'sunset' => [
            'bg_start' => '#24182A', 'bg_middle' => '#3B2034', 'bg_end' => '#4A2630',
            'surface' => '#33213A', 'text' => '#FCEAE2', 'primary' => '#FF9A66', 'accent' => '#5A2F45',
        ],
    ];

    /** Modes an admin can preset; 'auto' follows the visitor's system (light or dark). */
    public const MODES = ['light', 'dark', 'sunset', 'auto'];

    /** Modes a visitor can pick with the switcher, in switcher order. */
    public const SWITCHER_MODES = ['light', 'sunset', 'dark'];

    /** Cookie holding the visitor's choice; a functional preference the visitor sets explicitly. */
    public const MODE_COOKIE = 'denuo_mode';

    public const MIN_CONTRAST = 4.5;

    public function __construct(private Config $config)
    {
    }

    /**
     * Colour mode and the CSS custom properties built from the admin's base colours.
     * With the switcher enabled, a valid visitor choice from the cookie wins over the preset.
     *
     * @return array{mode: string, preset: string, switcher: bool, css: string, theme_color: array<string, string>}
     */
    public function tokens(): array
    {
        $preset = (string) $this->config->get('themes.denuo2.color_mode', 'light');
        if (!in_array($preset, self::MODES, true)) {
            $preset = 'light';
        }
        $switcher = (bool) $this->config->get('themes.denuo2.mode_switcher', false);
        $mode = $preset;
        if ($switcher) {
            $chosen = $_COOKIE[self::MODE_COOKIE] ?? null;
            if (is_string($chosen) && in_array($chosen, self::SWITCHER_MODES, true)) {
                $mode = $chosen;
            }
        }

        $light = $this->palette('light');
        $dark = $this->palette('dark');
        $sunset = $this->palette('sunset');
        $darkBlock = $this->declarations($dark, 'dark');

        $css = ':root{' . $this->declarations($light, 'light') . '}'
            . ':root[data-theme="dark"]{' . $darkBlock . '}'
            . ':root[data-theme="sunset"]{' . $this->declarations($sunset, 'sunset') . '}'
            . '@media (prefers-color-scheme: dark){:root[data-theme="auto"]{' . $darkBlock . '}}';

        return [
            'mode' => $mode,
            'preset' => $preset,
            'switcher' => $switcher,
            'css' => $css,
            'theme_color' => ['light' => $light['bg_start'], 'dark' => $dark['bg_start'], 'sunset' => $sunset['bg_start']],
        ];
    }

    /**
     * Colour pairs below the minimum contrast, for the given (unsaved) settings.
     *
     * @param array<string, array<string, string>> $colors base colours per mode, as submitted
     * @return list<array{mode: string, pair: string, ratio: float}>
     */
    public function contrastProblems(array $colors): array
    {
        $problems = [];
        foreach (array_keys(self::DEFAULT_COLORS) as $mode) {
            $palette = $this->palette($mode, (array) ($colors[$mode] ?? []));
            $pairs = [
                'text / background start' => [$palette['text'], $palette['bg_start']],
                'text / background middle' => [$palette['text'], $palette['bg_middle']],
                'text / background end' => [$palette['text'], $palette['bg_end']],
                'text / surface' => [$palette['text'], $palette['surface']],
                'primary / surface' => [$palette['primary'], $palette['surface']],
                'button text / primary' => [$this->onColor($palette['primary']), $palette['primary']],
            ];
            foreach ($pairs as $pair => [$foreground, $background]) {
                $ratio = self::contrast($foreground, $background);
                if ($ratio < self::MIN_CONTRAST) {
                    $problems[] = ['mode' => $mode, 'pair' => $pair, 'ratio' => $ratio];
                }
            }
        }
        return $problems;
    }

    /**
     * Validated base colours for one mode. Anything that is not #rrggbb falls back to the default,
     * so admin input can never inject CSS.
     *
     * @return array<string, string>
     */
    public function palette(string $mode, ?array $values = null): array
    {
        $values ??= (array) $this->config->get("themes.denuo2.colors.$mode", []);
        $palette = [];
        foreach (self::COLOR_KEYS as $key) {
            $value = trim((string) ($values[$key] ?? ''));
            if (preg_match('/^#[0-9a-f]{3}$/i', $value)) {
                $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
            }
            $palette[$key] = preg_match('/^#[0-9a-f]{6}$/i', $value)
                ? strtoupper($value)
                : self::DEFAULT_COLORS[$mode][$key];
        }
        return $palette;
    }

    private function declarations(array $palette, string $mode): string
    {
        $css = '';
        foreach ($palette as $key => $value) {
            $css .= '--c-' . str_replace('_', '-', $key) . ':' . $value . ';';
        }
        $css .= '--c-on-primary:' . $this->onColor($palette['primary']) . ';';
        // The pole is the extreme the derived tokens are pushed towards: black in light, white in
        // the dark-based modes (dark, sunset).
        $isDark = $mode !== 'light';
        $css .= '--c-pole:' . ($isDark ? '#FFFFFF' : '#000000') . ';';
        $css .= 'color-scheme:' . ($isDark ? 'dark' : 'light') . ';';
        return $css;
    }

    /** White or near-black, whichever reads better on the given colour. */
    public function onColor(string $background): string
    {
        $white = '#FFFFFF';
        $ink = '#14152A';
        return self::contrast($white, $background) >= self::contrast($ink, $background) ? $white : $ink;
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(
            static function (string $pair): float {
                $c = hexdec($pair) / 255;
                return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($hex, 1, 6), 2)
        );
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
