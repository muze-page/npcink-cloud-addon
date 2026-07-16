# Cloud Addon Contract Reuse Readiness - 2026-07-08

Status: active observation record

This record closes the `npcink-cloud-addon` observation pass after Core
confirmed `proposal_handoff`, Abilities Toolkit confirmed `ability_contracts`,
Adapter confirmed `execution_profiles`, and Workflow Toolbox confirmed
`product_surface`. The purpose is to decide whether this addon needs new
implementation work before the next project optimization pass.

## Scope

Cloud Addon's role in the current reuse stack is `signed_transport`:

- store the Cloud Base URL and customer-facing Cloud API Key wrapper;
- parse the key wrapper into server-side signing credentials without exposing
  split credential fields;
- sign named Cloud runtime/read requests with HMAC, trace, nonce, and
  idempotency headers;
- enforce endpoint allowlists and fail closed on unverified settings;
- expose bounded read-only status, entitlement, diagnostics, monitoring, Agent
  feedback, and runtime run detail;
- bridge Toolbox, WordPress AI connector scenes, Site Knowledge, media
  derivatives, image context evidence, observability, and Agent feedback
  through named transport seams;
- keep all write-like results as suggestion-only or Core-ready handoff data
  without applying WordPress writes.

The adjacent roles stay outside Cloud Addon:

| Role | Owner |
| --- | --- |
| `ability_contracts` | `npcink-abilities-toolkit` or another WordPress Abilities API provider |
| `proposal_handoff` | `npcink-governance-core` |
| `execution_profiles` | `npcink-ai-client-adapter` or another approved channel adapter |
| `product_surface` | `npcink-workflow-toolbox` |
| `runtime_detail` | `npcink-ai-cloud` |

## Reference-Plugin Learning

The useful outside reference work is already captured in
`docs/cloud-addon-reference-notes-2026-07.md`.

Mature WordPress connector plugins show patterns this addon can learn from:

- Jetpack: one clear account/service summary before feature detail;
- Site Kit by Google: setup, service status, and read-only projections should
  be visibly separate;
- WP Mail SMTP: save-and-test actions work when each test maps to one narrow
  transport contract;
- Health Check & Troubleshooting: diagnostics should separate site state,
  actionable checks, and low-frequency detail;
- WordPress Application Passwords: credential UX should expose human-safe
  wrapper concepts while keeping secret material server-side.

The addon should borrow those connector patterns, not their broader product
ownership. It must not copy module marketplaces, analytics dashboards, billing
consoles, provider administration, generic endpoint testers, workflow run
stores, scheduler truth, Site Knowledge lifecycle controls, or WordPress write
controls.

## Current Evidence

The current addon already has the thin connector hooks needed for contract
reuse:

- `README.md` positions the addon as a thin Cloud connector with read-only
  runtime/detail projections and no proposal, approval, queue, billing, router,
  prompt, preset, or write ownership;
- `docs/cloud-addon-boundary.md` records the local truth rule, endpoint rule,
  observability transport rule, and Site Knowledge change bridge rule;
- `docs/cloud-runtime-client-contract.md` keeps the low-level signed request
  helper private and endpoint-allowlisted;
- `docs/cloud-addon-complexity-budget.md` preserves security and boundary
  complexity while stopping product-control complexity;
- `docs/admin-surface-standard.md` keeps the admin page as connector/detail,
  not a second control plane;
- `docs/adapter-integration-seam.md` keeps Adapter integration on public PHP
  helper functions rather than duplicated Cloud credential storage;
- `Npcink_Cloud_Entitlement_Summary` already normalizes `contract_reuse` with
  `addon_role=signed_transport` and all expansion flags false;
- static and behavior tests already cover endpoint allowlists, forbidden legacy
  workflow routes, no direct WordPress writes, no split credential display, and
  read-only contract reuse projection.

## Active Observation Result

No new Cloud Addon endpoint, product tab, workflow runtime, queue, scheduler
truth, approval store, proposal store, billing truth, provider control plane, or
WordPress write executor is needed for this pass.

The current connector is sufficient for operators and clients to reuse existing
Cloud runtime/detail, Toolbox product-surface, Core proposal-handoff, Adapter
execution-profile, and Toolkit ability-contract roles:

```text
local plugin or Toolbox intent
-> Cloud Addon named transport helper
-> HMAC-signed allowlisted Cloud request
-> npcink-ai-cloud runtime/detail response
-> read-only projection, suggestion-only result, or Core-ready handoff
-> local Core/Adapter/Abilities path owns approval and WordPress writes
```

## Representative Ready Contracts

These existing addon contracts are enough to continue the reuse pass:

- `contract_reuse` entitlement projection;
- `signed_transport` addon role;
- `mak1_{base64url(json)}` Cloud API Key wrapper parsing;
- `POST /v1/runtime/execute`;
- `POST /v1/runtime/media/uploads`;
- `POST /v1/runtime/media/jobs`;
- `GET /v1/runs/{run_id}`;
- `GET /v1/runs/{run_id}/result`;
- `GET /v1/runs/nightly-inspection/recent`;
- `POST /v1/runs/{run_id}/retry`;
- `GET /v1/runtime/media/artifacts/{artifact_id}/download`;
- `POST /v1/runtime/media/artifacts/{artifact_id}/delivery-ack`;
- `GET /v1/entitlements/current`;
- `POST /v1/observability/plugin-events`;
- `GET /v1/observability/plugin-summary`;
- `POST /v1/agent-feedback/events`;
- `GET /v1/agent-feedback/summary`;
- `site_knowledge_change_bridge_status.v1`;
- `cloud_connector_runtime.v1`;
- `wordpress_operation.v1`;
- `cloud_connector_result.v1`;
- `image_context_evidence_request.v1`;
- `cloud_agent_feedback.v1`.

## Stop Rule

Stop and write a boundary note or ADR before Cloud Addon owns any of these:

- ability definitions, ability registries, workflow registries, MCP catalogs, or
  OpenClaw truth;
- Core proposal truth, approval truth, preflight truth, audit truth, rollback,
  or final WordPress writes;
- Adapter execution-profile truth or generic channel orchestration;
- Toolbox product workflows, fixed-button UX, recommendation UI, or local
  snapshot building;
- Cloud runtime implementation, provider routing, model/prompt/preset control,
  billing truth, quota enforcement truth, service operations, or key lifecycle
  operations;
- local workflow/task queues, retry queues, repair consoles, scheduler truth,
  or workflow engine truth;
- Site Knowledge vector/index lifecycle, freshness policy, collection
  management, embedding/vector provider settings, or deep operations controls;
- generic Cloud proxy routes, arbitrary endpoint testers, raw request/response
  logs, provider credentials, prompts, generated content, or media bytes.

## Next Recommendation

Move to `npcink-ai-cloud` next. The target there is to verify that Cloud owns
`runtime_detail` and hosted execution without becoming a second local product
control plane, ability registry, workflow registry, prompt/router control
plane, or WordPress write authority.

## Verification

For this documentation and static-contract pass:

```bash
composer run test:all
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob "*.php" --glob "!build/**" .
```

WordPress smoke is only required if the pass changes plugin activation,
settings persistence, REST/admin actions, runtime dispatch behavior, or local
WordPress integration.
