<?php

/**
 * Denuo2 smoke test: renders the key pages of a running site and checks them.
 *
 *   php user/themes/denuo2/bin/smoke.php https://example.test [--insecure] [--path=/extra ...]
 *
 * Finds sections from the main menu and articles from their cards, then checks every page for:
 * HTTP status, PHP/Twig errors, one <h1>, a <title>, <html lang>, the skip-link target, escaped
 * scripts, valid JSON-LD, and that every local image, stylesheet and script loads.
 * Exits with 1 when anything fails, so it can run before an upload or in CI.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

$args = array_slice($argv, 1);
$base = null;
$insecure = false;
$extra = [];
foreach ($args as $arg) {
    if ($arg === '--insecure') {
        $insecure = true;
    } elseif (str_starts_with($arg, '--path=')) {
        $extra[] = substr($arg, 7);
    } elseif (!str_starts_with($arg, '--')) {
        $base = rtrim($arg, '/');
    }
}
if (!$base || !preg_match('#^https?://#', $base)) {
    fwrite(STDERR, "Usage: php smoke.php https://example.test [--insecure] [--path=/extra ...]\n");
    exit(2);
}

$context = stream_context_create([
    'http' => ['ignore_errors' => true, 'timeout' => 60, 'header' => "User-Agent: denuo2-smoke\r\n"],
    'ssl' => $insecure ? ['verify_peer' => false, 'verify_peer_name' => false] : [],
]);

/** @return array{0: int, 1: string} */
function fetch(string $url, $context): array
{
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
            $status = (int) $m[1]; // last one wins after redirects
        }
    }
    return [$status, $body === false ? '' : $body];
}

function dom(string $html): DOMXPath
{
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    return new DOMXPath($document);
}

$failures = 0;
$warnings = 0;
$assetCache = [];
// Problems fail the run; warnings point at content that needs attention but does not break the theme.
$report = static function (bool $ok, string $label, array $problems = [], array $notes = []) use (&$failures, &$warnings): void {
    if (!$ok) {
        $failures++;
    } elseif ($notes) {
        $warnings++;
    }
    echo (!$ok ? 'FAIL ' : ($notes ? 'WARN ' : 'PASS ')) . $label . "\n";
    foreach ($problems as $problem) {
        echo "       - $problem\n";
    }
    foreach ($notes as $note) {
        echo "       ~ $note\n";
    }
};
$hasClass = static fn (string $class): string => "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";

