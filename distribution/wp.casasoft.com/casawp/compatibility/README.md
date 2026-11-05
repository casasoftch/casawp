# PHP 8.4 compatibility backports

CASAWP keeps its existing bundled dependency versions and public APIs, while
supporting PHP 8.1–8.4. This avoids unrelated library changes or raising the
WordPress minimum through an Action Scheduler upgrade. These are CASAWP
backports, not new upstream releases.

The changes make nullable parameters explicit in Action Scheduler, Laminas MVC
and Casamodules; remove ineffective defaults before required parameters; retain
the logger's historical strict-error mapping without evaluating `E_STRICT`;
and explicitly retain the existing CSV escape character. Invalid Action
Scheduler singleton cloning and unserialization throw `Error`, keeping those
operations fatal by default without the deprecated `E_USER_ERROR` trigger.

`packages.json` is a local Composer repository for six pinned dependencies.
It preserves their upstream version, source reference and dependency metadata,
adding PHP 8.4 to their requirements and marking the CASAWP compatibility
override. Casamodules no longer requires the obsolete Zend-to-Laminas Composer
bridge: its code already uses Laminas. The bridge is removed from the lock,
autoload files and vendor tree. CASAWP resolves dependencies against PHP 8.1.0
so an update on a newer development machine cannot silently raise the minimum.

`vendor-patches.json` records the exact backports, including package metadata.
`apply-patches.php` runs after Composer install/update, applies missing edits,
and verifies already-applied ones. It validates all targets before writing and
stops on unexpected source changes. Composer retains installed requirements
when only repository metadata changes, so the script also synchronizes those
six installed requirement records after checking their source references.

Work in a complete source checkout with its tracked vendor tree, and leave
Composer scripts enabled. The patch targets include existing CASAWP vendor
customizations (such as its HeatServiceFactory); they deliberately reject an
unreviewed replacement of that tree with pristine upstream files:

```sh
composer install
```

Validate changes on PHP 8.1 and PHP 8.4, including imports, scheduled jobs,
contact delivery and theme overrides in a local or staging WordPress site.

## Local WordPress validation — 2026-11-05

The local cwonyx site runs WordPress 7.1.4 and links CASAWP to this checkout.
Integration checks passed with its active plugins on PHP 8.3.30 and 8.4.10:

- Property queries and offer preparation, two Bootstrap 4 contact forms with
  the privacy checkbox, contact-service acceptance/error/timeout fixtures,
  cached XML conversion for all 14 properties, and a persisted scheduler job.
- Through an isolated PHP 8.4 HTTP server, the homepage, property page, archive
  and all seven CASAWP settings tabs returned HTTP 200 with no CASAWP diagnostics.
- In a disposable database and uploads copy, the real scheduler processed the
  cached feed on both PHP versions: 14 properties, two completed batches and
  successful cleanup, with the import lock released and 14 properties retained.

The original site retained its 14 properties and 170 attachments. Outbound
requests and mail were intercepted during testing. Temporary jobs, the test
admin session, database copy, uploads copy and HTTP server were removed. Local's
normal site configuration remains on PHP 8.3.30.

CASAWP-LG still reports existing dynamic-property deprecations; CASAWP-Legal's
bundled Mustache reports an implicitly nullable parameter on PHP 8.4. These
separate plugins were not changed. Live CASAGATEWAY fetching, actual email
delivery, normal asynchronous dispatch and WPML imports remain unverified;
cwonyx does not have WPML active.

When intentionally upgrading a pinned dependency, review its metadata here and
its exact edits in the patch manifest together. Keep CASAWP's existing vendor
customizations and translation catalogs. Never bypass a patch mismatch or use
`--ignore-platform-reqs` to claim compatibility. The release builder rejects
missing backports and mismatched requirement metadata.

The installer ZIP already contains patched dependencies. Development tooling
and this repository are excluded; run Composer from the full source checkout
when maintaining dependencies.
