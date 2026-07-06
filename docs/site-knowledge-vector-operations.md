# Site Knowledge Vector Operations

Status: active.

Npcink Cloud Addon is the WordPress-side connector for Cloud Site Knowledge. It
does not own vector indexing or vector database lifecycle.

## Local Permission

The settings page actions that request content index work require
`manage_options`, a valid nonce, and verified Cloud settings. Editor-facing
Toolbox usage may consume search results, but it must not trigger refresh,
rebuild, delete, or collection lifecycle actions.

The `Enable Site Knowledge delivery` setting is local delivery consent only. It
controls whether this WordPress site sends future public content changes and
administrator start/rebuild requests to Cloud Site Knowledge. It is not a vector
collection lifecycle switch.

## Allowed Addon Operations

- Listen for published `post` and `page` changes.
- Listen for approved comment changes attached to public posts or pages.
- Buffer affected public ids for bounded delivery durability.
- Let a present administrator enable or disable local Site Knowledge delivery
  consent from the WordPress Site Knowledge tab.
- Send `site_knowledge_sync.v1` with `sync_mode=refresh` for ordinary public
  content delivery only when local delivery is enabled.
- Let a present administrator explicitly start indexing from bounded public
  WordPress content only when local delivery is enabled.
- Let a present administrator explicitly request `sync_mode=rebuild` after
  typing `REBUILD` only when local delivery is enabled; Cloud clears and
  rebuilds the index.
- Let a present administrator explicitly request `sync_mode=delete` after
  typing `DELETE`; Cloud deletes the site index and WordPress content remains
  unchanged.
- Forward known Toolbox Site Knowledge search, status, and refresh contracts
  through `POST /v1/runtime/execute`.
- Show shallow connector state, buffered public changes, last delivery, last
  error, next flush, last local index action, and a link to Cloud Site
  Knowledge detail.

The status projection returned by
`npcink_cloud_addon_site_knowledge_change_bridge_health()` is
`site_knowledge_change_bridge_status.v1`. Consumers should expose it as
`change_bridge` and prefer `buffer_count` for the bounded local delivery-buffer
depth. That count is not vector queue truth, index freshness truth, collection
lifecycle truth, or Cloud diagnostics truth.

The bridge health projection also carries `ownership` and `truth_boundaries`
maps that mirror the Cloud `site_knowledge_status.v1` and
`site_knowledge_sync.v1` owner contract. Local consumers may render those maps
as read-only boundary detail: Cloud owns index execution, index lifecycle,
freshness, vector storage, embedding execution, and diagnostics; Cloud Addon
owns the delivery bridge; the local WordPress host owns source content, local
approval, and final WordPress writes. These maps are not settings, not
collection lifecycle controls, and not a Cloud-to-WordPress write grant.

## Forbidden Addon Operations

The addon must reject or omit:

- collection create, update, migrate, or delete controls
- embedding provider settings
- Qdrant or other vector database endpoint settings
- embedding dimensions and collection names
- stale-index policy controls
- local vector stores
- local indexing queues or scheduler truth
- direct WordPress writes from Site Knowledge results

## Content Admission

Refresh payloads may contain only public site content:

- published posts and pages;
- bounded title, excerpt/content, permalink, modified time, post id, and post
  type fields;
- approved comments attached to public posts or pages;
- truncation and payload-limit metadata.

Refresh payloads must not contain:

- drafts, private posts, password-protected posts, or trashed content;
- user emails, orders, memberships, form submissions, support tickets, or
  private CRM data;
- provider credentials, API keys, cookies, nonces, tokens, or signing fields;
- Core approval records, proposal records, or audit truth;
- final WordPress write instructions.

Cloud Site Knowledge remains the owner for embedding, vector storage,
collection lifecycle, freshness policy, re-indexing, rerank, quota, and deep
diagnostics.

Disabling local delivery stops future public content delivery and disables
start/rebuild requests. It does not delete existing Cloud index data. Delete site
index remains available as an explicit cleanup path after typing `DELETE`.
