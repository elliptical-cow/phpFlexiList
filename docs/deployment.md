# Deployment

## Requirements

- PHP 8.1 or newer
- PHP JSON and cURL extensions
- HTTPS in production
- a writable private `data/` directory
- a writable private `var/` directory

The web server document root must be the repository's `public/` directory.
Never expose `config/`, `data/`, `src/`, `tests/`, or `var/` directly.

## Apache

Enable `mod_rewrite`, allow `.htaccess` overrides for `public/`, and point the
virtual host's `DocumentRoot` at `/path/to/flexilist/public`.

## Development server

From the repository root:

```sh
php -S 127.0.0.1:8080 -t public public/index.php
```

Then open `http://127.0.0.1:8080`.

## Production configuration

Copy `config/site.example.php` to the ignored `config/site.php`, or set
`FLEXILIST_SITE_CONFIG` to an absolute configuration path outside the checkout.
Secrets should be injected through the environment.

Useful environment variables:

| Variable | Purpose |
| --- | --- |
| `FLEXILIST_APP_NAME` | Public application name |
| `FLEXILIST_BACKEND_URL` | Public origin, for example `https://lists.example.com` |
| `FLEXILIST_DATA_DIR` | Private persistent list storage |
| `FLEXILIST_RATE_LIMIT_DIR` | Writable rate-limit state directory |
| `FLEXILIST_SITE_TEMPLATES` | Site-specific template directory |
| `FLEXILIST_SITE_CSS` | Site-specific stylesheet |
| `OPENROUTER_API_KEY` | Optional OpenRouter key |
| `OPENROUTER_MODEL` | Optional model override |
| `FLEXILIST_AUTO_CATEGORIZATION` | Enable optional AI categorization |

Back up both the configured data directory and its `.access/` subdirectory.
Losing `.access/` makes the corresponding lists inaccessible.
