# BounceApp server deployment

1. Create an empty MySQL database and database user in cPanel. Grant the user ALL privileges on that database.
2. Upload all files from this package into the target web root, for example `public_html/` or a subdomain document root.
3. Ensure PHP 8.1 or newer is selected and PDO MySQL + mbstring are enabled.
4. Make sure the `storage/` directory and the project root are writable during installation so the installer can create `.env` and `storage/installed.lock`. Normal `0755` directories are usually sufficient on cPanel.
5. Open `https://your-domain.com/install.php` in a browser.
6. Enter the application URL, database credentials, and the first administrator account.
7. After successful installation, sign in. The installer becomes locked automatically.
8. In Admin → Locations, add real storage locations. Set pricing/capacity/opening hours and optionally an externally hosted image URL.
9. In Admin → Settings, choose the currency symbol, support email, site name and footer text.
10. Keep `.env` private. The included `.htaccess` blocks browser access to `.env`, config, SQL and documentation files on Apache.

## Manual database install

`database.sql` contains the full schema. You may import it through phpMyAdmin, then copy `.env.example` to `.env` and fill in credentials. To create the first admin safely, using the web installer is recommended because it hashes the password correctly.

## Payment handling

No third-party payment credentials are embedded. Bookings default to `unpaid`; administrators can mark them `paid` or `refunded` from the booking manager. This keeps the package deployable before a payment provider is chosen.

## Security checklist

- Use HTTPS.
- Delete any old ZIP files from the public web root after extraction.
- Use a unique database password and a strong administrator password.
- Do not commit `.env`; it is already ignored by Git.
- Keep PHP/MySQL patched through your host.
