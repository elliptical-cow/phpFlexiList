# Security model

Each list has two server-generated values:

- a 96-bit random list identifier used in the URL query;
- a 256-bit random bearer token carried in the URL fragment.

URL fragments are not sent in HTTP requests. The frontend reads the token and
sends it in the `Authorization` header. Only a SHA-256 hash of the token is
stored on the server. The complete share link grants read and write access;
there are currently no separate read-only roles and no token recovery flow.

The built-in file-based limits are a defensive baseline, not a replacement for
reverse-proxy limits. Production operators should also limit request rates and
body sizes at the web server, monitor storage, and keep the application behind
HTTPS.

AI categorization is disabled by default. Enabling it sends selected list
contents to the configured provider and creates a cost-abuse risk. Operators
must configure their own provider terms, privacy information, quotas, and
spending limits.

