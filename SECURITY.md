# Security policy

## Supported version

Until the first stable release, security fixes are applied to the `main`
branch. After version 1.0, supported release lines will be listed here.

## Reporting a vulnerability

Please use GitHub's private vulnerability reporting feature for this
repository. Do not open a public issue for suspected vulnerabilities and do
not include real list contents, access tokens, API keys, or server logs in a
report.

Include the affected version, reproduction steps, impact, and any suggested
mitigation. A maintainer should acknowledge a report within seven days.

## Operator responsibilities

FlexiList protects a list with a bearer token contained in the share link.
Anyone with the complete link can read and modify that list. Operators must
serve FlexiList over HTTPS, expose only the `public/` directory, keep `data/`
and `var/` private, configure backups, and apply rate limits at the reverse
proxy in addition to the built-in limits.

