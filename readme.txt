=== Magick AI Cloud Addon ===
Contributors: magick-ai
Tags: magick ai, cloud, hosted runtime
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Thin Cloud connector for Magick AI hosted runtime access, signing, health checks, and entitlement summaries.

== Description ==

Magick AI Cloud Addon connects a local WordPress site to `magick-ai-cloud`.

It stores the Cloud Base URL and Cloud API Key, parses Cloud-issued keys, signs runtime requests, probes health, and reads Cloud entitlement summaries.

It does not execute WordPress writes, approve proposals, own billing truth, or manage prompts, routers, presets, queues, schedulers, or workflow engines.

== External Services ==

This plugin connects to the Magick AI Cloud service configured by the site administrator through the Cloud Base URL setting.

The plugin contacts the configured Cloud service only after an administrator enters a Cloud Base URL and Cloud API Key, saves the settings, verifies the connection, or a local Magick AI component explicitly uses the Cloud runtime client.

Requests may include the configured site identifier, key identifier, request timestamp, nonce, trace identifier, idempotency key, HMAC signature headers, runtime request payloads supplied by local Magick AI components, and read-only requests for health, run status, run result, usage statistics, and entitlement summaries. The stored Cloud API Key secret is used server-side for request signing and is not printed in wp-admin.

The configured Cloud service is responsible for its own privacy policy, terms of service, data retention, and account/key issuance. Site administrators should only connect this plugin to a Cloud service whose terms and privacy policy they have reviewed.

== Installation ==

1. Place this directory in `wp-content/plugins/magick-ai-cloud-addon`.
2. Activate `Magick AI Cloud Addon`.
3. Open `Magick AI > Cloud Addon`.
4. Enter Cloud Base URL and Cloud API Key.
5. Click `Save and Verify`.

== Frequently Asked Questions ==

= Does this plugin create Cloud API Keys? =

No. Keys are issued by Magick AI Cloud.

= Does this plugin display the Cloud secret? =

No. The secret is stored for server-side signing only and is never printed on the settings page.

= Does this plugin write Cloud recommendations into WordPress? =

No. Final WordPress writes must go through local Core proposal, preflight, approval, and apply paths.

== Changelog ==

= 0.1.0 =

Initial standalone connector skeleton.
