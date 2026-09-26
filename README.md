# FlexiList

FlexiList is a small self-hosted web application for hierarchical,
collaborative checklists. It runs on PHP without a database, works on desktop
and mobile browsers, supports JSON Patch updates, and can optionally categorize
items through OpenRouter.

This is the reusable upstream application. Branding, domains, legal pages,
production configuration, and deployment credentials belong in a separate
site repository or server configuration.

## Features

- Nested categories, notes, reordering, search suggestions, and JSON export
- English and German default templates
- File-based storage with atomic writes
- Server-generated list IDs and bearer tokens
- Optional, disabled-by-default AI categorization
- Configurable templates, CSS, routes, branding, and CORS origins
- No runtime dependency on third-party JavaScript CDNs

## Quick start

Requirements are PHP 8.1+, JSON, and cURL.

```sh
php -S 127.0.0.1:8080 -t public public/index.php
```

Open `http://127.0.0.1:8080` and create a list. The complete generated URL is
the access credential: anyone who has it can edit the list.

For production, point the web server document root at `public/`, use HTTPS,
and keep `data/`, `var/`, and configuration files outside public access. See
[deployment](docs/deployment.md) and the [security model](docs/security-model.md).
Existing installations that used the old list-ID-only access model must run the
documented legacy access migration before switching production traffic.

## Configuration and customization

Defaults work for local development and live in `config/defaults.php`. Use the
ignored `config/site.php` or an external file selected with
`FLEXILIST_SITE_CONFIG` for a deployment. Secrets are read from environment
variables. On FTP-only shared hosting, the ignored `config/secrets.php` is an
alternative. Secrets must never be committed or placed below the web root.

See [site customization](docs/site-customization.md) for templates, branding,
CSS, and optional legal-page routes.

## Development

```sh
composer check
npm test
```

VS Code and GitHub Codespaces can use the included Dev Container. After the
container is created, start the local server with:

```sh
php -S 0.0.0.0:8080 -t public public/index.php
```

Port 8080 is forwarded automatically. The container uses PHP 8.3, Composer,
and Node.js 20 and runs the unit tests after creation.

The test suite has no application runtime dependencies. CI runs PHP linting and
tests on supported PHP versions, frontend tests on Node.js, and checksum
verification for vendored browser libraries.

## Security and privacy

AI categorization is disabled by default. Operators enabling it are responsible
for provider configuration, privacy information, quotas, and spending limits.
Please report vulnerabilities according to [SECURITY.md](SECURITY.md).

## License

FlexiList is available under the [Apache License 2.0](LICENSE). Bundled
third-party licenses are listed in
[THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
