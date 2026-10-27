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
- `distribution/wp.casasoft.com/casawp/`: update endpoint and release tooling.

## Validation

There is no first-party automated test suite checked into this repository. Validate affected behaviour in a local or staging WordPress installation and record what was checked and what remains unverified. External services such as CASAGATEWAY, Google Maps, reCAPTCHA, and email delivery require configured integration testing.

For changed PHP or JavaScript files, check syntax with `php -l path/to/file.php` or `node --check path/to/file.js` as appropriate.

## Release packaging

Follow [the release instructions](distribution/wp.casasoft.com/casawp/README.md) when preparing a release. The builder refreshes derived locale catalogs and validates release metadata and MO files.

Keep development instructions and skill links out of installable plugin packages. Maintain exclusions in both `.gitattributes` for Git exports and `distribution/wp.casasoft.com/casawp/build-release.py` for release ZIPs; the builder uses tracked files directly and does not apply `export-ignore` attributes.
