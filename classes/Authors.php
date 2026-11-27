<?php

declare(strict_types=1);

namespace Grav\Theme\Denuo2;

use Grav\Common\Grav;

/**
 * Public author profiles from the theme settings (Authors tab), matched on the page's `author`
 * front matter. Profiles of the former author-info plugin are still read when the theme has none.
 */
final class Authors
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $profiles = null;

    public function __construct(private Grav $grav)
    {
    }

    /**
     * @return array{name: string, title: string, badge: string, bio: string, avatar: string, url: string, same_as: list<string>, x: string}
     */
    public function author(mixed $name = null): array
    {
        $name = trim(is_string($name) ? $name : '');
        $profile = $name !== '' ? ($this->profiles()[mb_strtolower($name)] ?? []) : [];
        $avatar = $profile['avatar'] ?? '';
        if (!is_string($avatar)
            || !preg_match('~^/user/images/(?:[\w-]+/)*[\w.-]+\.(?:avif|gif|jpe?g|png|webp)$~iD', $avatar)
            || !is_file(GRAV_ROOT . $avatar)) {
            $avatar = '';
        }
        return [
            'name' => $name,
            'title' => trim((string) ($profile['title'] ?? '')),
            'badge' => trim((string) ($profile['badge'] ?? '')),
            'bio' => trim((string) ($profile['bio'] ?? '')),
            'avatar' => $avatar,
            // Profile links, only used in structured data: a page about the author and other profiles.
            'url' => self::link($profile['url'] ?? ''),
            'same_as' => array_values(array_filter(array_map(
                [self::class, 'link'],
                is_array($profile['same_as'] ?? null) ? $profile['same_as'] : (preg_split('/\s+/', (string) ($profile['same_as'] ?? '')) ?: [])
            ))),
            'x' => trim((string) ($profile['x'] ?? '')),
        ];
    }

    private static function link(mixed $value): string
    {
        $value = trim(is_string($value) ? $value : '');
        return preg_match('~^https?://[^\s"<>]+$~i', $value) ? $value : '';
    }

    /** @return array<string, array<string, mixed>> keyed by lower-case name */
    private function profiles(): array
    {
        if ($this->profiles !== null) {
            return $this->profiles;
        }
        $config = $this->grav['config'];
        $profiles = [];
        foreach ((array) $config->get('themes.denuo2.authors', []) as $profile) {
            if (is_array($profile) && trim((string) ($profile['name'] ?? '')) !== '') {
                $profiles[mb_strtolower(trim((string) $profile['name']))] = $profile;
            }
        }
        if (!$profiles) {
            foreach ((array) $config->get('plugins.author-info.profiles', []) as $name => $profile) {
                if (is_array($profile)) {
                    $profiles[mb_strtolower(trim((string) $name))] = $profile;
                }
            }
        }
        return $this->profiles = $profiles;
    }
}
