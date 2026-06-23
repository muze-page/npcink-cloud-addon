# WordPress.org Release and Translation Log

Status: active operational handoff.

Last updated: 2026-06-23.

This document records the WordPress.org release, assets, legal pages, and zh_CN
translation work completed for `npcink-cloud-addon`.

## Plugin Directory State

- WordPress.org plugin URL: `https://wordpress.org/plugins/npcink-cloud-addon/`
- Plugin slug: `npcink-cloud-addon`
- WordPress.org SVN URL: `https://plugins.svn.wordpress.org/npcink-cloud-addon/`
- Local SVN working copy: `build/wporg-svn`
- Release package: `build/npcink-cloud-addon.zip`
- Stable tag: `0.1.0`

The plugin has passed WordPress.org review and was submitted to SVN. Later asset
updates were submitted separately.

Known SVN revisions:

- `r3582010`: initial WordPress.org SVN submission with trunk/tag/assets.
- `r3582534`: regenerated WordPress.org icon and banner assets.

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

## PTE Follow-Up

PTE means Project Translation Editor. For this plugin, request PTE access for:

- Plugin: `npcink-cloud-addon`
- Locale: `Chinese (China) / zh_CN`
- WordPress.org username: `muze233`

PTE access would allow the account to approve this plugin's zh_CN strings from
`Waiting` to `Current`, without waiting for another zh_CN translation editor.

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

Chrome automation could open the GlotPress import pages and read the tab title
and URL, but DOM reads, screenshots, file input access, and coordinate clicks
timed out on the import page. Manual upload was required.

Do not assume this was a file-format issue. The PO files validated locally with
`msgfmt --check --statistics`, and the manual upload confirmed that the files
were accepted by GlotPress.

