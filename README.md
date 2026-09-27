# BounceApp

BounceApp is a lightweight visual website feedback tool built for fast, account-free collaboration. Paste a public website URL, let BounceApp discover pages and capture screenshots, mark exact areas that need changes, discuss them, and share one workspace link with clients or collaborators.

## Why BounceApp

Traditional website feedback often gets scattered across screenshots, email, chat, and documents. BounceApp keeps visual change requests attached to the exact page and screenshot they refer to, without forcing reviewers to create an account.

## Features

- **No login or signup required** for public feedback workspaces.
- **Website scanning** from a public URL with sitemap and homepage-link discovery.
- **Automatic screenshot capture** with a provider fallback chain.
- **Visual annotations** for marking exact areas on a captured page.
- **Structured change requests** with titles, descriptions, replacement text, and optional reference links.
- **Comments and threaded discussion** around requested changes.
- **Status workflow** including open, in progress, clarification, done, approved, and reopened.
- **Shareable public workspace links** backed by long random bearer tokens.
- **Manual page and screenshot upload fallback** when automated capture is unavailable.
- **Persistent MySQL/MariaDB storage** for projects, pages, captures, annotations, comments, and activity.
- **Security-minded URL scanning** that rejects localhost, private, and reserved IP targets.

## How it works

1. Paste a public website URL.
2. BounceApp creates a project and a unique public workspace token.
3. It discovers pages from sitemaps and/or homepage links.
4. The homepage is captured automatically when possible.
5. Open a page and draw over the exact area that needs a change.
6. Add the request, replacement text, attachments, or reference URL.
7. Share the workspace link with a client, designer, developer, or reviewer.
8. Collaborators can comment, update statuses, approve, or reopen requests without creating an account.

## Screenshot capture

BounceApp uses a fallback chain:

1. If `PAGESPEED_API_KEY` is configured, Google PageSpeed Insights is tried first.
2. If PageSpeed is unavailable, rate-limited, or not configured, BounceApp falls back to the keyless Thum.io screenshot endpoint.
3. If automatic providers fail, a manual JPG/PNG/WEBP screenshot upload remains available.

## Quick start

### Requirements

- PHP 8.1+
- MySQL or MariaDB
- PDO MySQL
- cURL
- fileinfo
- mbstring
- Apache/cPanel is recommended for the current deployment flow

### Installation

1. Clone or download this repository.
2. Create an empty MySQL/MariaDB database and database user.
3. Upload the project to your web root.
4. Make sure the project root and `storage/` are writable during installation.
5. Open `https://your-domain/install.php`.
6. Enter the application URL and database credentials.
7. Complete installation, then paste a public website URL to create your first workspace.

For additional deployment notes, see [DEPLOYMENT.md](DEPLOYMENT.md).

## Configuration

Copy `.env.example` to `.env` and configure the values required for your environment.

| Variable | Purpose |
| --- | --- |
| `APP_NAME` | Application name |
| `APP_URL` | Public base URL of the BounceApp installation |
| `APP_TIMEZONE` | Application timezone |
| `DB_HOST` | MySQL/MariaDB host |
| `DB_PORT` | Database port |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` | Database username |
| `DB_PASSWORD` | Database password |
| `PAGESPEED_API_KEY` | Optional Google PageSpeed API key for screenshot capture |
| `SCAN_MAX_PAGES` | Maximum number of pages discovered per scan |

Never commit a real `.env` file or production credentials.

## Public-link security model

BounceApp intentionally supports no-account collaboration. Each project receives a long random bearer token, and possession of the workspace link grants access to that project.

Treat a workspace URL like a private collaboration link:

- share it only with intended reviewers;
- use HTTPS in production;
- keep `.env` private;
- remove deployment archives from the public web root after installation;
- rotate or invalidate access if a workspace URL is accidentally exposed.

The scanner rejects localhost/private/reserved IP destinations to reduce SSRF risk, and uploaded files are MIME/type and size checked.

## Project structure

- `index.php` — main public application and workspace flow
- `bootstrap.php` — database, security, capture, scanning, upload, and shared helpers
- `config.php` — environment-backed configuration
- `database.sql` — database schema
- `install.php` — browser-based installation
- `assets/` — frontend CSS/JavaScript assets
- `public/` — public uploads and runtime assets
- `storage/` — installation/runtime state

## Project status

BounceApp is under active development. The current focus is keeping the no-login workflow simple while improving capture reliability, collaboration, deployment, security, and contributor experience.

## Contributing

Contributions, bug reports, and improvement ideas are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting changes.

For security issues, please follow [SECURITY.md](SECURITY.md) instead of opening a public issue.

## Roadmap

Areas planned for continued development include:

- more reliable screenshot provider options;
- stronger workspace access controls while preserving the no-account workflow;
- richer annotation and review tools;
- export and reporting improvements;
- automated tests and CI checks;
- improved deployment and upgrade tooling.

## Maintainer

Maintained by [XaqibRoXx](https://github.com/XaqibRoXx).
