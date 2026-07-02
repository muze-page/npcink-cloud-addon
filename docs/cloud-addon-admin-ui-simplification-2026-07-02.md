# Cloud Addon Admin UI Simplification Closeout

Status: accepted

Date: 2026-07-02

Scope: `npcink-cloud-addon` WordPress admin page.

## Context

The Cloud Addon admin page had accumulated several small usability problems:

- the Site Knowledge tab mixed repeated titles, left/right action placement, and
  multiple visible action groups;
- diagnostics tables had narrow label columns and several fixed English labels
  in zh_CN;
- advanced maintenance copy was long enough to obscure the main action;
- runtime runs, raw status, account detail, and diagnostics were split into too
  many tabs;
- disabled or unverified states still exposed detail surfaces that were not
  actionable;
- some low-frequency details were visible by default even though Cloud owns the
  underlying service-plane detail;
- duplicated re-verify buttons made the primary action unclear;
- wide desktop layouts left too much unused space while tables still felt
  cramped.

The fix stays within the addon boundary: the page remains a connector/status
surface, not a router, workflow, billing, approval, Site Knowledge lifecycle, or
WordPress write control plane.

## Decisions

1. Keep verified navigation to four tabs:
   `Status`, `Site Knowledge`, `Troubleshooting`, and `Connection Management`.
2. Fold legacy `Details` into `Status`, fold `Runtime Runs` into
   `Troubleshooting`, and keep connection recovery/raw status in
   `Connection Management`.
3. Hide non-actionable verified-only detail when the site is not verified; show
   the connection path and only the minimal recovery surface.
4. When Site Knowledge delivery is locally disabled, keep the current state and
   Cloud cleanup path clear, but hide normal indexing controls that depend on
   delivery consent.
5. Keep low-frequency and explanatory material behind explicit disclosure or
   detail affordances.
6. Use `!` detail entries for short inline explanations instead of long prose in
   diagnostic rows.
7. Avoid nested `<details>` controls; nested disclosure creates unclear browser
   and keyboard semantics.
8. Translate fixed Cloud Addon admin labels in zh_CN, but do not translate
   dynamic ability metadata, provider/model identifiers, slugs, or contract IDs
   in this addon.
9. Keep one re-verify action in the connection summary. Other sections should
   link to the relevant Cloud detail surface instead of repeating refresh
   controls.
10. Use a slightly wider admin working area for diagnostics while preserving
    bounded line lengths.

## Implemented Changes

- Replaced boxed WordPress nav tabs with the shared Npcink AI tab style.
- Reduced verified tabs to:
  `状态`, `站点知识库`, `排查`, and `连接管理`.
- Removed duplicate body titles where the active tab already names the area.
- Moved Runtime Runs into the `排查` tab as a low-frequency disclosure.
- Moved sanitized raw status into `连接管理`.
- Kept Site Knowledge index actions conditional on local delivery consent, with
  delete remaining available as an explicit Cloud cleanup path.
- Shortened troubleshooting copy to state the surface boundary directly:
  connection/service status is read-only, and product actions, approvals, and
  WordPress writes stay outside the addon.
- Added headers to the Cloud-owned capability notes list.
- Replaced long capability explanations with focusable `!` detail entries.
- Removed nested row-level `<details>` controls from the capability list.
- Translated fixed zh_CN labels such as `Cloud 基础 URL`, `Cloud API 密钥`,
  `托管运行时`, `Cloud 网页搜索`, `Cloud 图像生成`, and related short status text.
- Widened the admin work area to reduce cramped diagnostic tables.
- Added static contracts to preserve the simplified tab model, non-nested
  detail affordance, translation coverage, and boundary wording.

## Browser Verification

The admin page was verified in a real local WordPress session at:

`https://magick-ai.local/wp-admin/admin.php?page=npcink-cloud-addon`

Observed state:

- `排查` shows table headers for diagnostics and capability notes.
- fixed zh_CN labels render in Chinese.
- the capability notes use `!` detail entries and no nested `<details>`.
- the detail popover appears on focus.
- the working panel renders at the wider target width.
- `连接管理` has one re-verify action and no overflowing disclosure tables.

## Boundary Notes

This change intentionally does not add:

- WordPress post/comment/media writes;
- Core proposal, approval, audit, replace, or rollback controls;
- Cloud billing truth, invoice/payment controls, or service operations controls;
- router, prompt, preset, model, ability, workflow, MCP, Agent Gateway, or
  scheduler control planes;
- Site Knowledge index lifecycle ownership in WordPress.

Cloud remains the owner of runtime detail, provider readiness, Site Knowledge
index execution, lifecycle, freshness policy, and service-plane diagnostics.

## Verification Commands

Run before handing off related changes:

```sh
composer run test:all
composer run check:wporg
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```
