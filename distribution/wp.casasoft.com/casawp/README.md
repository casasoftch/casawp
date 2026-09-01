# CASAWP update hosting

This directory tracks the update endpoint deployed at:

https://wp.casasoft.com/casawp/update.php

The updater follows CASAWP's established version and info POST contract and
always distributes latest.zip. Versioned ZIPs are retained only as archives.

## Publishing a release

1. Update CASAWP's plugin version and the version/changelog values in
   update.php.
2. Commit the release, then create a ZIP whose top-level directory is exactly
   casawp/ and whose plugin file is casawp/casawp.php.
3. If latest.zip exists, rename it to casawp-X.Y.Z.zip, using its current
   version.
4. Upload the new ZIP as latest.zip and upload the revised update.php.
5. On a staging WordPress site, trigger a plugin update check and confirm the
   offered version, details modal and update download URL.

The update client never downloads the archived ZIPs. They are there solely for
rollback and release history.
