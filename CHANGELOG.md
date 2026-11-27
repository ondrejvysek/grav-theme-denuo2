# Changelog

Versions follow [semantic versioning](https://semver.org): 2.0.x for fixes and small changes,
2.x.0 for new features, a new major version only when sites must change their setup.

## 2.0.0 – 2026-11-27

First public release of Denuo2, the second generation of the Denuo theme for Grav.

**Look and layout**
- Pill navigation with a native `<dialog>` drawer on phones, rounded cards, Home hero with topic
  chips, category pages with an illustration, filter chips and a featured card.
- Articles with a table of contents (sticky column or collapsible card), TL;DR card, author card,
  related articles and previous/next links; 404 and search pages with their own design.
- Colour modes light, sunset, dark (deep blue) and auto, each with seven admin base colours and a
  contrast check; optional mode switcher without a flash on load.
- Shape scale (pills for controls, 20/14/8px radii), shadcn-style button and badge variants.
- Fonts in the Brand settings: Manrope, Open Sans or the system font; only the chosen ones load.
- No CSS framework, one class prefix (`d2-`), accessible (skip link, focus rings, 44px targets,
  reduced motion) and fast (WebP crops with `srcset`, no layout shift).

**Content and SEO**
- Search and social metadata generated from each page's `seo:` fields: title, description,
  robots, canonical, Open Graph, `article:*`, X cards and one schema.org graph (publisher,
  WebSite, WebPage, BreadcrumbList, BlogPosting, main image in 16:9, 4:3 and 1:1). Content made
  for the Aura plugin keeps working.
- Built-in heading anchors, table wrappers, author profiles (Authors tab) and editorial dates
  from front matter, never from file times.
- Companion plugins `d2-components` (callouts, badges, buttons, lead blocks) and `public-pages`
  (public-only search, feeds and sitemap); the theme works without them.

**Sites and languages**
- All front-end texts in Czech and English; interface texts per language through translations,
  the home hero and footer in pages, so multilingual sites get every text in their language.
- Site overrides folder and a `site-head` hook instead of editing the theme; Google Analytics 4
  with Consent Mode v2 and a small, neutral cookie banner.
- `bin/smoke.php` checks a running site, including its SEO output.
