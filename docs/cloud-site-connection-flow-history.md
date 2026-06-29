# Cloud Site Connection Flow History

Status: historical summary for the 2026-06-29 Cloud connection UX change.

## Background

The original Cloud Addon settings page exposed too much credential-oriented
detail to WordPress administrators. A verified connection could show split
credential identifiers such as Site ID and Key ID, and the Advanced form still
treated copy/paste Cloud API Key entry as a normal recovery path.

The product direction changed after the Cloud Portal side hid direct key
management behind site connection management. The Cloud repository shipped that
side of the contract in commit `d6a5fd7 Hide portal key management behind site
connection`.

The resulting product rule is:

- users manage site connections in Cloud Portal;
- runtime keys are implementation details between Cloud and the WordPress addon;
- the addon stores and parses the returned Cloud API Key wrapper only so it can
  sign runtime requests;
- split signing credentials must not be shown, edited, logged, or exposed in
  debug/admin UI.

## Decisions

Cloud Portal is now the primary connection and reconnection entry. The WordPress
addon opens the Cloud-side site authorization flow for the current WordPress
site, including a `return_url` and one-time state token.

After Cloud redirects back with `code` and `state`, the addon validates the
state and exchanges the code through:

```text
POST /portal/v1/addon-connections/exchange
```

The exchange returns a Cloud API Key wrapper. The addon saves the wrapper's
decoded signing credentials internally with the Cloud Base URL and immediately
runs the same signed verification path used by manual save-and-verify.

Manual credential entry remains only as an Advanced recovery fallback. It accepts
only Cloud-issued `mak1_` wrapper values. Raw split JSON credential payloads are
rejected by the admin save path.

## UI Outcome

The normal WordPress admin flow is:

1. Click the Cloud connection action in `Npcink > Cloud Addon`.
2. Complete site authorization in Cloud Portal.
3. Return to WordPress automatically.
4. See a connected/verified status if signed verification succeeds.

The default verified summary shows connector status, Cloud Base URL, last
verification time, entitlement availability, and package detail. It does not
show Site ID, Key ID, secret, or the stored Cloud API Key wrapper.

Account switching is handled by `Change connection in Cloud`, not by pasting a
new key into the default page.

## Security And Boundary Constraints

The addon remains a Cloud connector, not a second Cloud control plane. It must
not own account management, key lifecycle UI, billing truth, proposal approval,
workflow execution, scheduler truth, router configuration, prompt ownership, or
WordPress writes.

Sensitive values must stay out of operator-facing surfaces:

- do not print `cloud_api_key`;
- do not print or log the stored secret;
- do not display Site ID or Key ID in the settings page;
- redact `mak1_...` and `Bearer ...` values from admin notices;
- do not add split credential input fields.

The addon may continue to keep `site_id`, `key_id`, and `secret` internally
because the runtime client needs them for HMAC request signing.

## Implemented In Addon

The addon-side implementation landed in:

- `0f41aa5 Simplify cloud addon settings page`
- `de7c2e0 Finalize Cloud authorization connection flow`

Important implementation points:

- `includes/class-cloud-settings-page.php` handles the authorization callback,
  exchanges `code/state`, saves the returned wrapper, and immediately verifies.
- `includes/class-cloud-addon-settings.php` parses only `mak1_` wrappers through
  the admin payload path and rejects raw split JSON credentials.
- `tests/static-contracts.php` asserts the settings page no longer contains
  `Site ID`, `Key ID`, or `JSON key` UI strings.
- `tests/behavior-media-derivative.php` verifies local HTTP base URL handling
  while rejecting split credential JSON in admin payloads.

## Verification Record

The implementation was verified with:

```bash
composer run test:all
composer run check:wporg
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
composer run ai:i18n:audit
```

`composer run ai:i18n:audit` exited successfully while continuing to report the
pre-existing external WordPress AI plugin translation candidate list.

## Future Guardrails

Future changes to the settings page should preserve these rules:

- default users should connect through Cloud Portal, not by copying keys;
- Advanced recovery must remain low-frequency and wrapper-only;
- no split credential labels should return to the default UI;
- any new Cloud connection endpoint must stay connector-scoped and must not
  become a local control plane;
- docs and tests should be updated together whenever the connection contract
  changes.
