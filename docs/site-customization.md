# Site customization

FlexiList keeps reusable application code separate from operator-owned site
content. Copy `config/site.example.php` to `config/site.php`; that file is
ignored by Git.

Supported overrides include:

- application name and public backend URL;
- exact CORS origins;
- optional AI categorization;
- a custom template directory;
- a custom CSS file;
- optional routes such as privacy or imprint pages.

For a template named `landing`, FlexiList looks in the configured override
directory before `templates/`. German templates use the name
`landing.de.html`; the English fallback is `landing.html`. The same convention
applies to `index` and `guide`.

Available server-side template tokens are `{{ appName }}`, `{{ appVersion }}`
and `{{ backendUrl }}`. Vue expressions remain untouched.

Site-specific legal pages belong to the operator. They are intentionally not
distributed as part of FlexiList and should be reviewed for the actual hosting
environment and enabled services.

