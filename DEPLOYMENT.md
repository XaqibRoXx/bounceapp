# BounceApp deployment

1. Create an empty MySQL database and database user in cPanel; grant the user all privileges on that database.
2. Upload/extract all BounceApp files into the intended document root.
3. Select PHP 8.1 or newer and enable PDO MySQL, cURL, fileinfo, and mbstring.
4. Ensure the application root, `storage/`, and `public/uploads/` are writable during setup (normally 0755 directories on cPanel).
5. Visit `https://your-domain/install.php` and enter the app URL, database details, and first administrator account.
6. After installation, sign in and create a project.
7. Page capture uses Google PageSpeed Insights final-screenshot data. It can work without an API key under public quota, but for reliable production use you may put a Google PageSpeed API key in `.env` as `PAGESPEED_API_KEY=`.
8. If automatic capture is unavailable or rate-limited, every page has a manual JPG/PNG/WEBP screenshot upload fallback.
9. BounceApp validates target URLs as public http/https URLs and blocks private/reserved IPs before direct website scanning.
10. Use HTTPS in production and keep `.env` private. Apache `.htaccess` included in this package blocks direct access to sensitive config/database files.

## Existing database import

`database.sql` contains the complete schema. The web installer imports it automatically and creates the admin account with a securely hashed password.
