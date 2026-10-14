<?php
// KB EZproxy redirector. Prepend https://ziw.dk/ to the original URL.
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$proxyBase = 'https://login.kb-ku.idm.oclc.org/login?qurl=';
$instructions =
    "Article access via the Royal Danish Library (KB)\n" .
    "================================================\n\n" .
    "Add https://ziw.dk/ before an article URL.\n\n" .
    "Example:\n" .
    "  https://www.jstor.org/stable/12345\n" .
    "  https://ziw.dk/www.jstor.org/stable/12345\n\n" .
    "You can also include the original https:// prefix.\n" .
    "PubMed links use KB's institutional LinkOut.\n" .
    "KU/KB login and library access may be required.\n";

$input = substr($_SERVER['REQUEST_URI'] ?? '/', 1);
if ($input === '' || $input === '/') {
    http_response_code(200);
    exit($instructions);
}
if (strlen($input) > 8192 || preg_match('/[\x00-\x1F\x7F]/', $input)) {
    http_response_code(400);
    exit("Invalid or oversized article URL.\n\n" . $instructions);
}
if (preg_match('~^https?%3A~i', $input)) $input = rawurldecode($input);
if (!preg_match('~^https?://~i', $input)) $input = 'https://' . $input;
$parts = parse_url($input);
$host = strtolower($parts['host'] ?? '');
if (!filter_var($input, FILTER_VALIDATE_URL) ||
    !in_array(strtolower($parts['scheme'] ?? ''), ['http','https'], true) ||
    !$host || !preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/i', $host) ||
    isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) ||
    $host === 'ziw.dk' || str_ends_with($host, '.ziw.dk')) {
    http_response_code(400);
    exit("Invalid article URL.\n\n" . $instructions);
}
// Normalise HTTP to HTTPS; preserve path, query and fragment.
$input = 'https://' . $host . ($parts['path'] ?? '/')
    . (isset($parts['query']) ? '?' . $parts['query'] : '')
    . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

// Static allowlist; if missing, continue redirects as requested.
$list = __DIR__ . '/domains.txt';
if (is_file($list)) {
    $domains = file($list, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($domains !== false && count($domains) > 0) {
        $allowed = false;
        foreach ($domains as $domain) {
            // Match exact domain or any subdomain; never a lookalike suffix.
            $domain = strtolower(trim($domain));
            if ($domain === '' || str_starts_with($domain, '#')) continue;
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            http_response_code(403);
            exit("This destination is not included in the academic-resource domain allowlist.\n"
                . "Contact the site administrator if this is a legitimate research resource.\n");
        }
    }
}

if ($host === 'pubmed.ncbi.nlm.nih.gov') {
    $params = [];
    parse_str($parts['query'] ?? '', $params);
    $params['otool'] = 'idkdnlblib';
    $path = $parts['path'] ?? '/';
    if ($path === '') $path = '/';
    $target = 'https://pubmed.ncbi.nlm.nih.gov' . $path .
        '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    if (isset($parts['fragment'])) $target .= '#' . $parts['fragment'];
    header('Location: ' . $target, true, 302);
    exit;
}

header('Location: ' . $proxyBase . rawurlencode($input), true, 302);
exit;
