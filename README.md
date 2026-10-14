# EZproxy Redirect

A lightweight PHP redirector for institutional library access via EZproxy, with special handling for PubMed LinkOut.

**Example:** `https://ziw.dk/www.jstor.org/stable/12345` redirects through the Royal Danish Library EZproxy. PubMed links instead open with `otool=idkdnlblib`.

## Setup

Upload `index.php`, `update_allowlist.php`, and `.htaccess` to an Apache/PHP web root (PHP 8+, cURL and DOM extensions). Ensure PHP can create and write `data/` and run `/usr/bin/php` in the background. Configure the proxy, site hostname, and institutional PubMed `otool` in `index.php`.

The first redirect after 24 hours starts a background OCLC allowlist refresh. A missing list does not block redirects; a failed update retains the last list. Generated files are ignored by Git and blocked from web access by `.htaccess`.

**Note:** The OCLC documentation scraper and background execution require testing on the deployment server. OCLC's published stanzas are not a guarantee that an institution has enabled a resource.
