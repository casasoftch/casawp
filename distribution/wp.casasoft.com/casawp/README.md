# CASAWP update hosting

This directory tracks the update endpoint deployed at:

https://wp.casasoft.com/casawp/update.php

The updater follows CASAWP's established version and info POST contract and
always distributes latest.zip. Versioned ZIPs are retained only as archives.

## Publishing a release

1. Update CASAWP's plugin version and the version/changelog values in
   update.php.
2. Run `python3 distribution/wp.casasoft.com/casawp/build-release.py /tmp/casawp-X.Y.Z.zip`.
   This refreshes the derived locale catalogs, validates all MO files and
   release metadata, and creates a ZIP with the top-level directory `casawp/`.
   Stage any new runtime files before building; the ZIP uses tracked files
   from the working tree plus the explicitly generated locale aliases.
   Commit the release including any refreshed catalogs before publishing.
3. If latest.zip exists, rename it to casawp-X.Y.Z.zip, using its current
   version.
4. Upload the new ZIP as latest.zip and upload the revised update.php.
5. On a staging WordPress site, trigger a plugin update check and confirm the
   offered version, details modal and update download URL.

The update client never downloads the archived ZIPs. They are there solely for
rollback and release history.

## Translation compatibility

The maintained WordPress translation sources remain `de_DE`, `en_US`,
`fr_FR`, and `it_IT`. The build copies the compiled `casawp-de_DE.mo` to
`casawp-de_CH.mo` and `casawp-de_CH_informal.mo`; these are derived aliases,
not separately maintained translations. Existing locale catalogs are not
rewritten. Object content and imported language codes are unchanged.

CasasoftStandards `en_US.mo` is an independently maintained catalog with
additional translations. Preserve it as a real file; do not regenerate it
from the smaller `en.mo` catalog.

The bundled CasasoftStandards regional aliases are also real binary copies,
not symlinks. Its missing `fr.mo` is derived from the existing `fr_FR.mo`.
Keep these copies in Git so source ZIPs also contain usable files. Do not run
the old vendor symlink helper scripts when preparing a release.

The build omits distribution tooling, tests, development skills, and GitHub
workflow files from the installable ZIP. Test files are not needed at runtime.
