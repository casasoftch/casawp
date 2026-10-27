# CASAWP repository guidance

CASAWP is a WordPress plugin for importing and displaying real-estate properties.

## Project skill

Before changing plugin code, read [the CASAWP development skill](skills/casawp-development/SKILL.md). It is the canonical guidance for backward compatibility, localization, and release metadata. Keep those rules in the skill rather than duplicating them here.

Codex discovers the same skill through `.agents/skills/casawp-development`, a relative symlink to `skills/casawp-development`. Maintain the source in `skills/`.

## Repository map

- `casawp.php`: plugin entry point, bootstrap, WordPress hooks, and version metadata.
- `classes/`: plugin behavior, including imports, media, cleanup, logging, and progress.
- `options.php` and `classes/Admin.php`: CASAWP settings and admin integration.
- `modules/casawp/`: Laminas module configuration, services, and forms.
- `theme-defaults/`: frontend templates; sites can override these in their themes.
- `plugin-assets/`: scripts, styles, and frontend asset sources; `languages/`: translation catalogs.
- `tests/`: standalone PHP and Node regression checks.
- `distribution/wp.casasoft.com/casawp/`: update endpoint and release tooling.

## Validation

Run the existing checks relevant to the change from the repository root. The root `package.json` and `composer.json` do not define test scripts; invoke the test files directly.

| Area | Commands |
| --- | --- |
| Settings save/render | `php tests/settings-options.php` |
| Settings interactions | `node tests/settings-controls.js`, `node tests/settings-dropdowns.js`, `node tests/settings-notices.js` |
| Import and cleanup progress | `php tests/import-progress.php`, `node tests/import-progress.js` |
| Import feed validation | `php tests/import-feed-validation.php` |
| Import logging and taxonomy | `php tests/import-logging.php`, `php tests/import-taxonomy.php` |
| File and legacy attachment cleanup | `php tests/import-file-cleanup.php`, `php tests/import-legacy-attachments.php` |
| Remote media | `php tests/remote-media.php` |
| Admin notice hooks | `php tests/admin-notices.php /path/to/wordpress` |

The PHP checks use local stubs or fixtures. Settings checks need DOM, taxonomy and remote-media checks need SimpleXML, logging checks use `proc_open`, and legacy attachment checks need PDO SQLite and the bundled Composer dependencies. Admin notice checks require a local WordPress source tree and load its hook API without loading site configuration. Node checks use built-in modules. External services such as CASAGATEWAY, Google Maps, reCAPTCHA, and email delivery need configured integration testing; standalone checks do not establish that coverage.

For changed PHP or JavaScript files, check syntax with `php -l path/to/file.php` or `node --check path/to/file.js` as appropriate.

## Release packaging

Follow [the release instructions](distribution/wp.casasoft.com/casawp/README.md) when preparing a release. The builder refreshes derived locale catalogs and validates release metadata and MO files.

Keep development instructions and skill links out of installable plugin packages. Maintain exclusions in both `.gitattributes` for Git exports and `distribution/wp.casasoft.com/casawp/build-release.py` for release ZIPs; the builder uses tracked files directly and does not apply `export-ignore` attributes.
