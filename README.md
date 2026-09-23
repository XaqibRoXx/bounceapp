# BounceApp

BounceApp is a no-login visual website feedback tool. Paste a public website URL, scan its pages, automatically capture the homepage, mark exact areas on screenshots, create change requests, discuss them, and share one public workspace link.

## No-account workflow

1. Paste a website URL on the homepage.
2. BounceApp creates a database-backed project with a unique public token.
3. Sitemap/homepage links are scanned and stored as project pages.
4. The homepage is captured automatically.
5. Anyone with the unique workspace link can open pages, recapture screenshots, draw annotations, add comments, update statuses, Approve or Reopen — no login/signup.
6. Manual screenshot upload remains available only as a fallback.

## Screenshot capture

- If `PAGESPEED_API_KEY` is configured, BounceApp tries Google PageSpeed first.
- If PageSpeed is unavailable/rate-limited, or no key is configured, BounceApp automatically uses the Thum.io keyless screenshot endpoint.
- If both providers fail, a manual JPG/PNG/WEBP upload option remains available.

## Server requirements

PHP 8.1+ with PDO MySQL, cURL, fileinfo, and mbstring; MySQL/MariaDB; Apache/cPanel recommended.

See `DEPLOYMENT.md`.
