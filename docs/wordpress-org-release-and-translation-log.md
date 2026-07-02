# WordPress.org Release and Translation Log

Status: active operational handoff.

Last updated: 2026-07-02.

This document records the WordPress.org release, assets, legal pages, and zh_CN
translation work completed for `npcink-cloud-addon`.

## Plugin Directory State

- WordPress.org plugin URL: `https://wordpress.org/plugins/npcink-cloud-addon/`
- Plugin slug: `npcink-cloud-addon`
- WordPress.org SVN URL: `https://plugins.svn.wordpress.org/npcink-cloud-addon/`
- Local SVN working copy: `build/wporg-svn`
- Release package: `build/npcink-cloud-addon.zip`
- Stable tag: `0.1.1`

The plugin has passed WordPress.org review and was submitted to SVN. Later asset
updates were submitted separately.

Known SVN revisions:

- `r3582010`: initial WordPress.org SVN submission with trunk/tag/assets.
- `r3582534`: regenerated WordPress.org icon and banner assets.
- `r3590121`: release `0.1.1` with refreshed Cloud status UI, entitlement
  cache reuse, WordPress AI connector integration, and zh_CN metadata.

## Release Verification

Before packaging or handing off release changes, run:

```sh
composer run test:all
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

For full WordPress.org release verification, use:

```sh
composer run release:verify
```

The local Plugin Check release command depends on the Local WordPress site and
the local WP-CLI/PHP paths configured in `composer.json`.

## WordPress.org Assets

Source images:

- `sj/source/icon-source.png`
- `sj/source/banner-source.png`

Generated WordPress.org assets:

- `sj/exports/wordpress-org/icon-128x128.png`
- `sj/exports/wordpress-org/icon-256x256.png`
- `sj/exports/wordpress-org/banner-772x250.png`
- `sj/exports/wordpress-org/banner-1544x500.png`

The same four files were copied into:

- `build/wporg-assets/`
- `build/wporg-svn/assets/`

The latest regenerated assets were committed to WordPress.org SVN as:

```text
r3582534 | Update WordPress.org icon and banner assets
```

Local Git commit:

```text
191ddb8 Update WordPress.org plugin assets
```

Screenshots were prepared earlier and are present in the WordPress.org assets
set:

- `screenshot-1.png`
- `screenshot-2.png`
- `screenshot-3.png`
- `screenshot-4.png`

## Legal and External Service Pages

The plugin discloses Npcink Cloud as an external service in `readme.txt`.

Public legal pages:

- Terms of Service: `https://cloud.npc.ink/terms/en/terms.html`
- Privacy Policy: `https://cloud.npc.ink/terms/en/privacy.html`
- Data Retention: `https://cloud.npc.ink/terms/en/data-retention.html`

Local legal site source:

- `sj/site`

The legal content was prepared with separate English and Chinese folders. English
is used for review-facing WordPress.org links; Chinese is for internal/local
reference.

Provided legal metadata:

- Company Legal Name: `麻城市牧泽世华工艺品`
- Contact email: `npc@npc.ink`
- Effective date: `2026年6月1日`
- Service domain: `https://cloud.npc.ink`

## zh_CN Translation Work

There are two separate translation surfaces:

- Plugin runtime/admin strings from PHP gettext calls.
- WordPress.org plugin directory/readme strings from GlotPress readme projects.

The in-plugin language files are:

- `languages/npcink-cloud-addon.pot`
- `languages/npcink-cloud-addon-zh_CN.po`
- `languages/npcink-cloud-addon-zh_CN.mo`

The plugin uses:

- Text domain: `npcink-cloud-addon`
- Domain path: `/languages`

Do not add `load_plugin_textdomain()` unless the existing project contract
changes; current tests assert the text domain/domain path behavior and absence
of explicit `load_plugin_textdomain()`.

Local translation refresh commands:

```sh
WP_CLI_BIN=/opt/homebrew/Cellar/wp-cli/2.12.0/bin/wp composer run i18n:pot
WP_CLI_BIN=/opt/homebrew/Cellar/wp-cli/2.12.0/bin/wp composer run i18n:update-po
WP_CLI_BIN=/opt/homebrew/Cellar/wp-cli/2.12.0/bin/wp composer run i18n:make-mo
```

