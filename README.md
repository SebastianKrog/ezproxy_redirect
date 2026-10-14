# EZproxy Redirect

A lightweight PHP redirector for institutional library access via EZproxy, with special handling for PubMed LinkOut.

**Example:** `https://ziw.dk/www.jstor.org/stable/12345` redirects through the Royal Danish Library EZproxy. PubMed links instead open directly with `otool=idkdnlblib`.

## Setup

Upload `index.php` and `.htaccess` to an Apache/PHP web root (PHP 8+). Edit the proxy URL, site domain, and PubMed `otool` identifier in `index.php` for your institution. No dependencies or database required.
