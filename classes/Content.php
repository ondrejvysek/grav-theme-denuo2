<?php

declare(strict_types=1);

namespace Grav\Theme\Denuo2;

use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;

/**
 * Site content that differs per language lives in pages, which Grav keeps per language
 * (`.cs.md`, `.en.md`): the home hero in the home page's `hero:` front matter and the footer in
 * the hidden `_footer` page. The theme settings of the same name are the fallback for sites that still
 * keep these texts there.
 */
final class Content
{
    public function __construct(private Grav $grav)
    {
    }

    /** @return array<string, mixed> tag, title, lead, primary, secondary, image, image_alt, enabled */
    public function hero(): array
    {
        $config = (array) $this->grav['config']->get('themes.denuo2.home.hero', []);
        $home = $this->grav['pages']->find((string) $this->grav['config']->get('system.home.alias', '/'));
        $own = $home ? (array) ($home->header()->hero ?? []) : [];
        unset($own['enabled']);
        return array_replace_recursive($config, $own);
    }

    /** @return array{tagline?: string, columns?: list<array>} */
    public function footer(): array
    {
        $page = $this->footerPage();
        $own = $page ? (array) ($page->header()->footer ?? []) : [];
        return $own ?: (array) $this->grav['config']->get('themes.denuo2.footer', []);
    }

    private function footerPage(): ?PageInterface
    {
        $page = $this->grav['pages']->find('/_footer');
        return $page instanceof PageInterface ? $page : null;
    }
}