Note: `/tmp/wp-cli.phar` was broken in this environment during the translation
work, so the Homebrew WP-CLI binary was used explicitly.

Local Git commit:

```text
5c886f5 Update Chinese translations
```

## WordPress.org Translation Imports

Generated import files are in:

- `build/translate-wordpress-org/npcink-cloud-addon-stable-zh_CN.po`
- `build/translate-wordpress-org/npcink-cloud-addon-dev-zh_CN.po`
- `build/translate-wordpress-org/npcink-cloud-addon-stable-readme-zh_CN.po`
- `build/translate-wordpress-org/npcink-cloud-addon-dev-readme-zh_CN.po`
- `build/translate-wordpress-org/npcink-cloud-addon-zh_CN-wordpress-org-imports.zip`

These are ignored by Git because `build/` is ignored. Regenerate them when
source strings change.

Import status confirmed on 2026-06-23:

- `Stable Readme`: 46 waiting translations.
- `Stable`: 217 waiting translations.
- `Development Readme`: 46 waiting translations.
- `Development`: 217 waiting translations.

Total zh_CN waiting/fuzzy count:

```text
526
```

This total matches the four uploaded files:

```text
46 + 217 + 46 + 217 = 526
```

Important interpretation:

- `Waiting` means uploaded successfully but not approved.
- WordPress.org project percentages still show `0%` until translations become
  `Current`.
- The Chinese plugin directory page will not show the translations until the
  `Stable Readme` strings are approved.

## GlotPress Import URLs

Use these URLs when uploading refreshed PO files:

- Stable Readme:
  `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable-readme/zh-cn/default/import-translations/`
- Stable:
  `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable/zh-cn/default/import-translations/`
- Development Readme:
  `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev-readme/zh-cn/default/import-translations/`
- Development:
  `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev/zh-cn/default/import-translations/`

Use format:

```text
Portable Object Message Catalog (.po/.pot)
```

If GlotPress offers an import status option and the account is not already a PTE,
use `Waiting`.

## PTE Follow-Up History

PTE means Project Translation Editor. PTE access was requested for:

- Plugin: `npcink-cloud-addon`
- Locale: `Chinese (China) / zh_CN`
- WordPress.org username: `muze233`

The 2026-07-02 closeout below records the approval and completion state. Keep
the request template in this section as historical context only.

## 2026-07-02 zh_CN PTE Closeout

On 2026-07-02, the WordPress.org Polyglots notification confirmed that
`muze233` was added as a Project Translation Editor for:

```text
Plugins -> Npcink Cloud Addon
Locale: Chinese (China) / zh_CN
Project: https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon
Granted by: shenyanzhi
```

Interpretation:

- PTE access allows approving and importing translations for this plugin's
  zh_CN translation sets.
- It does not change this plugin's product boundary or runtime behavior.
- WordPress.org addon translation work should stay scoped to the
  `npcink-cloud-addon` text domain and the plugin readme projects.
- The separate local WordPress AI plugin compatibility shim remains a bounded,
  admin-only `ai` text-domain shim for fixed UI strings only. Dynamic ability
  metadata belongs in the plugin that registers the ability.

### Local Preparation

Before the online submission, the local zh_CN language file was checked:

```sh
msgfmt --statistics -o /tmp/npcink-cloud-addon-translation-submit/check.mo \
  /tmp/npcink-cloud-addon-translation-submit/npcink-cloud-addon-zh_CN-glotpress-import.po
```

Result:

```text
435 translated messages.
```

The temporary GlotPress import source was:

```text
/tmp/npcink-cloud-addon-translation-submit/npcink-cloud-addon-zh_CN-glotpress-import.po
```

Do not commit that temporary file. It is only an upload artifact derived from the
local `languages/npcink-cloud-addon-zh_CN.po`.

### Online Submission

The Development default translation set was opened at:

```text
https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev/zh-cn/default/
```

The import form was submitted with:

```text
Format: Auto Detect
Status: Current
File: npcink-cloud-addon-zh_CN-glotpress-import.po
```

GlotPress reported:

```text
264 translations were added
```

After import, two remaining untranslated fixed UI strings were manually added:

