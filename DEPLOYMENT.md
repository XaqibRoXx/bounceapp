# BounceApp deployment

1. Create an empty MySQL/MariaDB database and database user in cPanel.
2. Upload/extract the BounceApp server ZIP into the domain/subdomain document root.
3. Use PHP 8.1+ with PDO MySQL, cURL, fileinfo and mbstring enabled.
4. Ensure the project root and `storage/` are writable during installation.
5. Open `https://your-domain/install.php`.
6. Enter App URL + MySQL credentials and install. No admin/user account is required.
7. Open the app and paste the exact page URL you want to capture. BounceApp will not crawl the rest of the website automatically.

## Screenshot capture

BounceApp uses a fallback chain. If `PAGESPEED_API_KEY` is present in `.env`, PageSpeed is tried first. Otherwise BounceApp goes directly to Thum.io. If a provider fails or rate-limits, the next provider is tried automatically. Manual screenshot upload remains a last fallback.

## Public link model

Every project gets a long random bearer token. Anyone who has the unique workspace link can view and edit that project without logging in. Treat the link as access to the project and only share it with intended collaborators.

## Security

- Use HTTPS.
- Keep `.env` private. `.htaccess` blocks browser access to it on Apache.
- Exact-page URL validation rejects localhost/private/reserved IP targets to reduce SSRF risk.
- Uploaded files are MIME/type and size checked.
- Delete uploaded server ZIP files from the public web root after extraction.
