# Cloud Bulk Article Run Addon Seam

Status: active planning seam.

This seam defines how `magick-ai-cloud-addon` may expose Cloud bulk article run
information to local Magick AI plugins without becoming a queue owner, approval
owner, proposal owner, or WordPress write owner.

## Position

The addon is the local Cloud connector. For bulk article work it may read
Cloud run status and result artifacts, then make those artifacts available to
local operator surfaces such as Toolbox.

The addon must not publish articles, create Core proposals, approve proposals,
call WordPress write endpoints, or maintain local bulk execution truth.

## Allowed Addon Role

The addon may:

- sign Cloud requests with verified site credentials;
- read a Cloud bulk article run through existing run/result read seams;
- normalize read-only run summary fields for local display;
- expose selected item artifacts to a local operator surface;
- keep Cloud status separate from Core proposal status;
- surface Cloud errors and expiry as local read-only detail.

The addon must not:

- start or own a local workflow/task queue;
- retry Cloud workers locally;
- mark an item as approved, preflighted, committed, published, or executed;
- write `wp_posts`, post meta, media, terms, or comments;
- store WordPress admin credentials or application passwords for Cloud;
- turn Cloud run status into Core proposal state;
- bypass `magick-ai-core` plan intake.

## Read Model

A read-only bulk article run summary should keep only bounded display fields:

- `run_id`
- `contract_version`
- `status`
- `requested_article_count`
- `completed_article_count`
- `failed_article_count`
- `created_at`
- `updated_at`
- `expires_at`
- `cost`
- `limits`
- `items`

Per-item summaries should expose:

- `item_id`
- `status`
- `title`
- `topic`
- `risk_level`
- `ready_for_local_review`
- `blocked_claim_count`
- `needs_human_input_count`
- `has_article_write_plan`
- `artifact_expires_at`

Article body content and research evidence may be returned from Cloud result
reads for a selected item, but the addon should treat them as runtime artifacts
for local review, not as approval or write instructions.

## Local Import Boundary

The local import path is:

1. Cloud produces `bulk_article_run_v1` run evidence and per-item artifacts.
2. Addon reads the run/result data through signed Cloud transport.
3. Toolbox or another local operator surface lets the operator select one or a
   small bounded set of ready items.
4. The selected item becomes the normal
   `magick-ai-toolbox/build-article-write-plan` handoff data.
5. Core receives that plan through
   `POST /wp-json/magick-ai-core/v1/proposals/from-plan`.
6. Adapter executes the allowed draft write only after Core approval and
   commit preflight, through WordPress Abilities API.

## Endpoint Boundary

This seam does not require a new addon public proxy or arbitrary Cloud path.
Until a Cloud bulk article endpoint is frozen, the addon should rely on the
existing named runtime read methods:

- `GET /v1/runs/{run_id}`
- `GET /v1/runs/{run_id}/result`

If Cloud later adds a named bulk article endpoint, the addon must add a
specific runtime client method and endpoint allowlist entry. It must not expose
a generic request helper or a local workflow run route.

## UI Boundary

The settings page may show at most a bounded read-only summary or link to a
local operator surface after settings verify. It must not become:

- a bulk publish console;
- a queue repair console;
- an approval queue;
- a proposal editor;
- a scheduler;
- a second Cloud control plane.

## Guardrail Phrase

In addon surfaces, "bulk article run" means Cloud runtime preparation and
artifact review. Final WordPress writes remain local, Core-governed,
preflighted, audited, and Abilities API based.
