# Security Policy

Security reports are welcome and should be handled privately when they contain information that could put deployed BounceApp instances at risk.

## Supported version

The latest code on the `main` branch is the currently maintained version.

## Reporting a vulnerability

Please contact the repository maintainer privately through the contact options available on the maintainer's GitHub profile rather than opening a public issue for an unpatched vulnerability.

Include, when possible:

- a concise description of the issue;
- affected file or endpoint;
- reproduction steps;
- impact;
- any suggested mitigation.

Do not include real credentials, private workspace tokens, or data belonging to third parties.

## Security model notes

BounceApp uses public bearer-link workspaces by design. Anyone who has a valid project workspace link can access that workspace without creating an account. Operators should treat workspace URLs as sensitive collaboration links.

Production deployments should:

- use HTTPS;
- keep `.env` inaccessible from the web;
- use a dedicated least-privilege database user;
- keep PHP and database software patched;
- remove deployment archives after extraction;
- restrict writable directories to what the application needs;
- avoid exposing private workspace URLs in logs, screenshots, or public issue reports.

The scanner includes checks intended to reject localhost, private, and reserved IP targets. Changes affecting URL fetching, redirects, DNS resolution, uploads, authentication/access control, or token generation deserve extra security review.
