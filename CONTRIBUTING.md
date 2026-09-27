# Contributing to BounceApp

Thanks for helping improve BounceApp.

## Before you start

BounceApp is intentionally small and keeps its core workflow simple:

**Paste URL → discover pages → capture → annotate → discuss → approve/reopen → share**

Changes should preserve the no-login public workspace experience unless a proposal explicitly introduces an optional alternative.

## Reporting bugs

When opening a bug report, include:

- what you expected to happen;
- what actually happened;
- steps to reproduce;
- PHP and MySQL/MariaDB versions;
- browser and operating system when the issue is UI-related;
- relevant server/PHP error output with credentials and private URLs removed.

Do not include database passwords, API keys, workspace bearer tokens, or other secrets.

## Proposing features

Please describe the user problem first, then the proposed solution. Small, focused changes are easier to review and maintain.

## Local/development setup

1. Clone the repository.
2. Create a MySQL/MariaDB database.
3. Copy `.env.example` to `.env`.
4. Configure the database values and application URL.
5. Import `database.sql` or use the browser installer.
6. Run the app through a PHP-capable local web server.
7. Test the full public workflow before submitting a change.

## Pull requests

Please keep pull requests focused and include:

- a clear summary;
- why the change is needed;
- files or areas changed;
- manual test steps;
- screenshots for visible UI changes when useful.

Before submitting, verify that:

- no secrets or production credentials are committed;
- public workspace links still work as intended;
- URL scanning does not allow private/local network targets;
- uploaded files remain type/size validated;
- database queries use prepared statements for untrusted values;
- output containing user-provided data is escaped appropriately;
- the installer and documented deployment flow still work.

## Coding style

The project currently uses straightforward PHP without a framework. Prefer readable, dependency-light code and reuse existing helpers where practical.

## Security issues

Do not publish an exploitable security issue in a public GitHub issue. Follow the process in [SECURITY.md](SECURITY.md).
