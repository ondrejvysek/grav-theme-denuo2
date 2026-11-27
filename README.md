# Denuo2

An editorial blog theme for [Grav](https://getgrav.org): pastel gradient background, rounded
article cards, pill navigation, and light, sunset and dark colour modes (fixed, automatic, or
chosen by the visitor with a switcher) whose base colours you set in Admin. No CSS framework, no jQuery, about 10 KB of JavaScript. Built for
accessibility (keyboard navigation, focus rings, 4.5:1 contrast checks, reduced motion) and for
fast pages (WebP crops with `srcset`, no layout shift).

The front-end texts (navigation, buttons, dates, cookie banner) come in **Czech** and
**English**, picked by the page language. The Admin
settings are in English. See [Translating the front end](#translating-the-front-end).

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Content structure](#content-structure)
- [Theme settings](#theme-settings)
- [Page fields](#page-fields)
- [SEO](#seo)
- [Optional plugins](#optional-plugins)
- [Site overrides](#site-overrides)
- [Components](#components)
- [Colour modes and the switcher](#colour-modes-and-the-switcher)
- [Language switcher](#language-switcher)
- [Analytics and cookie consent](#analytics-and-cookie-consent)
- [Translating the front end](#translating-the-front-end)
- [Checking a site](#checking-a-site)
- [Files](#files)
- [License](#license)

## Requirements

- Grav 1.7 or newer, PHP 8.0 or newer.
- PHP GD with WebP support (Grav creates the WebP card and hero crops).
- Recommended plugins: `pagination` (category paging), `simplesearch` (search), `feed`
  (RSS/Atom), `sitemap`. `admin` for the settings screens.

## Installation

1. Copy this folder to `user/themes/denuo2`.
2. In `user/config/system.yaml` set:

   ```yaml
   pages:
     theme: denuo2
   ```

3. Clear the cache (Admin → Clear Cache, or `bin/grav clearcache`).
4. Open **Admin → Themes → Denuo2** and fill in the Brand (site name, logo), Home page and Footer
   tabs. Your
   settings are saved to `user/config/themes/denuo2.yaml`; never edit the theme's own
   `denuo2.yaml`, which only holds defaults and is replaced on updates.

To try the theme on a staging copy only, put the theme switch in an environment folder instead,
for example `user/env/staging.example.com/config/system.yaml`.

## Content structure

| Page type | Template | Used for |
|---|---|---|
| Home | `modular` with a `news` module | Hero from the theme settings, topic chips, the latest articles |
| Category | `blog_list` | Heading, intro and illustration, filter chips, a featured newest article, card grid, pagination |
| Article | `blog_item` | Hero image, byline, table of contents, optional TL;DR, related articles, author card, previous/next |
| Page | `default` | Plain text pages (about, privacy) |

A typical tree:

```
user/pages/
  01.home/            modular.md (+ _news/news.md)
  02.blog/            blog_list.md
    my-first-post/    blog_item.md (+ images)
  03.privacy/         default.md
```

The top-level visible pages form the main menu; a page with visible children gets a submenu.

**Home** (`modular.md`):

```yaml
---
title: Home
content:
  items: '@self.modular'
---
```

with a module `_news/news.md`:

```yaml
---
title: 'Latest articles'   # heading of the section
id: clanky                 # anchor the hero's first button points to
---
```

The news module lists the newest `blog_item` pages from the whole site (the number is a theme
setting). If the home page has a `_hero` module, the theme's hero replaces it while
**Home page → Hero from theme settings** is on.

**Category** (`blog_list.md`):

```yaml
---
title: Blog
content:
  items: '@self.children'
  order: { by: date, dir: desc }
  limit: 12
  pagination: true
---
```

Filter chips link to Grav's taxonomy URLs (`/blog/tag:Name`), so keep the `tag` taxonomy enabled
(`site.yaml` → `taxonomies: [category, tag]`).

**Article** (`blog_item.md`):

```yaml
---
title: 'Article title'
date: '2026-11-25 10:00'
author: 'Jane Doe'
taxonomy:
  tag: [Copilot, Security]
seo:
  description: 'One or two sentences for search results, social cards and article cards.'
  image: main-image.jpg     # file in the article folder
---
```

## Theme settings

Admin → Themes → Denuo2:

| Tab | Settings |
|---|---|
| Appearance | Colour mode (light, sunset, dark, auto), the colour mode switcher, and seven base colours for each mode: background gradient start, middle and end, surface, text, primary, accent. Every other colour (headings, muted text, borders, tags, links, shadows, focus ring, scrollbars, text selection) is derived from these with CSS `color-mix()`. Saving warns when text, surface or button colours fall below 4.5:1 contrast. |
| Brand | Site name (shown in the header and footer; empty: the site title), logo (square, SVG recommended; empty: the name alone), touch icon (180×180 PNG), publisher logo for structured data (512×512 PNG), fallback image for articles without images, heading and body font (Manrope, Open Sans or the system font; only the chosen fonts load). The theme ships no logo of its own; empty icon fields are simply left out. |
| SEO | Site name for search and social, publisher (organization or person) and its name, profile links, X account, large image previews. |
| Authors | Author profiles for the byline and the author card: name (as in the article's `author`), title, byline badge, photo, short bio; profile page, other profiles and X account for structured data. |
| Site | Copyright start year and owner, search-engine title pattern for categories (`{title}: guides and news`), tags that are never promoted. |
| Privacy & analytics | Google Analytics 4 measurement ID, cookie banner on/off, privacy policy page. Banner texts come from the translations. |
| Home page | Theme hero on/off (its texts, buttons and image are edited in the home page, **Hero** tab), topic chips, number of latest articles. |

## Page fields

Category (`blog_list`), all optional:

| Field | Effect |
|---|---|
| `heading` | Page H1. Otherwise `design.text-header`, then the title. |
| `design.image` | Illustration beside the heading (path under `/user/images`), shown at most at its own size and hidden on phones. |
| `intro` | Sentence under the heading, also the meta description fallback. |
| `seo.title`, `seo.description` | Search title and description (see [SEO](#seo)); otherwise the title pattern and the intro. |
| `chips` | List of tags shown as filter chips. Otherwise the five most used tags. |

Article (`blog_item`), all optional:

| Field | Effect |
|---|---|
| `seo.image`, `preview_image_file`, `hero_image_file_lg` | Candidate images. Cards (16:9) and the hero (16:7) use whichever gives the largest crop, never upscaled. |
| `readingtime` | Minutes; otherwise estimated at about 170 words a minute. |
| `tldr` | List of key points shown in a card above the text. |
| `verified` | Date the facts were last checked, shown in the byline. |
| `toc: false` | Hides the table of contents; the body centres, as for articles without headings. |
| `hideonhome: true` | Keeps the article out of the latest articles on Home. |

## SEO

Every page gets its search and social metadata from a few inputs; nothing generated is stored in
the page. Fields (all optional) in the page's front matter, also in the Admin forms:

```yaml
seo:
  title: ''         # search title; " | <site name>" is added. Default: the page title
  description: ''   # 120–160 characters. Default: the opening of the article (categories: intro)
  image: hero.jpg   # file in the page folder. Default: the largest suitable image there
  image_alt: ''     # default: the title
  robots: ''        # 'noindex' or 'noindex, nofollow'; default: indexed
  canonical: ''     # full URL of the original, for republished articles
```

Generated for every page:

- `<title>`, meta description, `robots` (with `max-image-preview:large`), canonical URL;
  filtered and invalid listing pages are `noindex` without a canonical.
- Open Graph: site name, `og:locale` (`cs_CZ` …), type, URL, title, description and image with
  type, size and alt text. Page images are cropped to 1200×630 JPEG, never upscaled.
- Articles: `article:published_time`, `article:modified_time` (only with `modified_date`),
  `article:author` (the author's profile page), `article:section` and `article:tag`.
- X: `summary_large_image`, `twitter:site` and `twitter:creator` when accounts are set.
- One schema.org graph: Organization or Person (publisher, logo, profiles), WebSite (site name,
  the site title as alternate name), WebPage or CollectionPage, BreadcrumbList (the visible
  breadcrumbs), the main image, and for articles a BlogPosting with the author's profile, dates,
  section, tags and the image in 16:9, 4:3 and 1:1.

Dates come from the front matter only (`date`, `modified_date`), never from file times.

**Coming from the Aura plugin:** `aura.description`, `aura.image` and an `og:image` stored in
`metadata` are still read when `seo` has no value, so existing content keeps its metadata. Values
in `metadata:` for tags the theme generates (`og:*`, `twitter:*`, `article:*`, description,
keywords, robots) are ignored; other extra tags, such as site verification codes, are printed.

## Built in

- **Table of contents and anchors:** ids on `h2`–`h5`, a table of contents from the `h2`
  sections, wide tables in a scrollable, labelled box, lazy loading and intrinsic sizes for the
  article's own images.
- **Authors:** profiles under **Admin → Themes → Denuo2 → Authors** (name, title, byline badge,
  photo, short bio), matched on the article's `author`. They feed the byline and the author card.
- **Editorial dates:** published (`date`), updated (`modified_date`) and verified (`verified`) in
  the byline and the feeds; file times are never used.

## Companion plugins

The theme works without them.

| Plugin | Adds | Without it |
|---|---|---|
| `d2-components` | Callouts, badges, buttons and lead blocks as short tags in content; clean excerpts for cards and feeds | Content as written; excerpt from the description or Grav's summary |
| `public-pages` | Search, feeds and sitemap list public pages only; sitemap dates from front matter | Grav's defaults |
| `langswitcher` | Language pill and `hreflang` (with more than one language) | Not shown |

With `d2-components` the article content goes through `render_components`. The theme styles its
markup (`d2-callout`, `d2-lead`, `d2-tag`, `d2-btn`); see the plugin's README for the tags.

## Site overrides

A site can replace any theme file (templates, page blueprints, images) without editing the theme,
which keeps updates painless. Add an override folder in front of the theme in
`user/config/themes/denuo2.yaml`:

```yaml
streams:
  schemes:
    theme:
      type: ReadOnlyStream
      paths:
        - 'user://denuo2-overrides'
        - 'user://themes/denuo2'
```

Then copy the template you want to change into `user/denuo2-overrides/` with the same path, for
example `user/denuo2-overrides/templates/partials/footer.html.twig`.

For extra admin fields, do not copy a blueprint: a copy hides every field the theme adds later.
Extend the theme's blueprint instead and list only your additions or changes, for example in
`user/denuo2-overrides/blueprints/blog_item.yaml`:

```yaml
title: Article
'@extends':
    type: blog_item
    context: 'user://themes/denuo2/blueprints'

form:
  fields:
    tabs:
      fields:
        article:
          fields:
            header.subtitle:
              type: text
              label: Subtitle
              ordering@: header.tldr
```

### Site hook

`templates/partials/site-head.html.twig` is empty in the theme and rendered at the end of
`<head>`. Override it to add your own styles, scripts or meta tags without copying
`base.html.twig`, for example to load a stylesheet only on pages that need it:

```twig
{# user/denuo2-overrides/templates/partials/site-head.html.twig #}
{% if page.header.uses_bootstrap %}
    {% do assets.addCss('theme://css/bootstrap.min.css', {priority: 110}) %}
{% endif %}
```

Files the hook loads through `theme://` can live in the override folder too
(`user/denuo2-overrides/css/…`), so the theme folder stays untouched.

## Components

Built in the manner of shadcn/ui (semantic tokens, one variant per role, shared states), drawn in
the theme's pill style. Use these classes in templates and content instead of one-off colours.

| Component | Classes |
|---|---|
| Button | `d2-btn` (primary) + `--secondary`, `--outline`, `--ghost`, `--destructive`, `--link`; sizes `--sm`, `--lg`, `--icon` |
| Badge | `d2-tag` + `--solid`, `--outline`, `--success`, `--destructive` |
| Input | `d2-input` (pill field; `aria-invalid="true"` marks errors) |
| Card | `partials/card.html.twig` (the only article listing component) |

Hover changes the fill, keyboard focus shows the focus ring, `disabled` / `aria-disabled="true"`
dims to 50%. Small buttons grow to 44px on touch screens. Bootstrap class names written by
plugins or older content (`btn-primary`, `btn-outline-*`, `btn-danger`, `btn-link`, `badge`,
`alert-*`, `table`) are styled to match.

## Colour modes and the switcher

Three modes share the same layout and components: **Light** (pastel), **Sunset** (a warm dusk
theme: plum background, light text, orange accents) and **Dark**. Each has its own seven base
colours under Appearance; every other colour is derived from them.

- **Fixed mode**: set *Colour mode* and leave the switcher off. Everyone sees that mode; *Auto*
  follows the visitor's operating system (light or dark).
- **Switcher**: turn on *Show colour mode switcher*. A light / sunset / dark switch appears next
  to the search button (a single cycling button between 992 and 1199 px, or up to 1279 px when
  the language pill is shown; inside the menu on phones). *Colour mode* is then only the starting mode.
- The visitor's choice is stored in the `denuo_mode` cookie for 12 months. The server renders
  the chosen mode straight away and a small inline script re-applies it before paint, so pages
  never flash the wrong colours, even behind a page cache. It is a preference the visitor sets
  explicitly, so it needs no consent and is not part of the cookie banner.
- The switcher is a native radio group: Tab to reach it, arrow keys to change mode, announced as
  "Barevný režim". Changes cross-fade where the browser supports View Transitions, instantly
  with reduced motion.

## Language switcher

With the [`langswitcher`](https://github.com/getgrav/grav-plugin-langswitcher) plugin installed
and enabled and more than one language in System → Languages, a language pill (`CS ▾`) appears
next to the colour mode switcher, and a *Jazyk* row of language chips in the phone menu. Without
the plugin, with it disabled, or with a single language, nothing is shown.

- Links come from the plugin's own logic, so translated slugs, the default-language prefix and
  untranslated pages follow its settings. The theme also outputs `hreflang` alternate links
  (with `x-default` when the default language has no URL prefix).
- With `append_url_extension` (e.g. `.html`) the plugin adds the extension twice; the theme's
  templates remove the duplicate, so use them rather than the plugin's own partials.
- The theme draws its own markup; set `built_in_css: false` in the plugin settings to skip the
  plugin's stylesheet.

## Analytics and cookie consent

Leave the GA4 ID empty and the theme outputs no analytics code and no banner.

With an ID set:

- `<head>` gets Google Consent Mode v2 defaults with all storage denied.
- A small non-modal banner asks whether optional cookies may be used. Necessary cookies are
  always used. Both buttons look the same, so neither choice is pushed, and the banner links to
  your privacy page.
- `gtag.js` is downloaded only after the visitor allows it. Refusing stores the choice, removes
  `_ga*` cookies and reloads if the tag had loaded.
- The choice is kept for 12 months in `localStorage`; the footer link reopens the banner.

If you rewrite the banner text, keep a short phrase saying what the optional cookies are for:
under the GDPR and ePrivacy rules consent only counts when it is informed. This is not legal
advice; check the wording with whoever handles privacy for your site.

## Translating the front end

All front-end texts (menus, labels, screen-reader texts, the cookie banner, the error page and
the strings used by the scripts) are in `languages.yaml` under `THEME_DENUO2`, in Czech (`cs`)
and English (`en`). The active page language picks the set.

- **Another language:** copy the `en` block into `user/languages/<lang>.yaml` (or a copy of the
  theme's `languages.yaml` in your override folder) and translate it.
- **Change a single text:** put just that key in `user/languages/<lang>.yaml`; site files win over
  the theme. For example, a different search placeholder:

  ```yaml
  # user/languages/en.yaml
  THEME_DENUO2:
    SEARCH:
      PLACEHOLDER: 'e.g. Copilot Studio…'
  ```

- Dates use `THEME_DENUO2.DATE_FORMAT` (PHP `date()` format): `6. 11. 2026` in Czech,
  `6 Nov 2026` in English.

### Multilingual sites

Theme settings hold only what is the same in every language (colours, logo, fonts, analytics,
page routes). Every visible text comes from a place Grav keeps per language:

| Text | Where | Per language |
|---|---|---|
| Interface texts: menus, buttons, cookie banner, error page, category title pattern (`CATEGORY.TITLE_PATTERN`, e.g. `'{title}: guides and news'`) | `languages.yaml`, overridden in `user/languages/<lang>.yaml` | one file per language |
| Home hero: tag, heading, sentence, buttons, image | the home page's `hero:` front matter (Admin: **Hero** tab) | `modular.<lang>.md` |
| Footer: text under the logo, link columns | the hidden page `user/pages/_footer` (template `footer`, Admin: **Footer** tab) | `default.<lang>.md` |
| Articles, categories, SEO fields | the pages themselves | `<template>.<lang>.md` |

A language without its own `_footer` or home page file shows the default language's version
(Grav's content fallback), so add one per language. Sites set up before the hero and footer moved
to pages keep working: the theme settings `home.hero.*`, `footer` and `category_title_pattern`
are still read when the page or translation does not set them.
## Checking a site

`bin/smoke.php` loads the home page, every section in the menu, two articles from each of the
first three sections, search, a missing page, the feed and the sitemap. On each page it checks
the status code, PHP/Twig error output, one `<h1>`, `<title>`, `lang`, `#main-content`, valid
JSON-LD and that local images, styles and scripts load.

```
php user/themes/denuo2/bin/smoke.php https://example.com
php user/themes/denuo2/bin/smoke.php https://site.test --insecure      # self-signed certificate
php user/themes/denuo2/bin/smoke.php https://example.com --path=/blog/some-article.html
```

`PASS`/`WARN` lines are informational (a warning is usually content: an empty category, a second
`<h1>` in an article); any `FAIL` makes it exit with code 1.

## Files

```
denuo2/
  denuo2.php           theme class: events and Twig functions
  classes/             Colors (tokens, contrast check), Media (brand, images), Seo (metadata),
                       Articles (reading time, chips, related, TOC), Outline (anchors, table
                       wrappers, image sizes), Authors, Dates, Typography, Site (links, dates,
                       translations, consent texts), Fallbacks (without d2-components)
  denuo2.yaml          defaults (do not edit; use Admin or user/config/themes/denuo2.yaml)
  languages.yaml       front-end texts, cs + en
  blueprints.yaml      Admin settings
  blueprints/          page blueprints (Category, Article)
  bin/smoke.php        smoke test for a running site
  css/denuo2.css       all styles, in numbered sections (see the outline at the top)
  js/site.js           reading progress, mobile menu dialog, submenus
  js/mode.js           colour mode switcher (only loaded when enabled)
  js/article.js        table of contents, image lightbox
  js/consent.js        cookie banner and GA4 loading
  templates/           Twig templates
  fonts/               Manrope and Open Sans 400/600/700/800 (latin + latin-ext, WOFF2)
```

## License

MIT, see [LICENSE](LICENSE). Bundled: Manrope and Open Sans (SIL Open Font License 1.1).
