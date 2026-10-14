<?php
// Lightweight KB EZproxy redirector.
// Usage: https://ziw.dk/www.example.com/article

header('Content-Type: text/plain; charset=utf-8');

$proxyBase = 'https://login.kb-ku.idm.oclc.org/login?qurl=';

$instructions =
    "Article access via the Royal Danish Library (KB)\n" .
    "================================================\n\n" .
    "Add https://ziw.dk/ before an article URL to access it through KB.\n\n" .
    "Example:\n" .
    "  https://www.jstor.org/stable/12345\n" .
    "  becomes\n" .
    "  https://ziw.dk/www.jstor.org/stable/12345\n\n" .
    "You can also include the original https:// prefix.\n\n" .
    "PubMed links use KB's institutional LinkOut:\n" .
    "  https://ziw.dk/pubmed.ncbi.nlm.nih.gov/42314835/\n" .
    "  becomes\n" .
    "  https://pubmed.ncbi.nlm.nih.gov/42314835/?otool=idkdnlblib\n\n" .
    "KU/KB login and library access may be required.\n";

$input = substr($_SERVER['REQUEST_URI'] ?? '/', 1);

if ($input === '' || $input === '/') {
    http_response_code(200);
    exit($instructions);
}

// Decode fully encoded article URLs, preserving existing encoding otherwise.
if (preg_match('~^https?%3A~i', $input)) {
    $input = rawurldecode($input);
}

if (!preg_match('~^https?://~i', $input)) {
    $input = 'https://' . $input;
}

if (!filter_var($input, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    exit("Invalid article URL.\n\n" . $instructions);
}

$parts = parse_url($input);
$scheme = strtolower($parts['scheme'] ?? '');
$host = strtolower($parts['host'] ?? '');

if (!in_array($scheme, ['http', 'https'], true)) {
    http_response_code(400);
    exit("Unsupported URL scheme.\n\n" . $instructions);
}

if (
    !$host ||
    !preg_match('/^(?=.{1,253}$)[a-z0-9-]+(?:\.[a-z0-9-]+)+$/i', $host) ||
    str_contains($host, '..')
) {
    http_response_code(400);
    exit("Invalid article domain.\n\n" . $instructions);
}

if ($host === 'ziw.dk' || str_ends_with($host, '.ziw.dk')) {
    http_response_code(400);
    exit("Cannot redirect to ziw.dk itself.\n\n" . $instructions);
}

// PubMed: institutional LinkOut rather than EZproxy.
if ($host === 'pubmed.ncbi.nlm.nih.gov') {
    $params = [];
    parse_str($parts['query'] ?? '', $params);
    $params['otool'] = 'idkdnlblib';

    $path = $parts['path'] ?? '/';
    if ($path === '') {
        $path = '/';
    }

    $target = 'https://pubmed.ncbi.nlm.nih.gov'
        . $path
        . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    if (isset($parts['fragment'])) {
        $target .= '#' . $parts['fragment'];
    }

    header('Location: ' . $target, true, 302);
    exit;
}

// Other resources: encode the full URL for the KB EZproxy starting point.
header('Location: ' . $proxyBase . rawurlencode($input), true, 302);
exit;
