# Site Knowledge Full-Index Delivery

`npcink-cloud-addon` delivers an administrator-requested `start` or `rebuild`
across the site's bounded public `post` and `page` corpus instead of sampling
only the most recently modified items.

The Addon remains a transport bridge:

- WordPress is the source-content owner and selects at most 10,000 published
  post/page IDs for one explicit administrator action.
- The Addon sends batches of at most 200 public manifests through the existing
  `POST /v1/runtime/execute` contract.
- The administrator request only records one bounded local delivery cursor and
  schedules the existing Site Knowledge flush hook. Each Cron invocation sends
  or polls at most one Cloud batch, so a large corpus does not hold the
  administrator HTTP request open across as many as 50 Cloud calls.
- A rebuild's first batch uses `sync_mode=rebuild` with empty `post_ids`, so
  Cloud clears the site's previous index before indexing that batch. Remaining
  batches use `sync_mode=refresh`.
- Each batch has a stable idempotency key. The cursor records only selected
  public IDs, current batch, bounded attempts, an optional Cloud run ID, and a
  bounded poll generation. One Cloud run may be polled at most 120 times before
  consuming a delivery attempt; three exhausted attempts block the cursor.
  This does not create a local index job, queue, scheduler truth, workflow
  runtime, or vector truth.
- A second start, rebuild, delete, or automatic maintenance request cannot
  overwrite an active full-index cursor. Ordinary content changes remain in the
  existing bounded change buffer and are scheduled again after the full-index
  delivery completes.
- Cursor advancement and deletion are conditional on the exact version read by
  the Cron callback, preventing an older overlapping callback from mutating a
  newer administrator request.
- Public text is capped at 1,800 UTF-8 bytes per document. Incidental embedded
  URLs, email addresses, and phone-like sequences are removed before delivery;
  the canonical public permalink remains a separate allowlisted field.

Cloud continues to own queued execution, index lifecycle, vector storage,
embedding execution, freshness, and diagnostics. Every request remains
`suggestion_only` and cannot write WordPress content.
