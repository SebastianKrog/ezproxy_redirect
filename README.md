# EZproxy Redirect

A lightweight PHP redirector for institutional library access via EZproxy, with PubMed LinkOut support.

**Example:** `https://ziw.dk/www.jstor.org/stable/12345` redirects through KB's EZproxy. PubMed links use `otool=idkdnlblib` instead.

## Setup

Upload `index.php`, `domains.txt`, and `.htaccess` to an Apache/PHP web root (PHP 8+). Adjust the proxy, site hostname, and PubMed `otool` in `index.php`.

`domains.txt` is a generous static allowlist of academic-resource domains, informed by public EZproxy configuration examples and supplemented manually. Subdomains are accepted. Edit the file to add resources. No updater, cron, database, or background process is required. If the list is missing, redirects remain available.

The list is not OCLC-certified and does not represent KB's active subscriptions or guarantee that every listed domain is safe.
