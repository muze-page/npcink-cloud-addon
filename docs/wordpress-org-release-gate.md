# WordPress.org Release Gate

Status: active release gate.

Before uploading this plugin to WordPress.org, run:

```sh
composer release:verify
```

Before packaging any release build, confirm the Cloud Base URL environment
contract:

- local development uses `http://localhost:8010/`;
- the packaged plugin's non-local default uses `https://cloud.npc.ink/`;
- no release package should be verified against the local Cloud URL unless the
  work is explicitly a local smoke test and not a distributable upload.

This release gate exists because functional tests and local smoke tests can pass
while WordPress.org rejects the package for review-policy issues.

The local `check:wporg` guard blocks recurring review problems:

- direct `wp-admin/includes/*` path construction, except the common
  `upgrade.php` activation helper for `dbDelta()`;
- admin request parameters read directly from `$_GET`;
- inline admin CSS or JS emitted from PHP;
- raw `<script>` or `<style>` tags in PHP admin views.

When WordPress.org sends a review email, decode the current top-level message,
extract every cited file and line, fix the whole pattern class, and add a local
guard when the pattern is statically checkable.
