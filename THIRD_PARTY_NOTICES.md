# Third-party notices

FlexiList includes the following browser dependencies in `public/vendor/` so
that list data and access tokens are not exposed to a third-party CDN at
runtime:

- Vue.js 3.4.21, MIT License
- fast-json-patch 3.1.1, MIT License

Their complete license texts are stored in `public/vendor/licenses/`. The
expected SHA-256 hashes are checked by `php tools/verify_vendor.php`.

