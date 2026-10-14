<?php
// Run with PHP CLI only. OCLC documentation is the source, not KB's live configuration.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$dir = __DIR__ . '/data';
if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
$lock = fopen($dir . '/update.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { exit; }
$stamp = $dir . '/last_attempt';
if (is_file($stamp) && time() - filemtime($stamp) < 86400) { exit; }
// Touch before network calls. Failed runs retry after 24h, preventing request storms.
touch($stamp);

function download(string $url): ?string {
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'EZproxyRedirect/1.0 (+https://ziw.dk/)',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($c);
    $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return is_string($body) && $status === 200 && strlen($body) < 2500000 ? $body : null;
}
function links(string $html): array {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $paths = [];
    foreach ($dom->getElementsByTagName('a') as $a) {
        $href = html_entity_decode($a->getAttribute('href'));
        $path = parse_url($href, PHP_URL_PATH);
        if (is_string($path) &&
            preg_match('~^/Library_Management/EZproxy/EZproxy_database_stanzas/Database_stanzas_[^/]+/[^/]+$~', $path)) {
            $paths[$path] = true;
        }
    }
    return array_keys($paths);
}
function registrable(string $host, array $suffixes): ?string {
    $labels = explode('.', strtolower(trim($host, '.')));
    if (count($labels) < 2) return null;
    $match = 1;
    $exception = false;
    $n = count($labels);
    for ($i = 0; $i < $n; $i++) {
        $tail = implode('.', array_slice($labels, $i));
        if (isset($suffixes['!' . $tail])) { $match = $n - $i - 1; $exception = true; break; }
        if (isset($suffixes[$tail])) $match = max($match, $n - $i);
        if ($i + 1 < $n && isset($suffixes['*.' . implode('.', array_slice($labels, $i + 1))]))
            $match = max($match, $n - $i);
    }
    return $n > $match ? implode('.', array_slice($labels, -($match + 1))) : null;
}
$pslText = download('https://publicsuffix.org/list/public_suffix_list.dat');
if (!$pslText) exit(1);
$suffixes = [];
foreach (explode("\n", $pslText) as $line) {
    $line = trim($line);
    if ($line !== '' && !str_starts_with($line, '//') && preg_match('/^[!*.a-z0-9-]+$/i', $line))
        $suffixes[strtolower($line)] = true;
}
$root = 'https://help.oclc.org/Library_Management/EZproxy/EZproxy_database_stanzas';
$html = download($root);
if (!$html) exit(1);
$pages = links($html);
// Directory may paginate/link through the alphabetical subdirectories.
$dom = new DOMDocument(); @$dom->loadHTML($html);
foreach ($dom->getElementsByTagName('a') as $a) {
    $path = parse_url($a->getAttribute('href'), PHP_URL_PATH);
    if (is_string($path) && preg_match('~^/Library_Management/EZproxy/EZproxy_database_stanzas/Database_stanzas_[^/]+$~', $path)) {
        $sub = download('https://help.oclc.org' . $path);
        if ($sub) $pages = array_merge($pages, links($sub));
    }
}
$pages = array_unique($pages);
if (count($pages) < 100) exit(1);

$domains = ['ncbi.nlm.nih.gov' => true];
$success = 0;
foreach ($pages as $path) {
    $page = download('https://help.oclc.org' . $path);
    if (!$page) continue;
    $success++;
    // Only parse explicit EZproxy directives in rendered documentation.
    $text = html_entity_decode(strip_tags(preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $page)));
    if (preg_match_all('/^\s*(?:URL|U|H|HJ|Host|HostJavaScript|D|DJ|Domain|DomainJavaScript)\s+([^\s<>]+)/mi', $text, $matches)) {
        foreach ($matches[1] as $value) {
            $host = parse_url(str_contains($value, '://') ? $value : 'https://' . $value, PHP_URL_HOST);
            if (!is_string($host) || !preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/i', $host)) continue;
            $base = registrable($host, $suffixes);
            if ($base !== null) $domains[$base] = true;
        }
    }
}
// Reject unexpectedly small or incomplete downloads rather than replacing a good list.
if ($success < count($pages) * 0.90 || count($domains) < 100) exit(1);
ksort($domains);
$tmp = tempnam($dir, 'domains-');
file_put_contents($tmp, implode("\n", array_keys($domains)) . "\n");
chmod($tmp, 0600);
rename($tmp, $dir . '/domains.txt');