| Original | Translation |
| --- | --- |
| `Entitlement and package are read from Cloud into a short local summary cache. Re-verifying refreshes the latest Cloud summary.` | `权益和套餐会从 Cloud 读取到简短的本地摘要缓存中。重新验证会刷新最新的 Cloud 摘要。` |
| `Details` | `详情` |

Three waiting fixed UI translations were reviewed and approved:

| Original | Translation |
| --- | --- |
| `not returned by Cloud` | `Cloud 未返回` |
| `Advanced Information` | `高级信息` |
| `Advanced` | `高级` |

One GlotPress warning was resolved by changing the first translation above to:

```text
未从 Cloud 返回
```

The warning was only about the original string starting with lowercase `not`
while the previous translation started with uppercase `Cloud`; it was not a
placeholder or format-string error.

### Readme Completion

The plugin readme translation sets are separate from the PHP gettext/default
translation sets. Do not upload the addon PO file into readme projects.

Development Readme had one untranslated changelog string:

| Original | Translation |
| --- | --- |
| `Refresh Cloud connection status actions, entitlement summary caching, WordPress AI connector integration, zh_CN strings, and release packaging checks.` | `更新 Cloud 连接状态操作、权益摘要缓存、WordPress AI 连接器集成、zh_CN 字符串和发布打包检查。` |

After this was saved, Stable Readme synchronized to the same completed state.

### Final WordPress.org State

The final project overview row for zh_CN was:

```text
Chinese (China)    100%    100%    100%    100%    0
```

Meaning:

- Development: 100%
- Development Readme: 100%
- Stable: 100%
- Stable Readme: 100%
- Waiting/Fuzzy: 0
- Untranslated: 0
- Warnings: 0

The verified GlotPress pages were:

- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev/zh-cn/default/`
- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable/zh-cn/default/`
- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev-readme/zh-cn/default/`
- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable-readme/zh-cn/default/`
- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/`

### Directory Page Behavior

The global WordPress.org plugin page remains the English directory page:

```text
https://wordpress.org/plugins/npcink-cloud-addon/
```

The Chinese-localized directory page is:

```text
https://cn.wordpress.org/plugins/npcink-cloud-addon/
```

After GlotPress reaches 100%, the remaining work is to wait for WordPress.org to
generate and distribute language packs and for plugin-directory caches to
refresh. If the Chinese page still shows English after caches settle, check the
Stable Readme translation set first, because the localized plugin directory page
uses readme translations rather than the runtime `.po` file alone.

For local language-pack verification after generation, use the local WordPress
site:

```sh
wp language plugin update npcink-cloud-addon \
  --path="/Users/muze/Local Sites/npcink/app/public"
```

or use the WordPress admin updates screen to refresh translations.

### Follow-Up

- Keep WordPress.org translations at 100% when new release strings are added.
- Re-run local i18n refresh before the next release package.
- Keep the AI plugin compatibility shim audit separate from WordPress.org addon
  translation work.
- If the WordPress.org plugin directory does not localize after the language
  pack/cache window, verify Stable Readme first, then the generated language
  pack state.

Suggested request title:

```text
PTE Request for Npcink Cloud Addon
```

Suggested request body:

```text
Hello Polyglots team,

I am the plugin author of Npcink Cloud Addon:
https://wordpress.org/plugins/npcink-cloud-addon/

I have imported Chinese (China) translations for the following projects:

- Stable
- Stable Readme
- Development
- Development Readme

There are currently 526 zh_CN strings waiting for review.

I would like to request Project Translation Editor access for this plugin for
#zh_CN, so I can review and maintain the Chinese translations.

WordPress.org username: muze233

Thank you.
```

Request location:

```text
https://make.wordpress.org/polyglots/
```

## Browser Automation Note

Chrome extension automation was not available during the 2026-07-02 closeout,
but the logged-in Playwright browser session could access GlotPress as
`Howdy, Npcink` and submit file inputs. The Development default PO import,
manual string saves, waiting approvals, warning fix, and readme completion were
all completed through that browser session.

Do not assume future upload failures are file-format issues. Validate PO files
locally with `msgfmt --check --statistics` or `msgfmt --statistics` before
debugging GlotPress/browser behavior.
