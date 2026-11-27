<?php

declare(strict_types=1);

namespace Grav\Theme\Denuo2;

use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Utils;

/**
 * Editorial dates from front matter: published (`date`, else `publish_date`), updated
 * (`modified_date`) and the sitemap override (`sitemap.lastmod`). File times are never used:
 * they change on every upload.
 */
final class Dates
{
    public function __construct(private Grav $grav)
    {
    }

    /** @return array{published: ?int, modified: ?int, sitemap_override: ?int} */
    public function forPage(PageInterface $page): array
    {
        $header = $page->header();
        $format = $page->dateformat()
            ?: $this->grav['config']->get('system.pages.dateformat.default')
            ?: 'd-m-Y H:i';
        return [
            'published' => self::timestamp($header->date ?? null, $format)
                ?? self::timestamp($header->publish_date ?? null, $format),
            'modified' => self::timestamp($header->modified_date ?? null, $format),
            'sitemap_override' => self::timestamp($header->sitemap['lastmod'] ?? null, $format),
        ];
    }

    private static function timestamp(mixed $value, string $format): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        if (is_int($value)) {
            return $value > 0 ? (Utils::date2timestamp((string) $value, $format) ?: null) : null;
        }
        if (!is_string($value) || trim($value) === '' || strlen($value) > 100) {
            return null;
        }
        $value = trim($value);
        $parsed = date_parse_from_format('!' . $format, $value);
        if ($parsed['error_count'] || $parsed['warning_count']) {
            $parsed = date_parse($value);
        }
        if ($parsed['error_count'] || $parsed['warning_count'] || isset($parsed['relative'])
            || !$parsed['year'] || !$parsed['month'] || !$parsed['day']) {
            return null;
        }
        return Utils::date2timestamp($value, $format) ?: null;
    }
}
