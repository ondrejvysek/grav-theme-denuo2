<?php

declare(strict_types=1);

namespace Grav\Theme\Denuo2;

use Grav\Common\Grav;

/** Site-wide helpers: links from settings, dates, translations and the privacy settings. */
final class Site
{
    public function __construct(private Grav $grav)
    {
    }

    /**
     * Resolve a link from theme settings: "#anchor" and full http(s) addresses pass through,
     * a page route such as "/blog" becomes that page's URL (with the site's URL extension).
     */
    public function link(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '#') || preg_match('~^(https?:|mailto:)~i', $value)) {
            return $value;
        }
        if (str_starts_with($value, '/')) {
            $page = $this->grav['pages']->find(rtrim($value, '/') ?: '/');
            if ($page) {
                return $page->url();
            }
            return $this->grav['uri']->rootUrl() . $value;
        }
        return '';
    }

    /** Short date for meta lines in the active language (THEME_DENUO2.DATE_FORMAT, PHP date()). */
    public function date(mixed $value): string
    {
        $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if (!$timestamp) {
            return '';
        }
        $format = $this->translate('THEME_DENUO2.DATE_FORMAT');
        if ($format === 'THEME_DENUO2.DATE_FORMAT') {
            $format = 'j M Y';
        }
        // Spaces in dates never break across lines.
        return str_replace(' ', "\u{00A0}", date($format, $timestamp));
    }

    /** Translate a THEME_DENUO2.* key for the active language, with optional sprintf arguments. */
    public function translate(string $key, mixed ...$args): string
    {
        return (string) $this->grav['language']->translate(array_merge([$key], $args));
    }

    /**
     * Analytics and consent settings for the templates. The GA4 ID is validated so admin input
     * never reaches the inline script raw; the banner is only needed when a tracker is set.
     * Banner texts come from the translations (THEME_DENUO2.CONSENT), so every language has its
     * own; override them per language in user/languages/<lang>.yaml.
     *
     * @return array{ga4: ?string, banner: bool, policy_url: string, title: string, message: string, accept: string, reject: string, settings: string}
     */
    public function privacy(): array
    {
        $config = (array) $this->grav['config']->get('themes.denuo2.privacy', []);
        $ga4 = strtoupper(trim((string) ($config['ga4_id'] ?? '')));
        $ga4 = preg_match('/^G-[A-Z0-9]{4,20}$/', $ga4) ? $ga4 : null;
        $text = fn (string $key, string $translation): string => $this->translate('THEME_DENUO2.CONSENT.' . $translation);

        return [
            'ga4' => $ga4,
            'banner' => $ga4 !== null && ($config['consent_enabled'] ?? true) !== false,
            'policy_url' => $this->link((string) ($config['policy_url'] ?? '')),
            'title' => $text('title', 'TITLE'),
            'message' => $text('message', 'MESSAGE'),
            'accept' => $text('accept_label', 'ACCEPT'),
            'reject' => $text('reject_label', 'REJECT'),
            'settings' => $text('settings_label', 'SETTINGS'),
        ];
    }
}