$checkPage = function (string $path, int $expected, string $kind) use ($base, $context, &$assetCache, $report, $hasClass): ?DOMXPath {
    [$status, $html] = fetch($base . $path, $context);
    $problems = [];
    $notes = [];
    if ($status !== $expected) {
        $problems[] = "status $status, expected $expected";
    }
    if (preg_match('/Whoops|Fatal error|Twig\\\\Error|Unknown "[^"]+" (?:function|filter)|Uncaught /', $html, $m)) {
        $problems[] = "error marker: {$m[0]}";
    }
    if ($html === '') {
        $report(false, "$kind $path", array_merge($problems, ['empty response']));
        return null;
    }

    if ($kind === 'feed') {
        if (!str_starts_with(ltrim($html), '<?xml')) {
            $problems[] = 'not XML';
        }
        $report(!$problems, "$kind $path", $problems);
        return null;
    }

    $xpath = dom($html);
    $h1 = $xpath->query('//h1')->length;
    if ($h1 === 0) {
        $problems[] = 'no <h1>';
    } elseif ($h1 > 1) {
        $notes[] = "$h1 <h1> elements (content has its own top-level heading)";
    }
    if (trim((string) $xpath->evaluate('string(//title)')) === '') {
        $problems[] = 'empty <title>';
    }
    if ((string) $xpath->evaluate('string(/html/@lang)') === '') {
        $problems[] = 'missing <html lang>';
    }
    if ($xpath->query('//*[@id="main-content"]')->length !== 1) {
        $problems[] = 'skip-link target #main-content missing';
    }
    if (str_contains($html, '&lt;script')) {
        $problems[] = 'escaped <script> printed as text';
    }
    $types = [];
    foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
        $data = json_decode($script->textContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $problems[] = 'invalid JSON-LD: ' . json_last_error_msg();
            continue;
        }
        foreach ($data['@graph'] ?? [$data] as $node) {
            $types[] = (string) ($node['@type'] ?? '');
        }
    }

    // SEO: indexable pages need a description, robots, canonical, a social image and structured
    // data; articles a BlogPosting. Lengths are advice (warnings), not failures.
    if (in_array($kind, ['home', 'category', 'article', 'page'], true)) {
        $meta = static fn (string $key): string => trim((string) $xpath->evaluate(
            'string(//meta[@name="' . $key . '" or @property="' . $key . '"]/@content)'
        ));
        $robots = $meta('robots');
        if ($robots === '') {
            $problems[] = 'no robots meta';
        }
        if (!str_contains($robots, 'noindex')) {
            $description = $meta('description');
            if ($description === '') {
                $notes[] = 'no meta description';
            } elseif (mb_strlen($description) > 170) {
                $notes[] = 'description over 170 characters';
            }
            $title = trim((string) $xpath->evaluate('string(//title)'));
            if (mb_strlen($title) > 65) {
                $notes[] = 'title over 65 characters (' . mb_strlen($title) . ')';
            }
            if ((string) $xpath->evaluate('string(//link[@rel="canonical"]/@href)') === '') {
                $problems[] = 'no canonical link';
            }
            foreach (['og:title', 'og:url', 'og:type', 'og:site_name'] as $key) {
                if ($meta($key) === '') {
                    $problems[] = "no $key";
                }
            }
            $image = $meta('og:image');
            if ($image === '') {
                $notes[] = 'no og:image';
            } elseif (!isset($assetCache[$image])) {
                [$assetCache[$image]] = fetch($image, $context);
            }
            if ($image !== '' && $assetCache[$image] !== 200) {
                $problems[] = "og:image {$assetCache[$image]}: $image";
            }
            if (!in_array('WebSite', $types, true) || !array_intersect(['WebPage', 'CollectionPage'], $types)) {
                $problems[] = 'structured data without WebSite/WebPage';
            }
            if ($kind === 'article' && !in_array('BlogPosting', $types, true)) {
                $problems[] = 'article without BlogPosting';
            }
        }
    }

    $urls = [];
    foreach ($xpath->query('//img/@src|//link[@rel="stylesheet"]/@href|//script/@src|//link[@rel="preload"]/@href') as $attr) {
        $urls[] = $attr->value;
    }
    foreach ($xpath->query('//img/@srcset') as $attr) {
        foreach (explode(',', $attr->value) as $candidate) {
            $urls[] = strtok(trim($candidate), ' ');
        }
    }
    $host = parse_url($base, PHP_URL_HOST);
    foreach (array_unique(array_filter($urls)) as $url) {
        if (str_starts_with($url, 'data:')) {
            continue;
        }
        if (str_starts_with($url, '//') || (preg_match('#^https?://#', $url) && parse_url($url, PHP_URL_HOST) !== $host)) {
            continue; // external
        }
        $absolute = preg_match('#^https?://#', $url) ? $url : $base . '/' . ltrim($url, '/');
        if (!isset($assetCache[$absolute])) {
            [$assetStatus] = fetch($absolute, $context);
            $assetCache[$absolute] = $assetStatus;
        }
        if ($assetCache[$absolute] !== 200) {
            $problems[] = "asset {$assetCache[$absolute]}: $url";
        }
    }

    if ($kind === 'category' && $xpath->query('//*[' . $hasClass('d2-card') . ']')->length === 0) {
        $notes[] = 'no article cards (empty category?)';
    }
    if ($kind === 'article' && $xpath->query('//*[@id="article-content"]')->length !== 1) {
        $problems[] = 'article body missing';
    }

    $report(!$problems, "$kind $path", $problems, $notes);
    return $xpath;
};

echo "Denuo2 smoke test: $base\n\n";

$home = $checkPage('/', 200, 'home');
$sections = [];
if ($home) {
    foreach ($home->query('//*[' . $hasClass('d2-nav-desktop') . ']//a[' . $hasClass('d2-nav-link') . ']/@href') as $href) {
        $sections[] = parse_url($href->value, PHP_URL_PATH) ?: $href->value;
    }
}
$sections = array_values(array_unique($sections));
if (!$sections) {
    $report(false, 'main menu', ['no section links found on the home page']);
}

$articles = [];
foreach ($sections as $index => $section) {
    $page = $checkPage($section, 200, 'category');
    if ($page && $index < 3) {
        foreach ($page->query('//a[' . $hasClass('d2-card-link') . ']/@href') as $n => $href) {
            if ($n >= 2) {
                break;
            }
            $articles[] = parse_url($href->value, PHP_URL_PATH) ?: $href->value;
        }
    }
}
foreach (array_unique($articles) as $article) {
    $checkPage($article, 200, 'article');
}
foreach ($extra as $path) {
    $checkPage($path, 200, 'page');
}

$checkPage('/search?query=test', 200, 'search');
$checkPage('/denuo2-smoke-missing-page', 404, 'error');
foreach (['/feed.rss', '/sitemap.xml'] as $feed) {
    [$status] = fetch($base . $feed, $context);
    if ($status !== 404) {
        $checkPage($feed, 200, 'feed');
    }
}

echo "\n" . ($failures ? "$failures check(s) failed" : 'All checks passed') . ($warnings ? ", $warnings warning(s).\n" : ".\n");
exit($failures ? 1 : 0);
