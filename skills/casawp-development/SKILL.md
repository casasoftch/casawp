---
name: casawp-development
description: Maintain the CASAWP WordPress plugin, especially its settings-page labels and translations, while preserving compatibility for existing sites.
---

# CASAWP Development

Use this skill for changes to the CASAWP plugin.

## Localization

- Use English as the source language for every new or edited UI label.
- Make every settings-page label and standardized frontend label translatable with the plugin's `casawp` text domain.
- Maintain translations only for the supported locales: `de_DE`, `en_US`, `fr_FR`, and `it_IT`.
- The WordPress admin UI in scope is CASAWP's dedicated settings page. Post-type and taxonomy labels are import-managed and are not ordinary WordPress admin UI; do not change them unless the user explicitly asks.
- When changing settings-page text, update the matching `languages/casawp-{locale}.po` catalogs and compile their `.mo` files. `options.php` also uses the `casawp_settings_text()` helper; include it as an extraction keyword when rebuilding its catalog entries.

## Release Version Consistency

When changing the CASAWP plugin version, use the `Version` header in `casawp.php` as the canonical value. Update the following release metadata in the same change before committing or publishing:

- `casawp.php`: the `Version` header and `$plugin_current_version`
- `README.txt`: `Stable tag`, `Tested up to`, and the matching changelog entry
- `distribution/wp.casasoft.com/casawp/update.php`: `$obj->new_version`, `$obj->tested`, and `$obj->last_updated`

Set `$obj->last_updated` to the change date as a `YYYY-MM-DD` string. Verify the latest stable WordPress release from the official WordPress download page, then use that exact version for both `Tested up to` and `$obj->tested`; CASAWP changes are tested against the current WordPress release. Do not publish if any synchronized version or compatibility declarations differ.

## Compatibility First

CASAWP is installed on hundreds of existing websites. Preserve all observable existing behavior unless the user explicitly authorizes a breaking change.

- Treat public PHP functions, hooks, shortcodes, option keys, stored metadata, generated markup, established field names, import formats, and theme integrations as compatibility-sensitive.
- Before implementing a change, assess its effect on existing installations, saved data, imports, integrations, themes, and translations.
- If a requested implementation could break functionality or compatibility cannot be confidently assured, explain the concrete risk and stop. Do not proceed until the user chooses or authorizes a compatible alternative.
- Prefer additive, backward-compatible implementations, including fallbacks and migrations that retain prior behavior.
