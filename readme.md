readme.txt
===============================


Please refer to the ``readme.txt`` for general infos concerning the plugin. This readme.md only contains further technical details.

How do I change the html markup and add my own view files?
-------------------------------

Simply copy the relevant view files from the plugin ``/wp-content/plugins/casawp/theme-defaults/chosen-viewtype/*.phtml`` ...

![image](assets/custom-viewfiles-plugin.png)

into your theme directory ``/wp-content/themes/your-theme/casawp/chosen-viewtype/*.phtml`` ...

![image](assets/custom-viewfiles-theme.png)

to override them with your theme.

ViewFile Structure
-------------------------------

![image](assets/custom-viewfiles-structure-ugly.png)

Shortcodes
===============================

casawp_properties
-------------------------------

`[casawp_properties categories="apartment" order="ASC" posts_per_page="15"]`

Displays properties anywhere shortcodes are accepted.

The correspondig view file `shortcode-properties.phtml` is responsible for the looks.

Accepted Query Params:

* 'post-type'
* 'posts_per_page'
* 'order'
* 'ignore_sticky_posts'
* 'post__not_in'
* 'orderby'
* 'categories'
* 'locations'
* 'countries'
* 'salestypes'
* 'availabilities'
* 'categories_not'
* 'locations_not'
* 'countries_not'
* 'salestypes_not'
* 'availabilities_not'
* 'features'
* 'my_lng'
* 'my_lat'
* 'radius_km'
* 'projectunit_id'

Accepted pass-through variables

* col_count

casawp_contactform
-------------------------------

`[casawp_contactform offer_id="12" id="my-custom-form"]`

Displays a form for inquiry submissions

Accepted Query Params:

* id – Form ID registered to formSettingService (optional)
* offer_id
* project_id
* direct_recipient_email (Will override any setting and will force CASAMAIL to send an E-Mail)

Custom Forms
===============================

Register additional forms in your theme by adding the following to your functions.php

```php
<?php

    //create a new class that extends DefaultFormSettings to create a new form id.
    class MyCustomFormSetting extends \casawp\Form\DefaultFormSetting {
        public $id = 'my-custom-form';

        function __construct(){
            //name the view file you wish to use (defaults to contact-form) make sure you create the file in `wp-content/themes/your-theme/casawp/bootstrap3/{viewFileName}.phtml`
            $this->viewFile = 'custom-form-' . $this->id;
        }

        public function setAdditionalFields($form){
            // set additional fields here
            $form->add(array(
                'name' => 'customfield',
                'type' => 'Text',
                'options' => array(
                    'label' => __('Custom Field here', 'casawp'),
                ),
            ));

            //add validation filters here
            $form->setCustomFilters(array(
                array(
                    'name' => 'customfield',
                    'required' => true,
                    'validators' => array(
                        array(
                            'name' => 'not_empty',
                        ),
                        array(
                            'name' => 'string_length',
                            'options' => array(
                                'min' => 2
                            ),
                        ),
                    ),
                )
            ));

            return $form;
        }
        public function preCasaMailFilter($data, $postdata){
            //manipulate the data before it is sent to casamail after the visitor posts the form and it's valid.
            $data['extra_data'] = array_merge($data['extra_data'], array('customfield' => $postdata['customfield']));

            return $data;
        }
    }

    //make sure the plugin knows about it by informing the formSettingService from the plugin
    add_action( 'casawp_register_forms', 'casawp_register_forms_func', 10, 1 );
    function casawp_register_forms_func( $formSettingService ) {
        $formSettingService->addFormSetting(new MyCustomFormSetting());
    }
```


Custom automatic E-Mail Response
===============================

An example of how to enable an extra E-Mail when a customer posts a inquiry.

```php
function casawp_after_inquirysend($payload) {
	//(array) $postdata
	//(object) $offer
	extract($payload);

	if ($offer->getFieldValue('seller_org_customerid') == "slug") {

		$subject = "Info-Meldung bezüglich einer Objekt-Anfrage von website.ch: " . $offer->getTitle();

		$message = 'Eine Anfrage wurde von website.ch an die Test AG gesendet.';
		$message .= "\n";
		$message .= "\n";
		$message .= "Objekt:\n";
		$message .= $offer->getTitle(). "\n";
		$message .= 'ref: ' . $offer->getFieldValue('visualReferenceId')
        ? $offer->getFieldValue('visualReferenceId')
        : $offer->getFieldValue('referenceId') . "\n";

        $message .= "\n";
		$message .= "\n";
		$message .= "Anfragender:\n";
		foreach ($postdata as $key => $value) {
			$message .= $key . ": " . $value . "\n";
		}

		wp_mail( mail address, $subject, $message);
	}
}
add_action( 'casawp_after_inquirysend', 'casawp_after_inquirysend' );
```


Exclusive Properties with login
===============================

The Plugin uses the default WordPress user interface for registration and authentication for users that have access to special unpublished properties. These properties `if availability = private` and the corresponding filter `if availability contains private`, will only be viewable if the user is registered to at least a `subscription` level and will be redirected to the defined login page.

CASAWP will generate 2 Pages directly and will define the pages within the casawp->settings->PrivateArea login and logout pages.

Custom Login form at arbitrary entry location
-------------------------------

Use the default WordPress login form `wp_login_form()` or copy the sample code below. (adjust the redirect url to an appropriate one)

```php
<?php if (isset($_GET['login']) && $_GET['login'] == 'failed') : ?>
  <div class="alert alert-danger" role="alert">Login fehlgeschlagen.</div>
<?php endif; ?>
<div class="casawp-private-login-form">
  <?php
    $args = array(
      'echo'           => true,
      'remember'       => true,
      'redirect'       => 'http://where-should-i-go.com',
    );
    wp_login_form($args);
  ?>
</div>
```

Import diagnostics
==================

Import logs remain in `wp-content/uploads/casawp/logs/YYYYMM.log` (or the site's uploads directory on multisite). Entries use JSON with a local timestamp including seconds and a message. Successful milestones put batch progress, timings, counts and scheduling details in the message, with compact `_meta` fields for run/batch identity and the event. Repeated feed paths, fingerprints, memory figures and internal stages are omitted from successful milestones. Warnings and errors retain full diagnostics, including precise UTC time, severity, run/request IDs, stage, file/line, feed details and stack frames without argument values. JSON is the only output format; no format setting is needed.

For example, batch milestones read:

```json
{"2026-10-19 14:21:59":"Import batch 32 scheduled in 13s (action 867).","_meta":{"run_id":1792411575,"batch_number":31,"event":"scheduler.scheduled"}}
{"2026-10-19 14:21:59":"Import batch 31 finished in 4.202s: 16 offers processed, 12 unchanged offers, 4 metadata updates, 39 attachments updated, 0 errors.","_meta":{"run_id":1792411575,"batch_number":31,"event":"properties_batch.finished"}}
{"2026-10-19 14:22:55":"Import batch 32 started.","_meta":{"run_id":1792411575,"batch_number":32,"event":"properties_batch.started"}}
{"2026-10-19 14:22:55":"Import batch 32/164: processing 4 properties (1781010, 1781011, 1781012, 1739179).","_meta":{"run_id":1792411575,"batch_number":32,"event":"batch.processing"}}
```

Normal logging summarizes routine offer and attachment outcomes per batch. Routine batch start and successful scheduling entries are shown only when `casawp_import_log_level` returns `debug`; batch finishes, processing results, cleanup phase transitions and totals remain visible normally. Scheduling failures, warnings and errors are always retained. Individual unchanged-property, metadata and attachment messages are available in debug mode, so large imports do not generate a line for every successful operation. Warnings and errors always include their context. Signed URL query values, URL credentials and sensitive structured keys are redacted. Import and manual cleanup completion summaries always use English text and English number formatting, independently of the site's language; settings-page notices remain translated. Dashed separators mark "Start of import", "Start of cleanup", "End of cleanup" and "End of import", retaining valid JSON on every nonempty log line. Import boundaries apply to manual and gateway-triggered imports, in batch and single-request mode. Cleanup starts are logged once per run across background batches; standalone cleanup uses only the cleanup boundaries. The end-of-import separator follows cleanup completion. The event names are `import.separator.start`, `cleanup.separator.start`, `cleanup.separator` (cleanup end), and `import.separator.end`.

Routine debug entries use the compact original timestamp/message style, without repeating `_meta` on every attachment. A short property heading identifies the property, language, run and batch; successful offer finish entries are omitted. For example:

```json
{"2026-10-19 12:09:03":"Property 353184 (de) | run 12345, batch 2"}
{"2026-10-19 12:09:03":"updated casawp attachment 42 origin: https://example.test/image.jpg"}
{"2026-10-19 12:09:03":"kept casawp attachment 43 origin: https://example.test/plan.pdf"}
```

Temporarily enable detailed logging in a site plugin when investigating a specific import:

```php
add_filter('casawp_import_log_level', static function () { return 'debug'; });
```

Remove the filter after investigating to return to batch summaries.

Active imports record fatal PHP errors, including memory exhaustion and PHP execution timeouts, with the last known stage. A process killed by the server or operating system cannot write a shutdown entry; in debug mode, a batch start without a finish is still useful evidence. If the monthly log cannot be written, the entry falls back to the PHP error log.

Run the standalone logging regression checks with `php tests/import-logging.php`.

Import file cleanup
===================

The General settings page also provides **Run legacy data cleanup** in its own block below **Delete all property data**. It runs the attachment, file, taxonomy and log cleanup in background batches, preserving existing property posts and their content. An activity bar stays visible while background batches run. A status message shows its phase, batch and freed MB; the completion summary also lists removed unused attachment records, local import files and empty taxonomy terms; monthly logs contain the detailed results. This action requires administrator permissions and a nonce. It does not download or import a feed, and works without `data.xml`: the current site's stored property IDs, across all languages and post statuses, protect active folders when CDN is disabled. With CDN enabled, all regular files under `externalsync` are removed, including files for active properties. Its state and totals are separate from import progress. It shares the import lock and stops if the stored property ID set or CDN mode changes. Newly requested imports wait until manual cleanup releases the lock. The existing delete-all action remains separate.

Import progress reserves its final percent for cleanup: the import stays at 99% while a separate activity bar and current phase show that maintenance is still running. Only completed cleanup releases the import controls and reports 100%. Cleanup activity bars are indeterminate because the remaining work is discovered across batches. Both cleanup indicators use the import green with a slow five-second movement in each direction, and stop animating when reduced motion is preferred. Import and cleanup bars disappear immediately on completion; the result summary is logged and remains visible for up to ten minutes after completion or until another run starts. Reopening settings within that window shows the remaining time without extending the expiry. Expired results stay hidden. Starting either an import or manual cleanup dismisses the previous completion notice from the other operation immediately and persistently. Status responses from before a new manual cleanup are ignored, so stale results cannot reappear alongside the current operation. The saved counts and log entries remain available. Status polling continues after completion so subsequent background imports are also visible.

Successful import finalization also cleans the current site's `casawp/import` directory in small batches. It removes the historical `data-done.xml` and `data-error.xml` files, unreferenced files belonging to properties absent from the imported feed, and empty property directories, including `externalsync` and its `attachment` parent when empty. Unreferenced Finder `.DS_Store` metadata inside attachment folders is also removed so it does not keep otherwise empty folders alive. `data.xml` and unrelated XML files are retained. A changed or invalid feed, a stale/canceled run, or recorded import/media failures prevents this file cleanup. Symbolic links are not followed.

Before sweeping files, cleanup removes detached legacy attachment records with no parent or a missing parent. It targets CASAWP import paths and CASAGATEWAY/CDN media GUIDs, including historical remote URLs stored in `_wp_attached_file`. It also removes leftover post metadata for missing attachment posts identified by their CASAWP import path; unrelated orphan metadata is retained. Local legacy media for active properties is retained when CDN is disabled.

Source ownership and the absence of an existing parent determine which legacy records are removed. Cleanup does not search arbitrary content, metadata or options for matching IDs or filenames, so the same rule applies across installations regardless of property measurements, postal codes, custom fields or old cached data. Attachments with an existing parent are retained. A WPML counterpart with an existing parent protects its translation group, across all languages. WordPress attachment deletion APIs run with physical file deletion temporarily suppressed; the guarded file sweep handles physical deletion. Custom integrations can retain a detached record through `casawp_legacy_attachment_usage` (reason string and attachment ID); this does not exempt its local files from the CDN `externalsync` purge. Unsafe paths and symbolic links are retained.

Candidate selection bounds each source before merging its ordered page, so a large backlog does not have to be merged in full for each batch. Posts and metadata are loaded into WordPress caches in bulk. Follow-up import and manual cleanup batches are eligible immediately, without an artificial delay; Action Scheduler still controls execution and its normal request limits. The `casawp_legacy_attachment_cleanup_batch_size` filter defaults to 50 records, capped at 200. Normal logs report removed/retained counts; debug logs identify retained records and reasons.

With the CDN option enabled, CASAGATEWAY/CDN media of all supported types (`image`, `document`, `plan`, `offer-logo`, and `sales-brochure`) are imported as remote-only attachments. This includes PDF documents and plans. Cleanup treats `import/attachment/externalsync` as a disposable local cache: it deletes every regular file recursively, regardless of extension, active property IDs, attachment type, missing metadata or local references. This includes files directly in `externalsync` and nested subfolders. Empty property folders, `externalsync`, and its `attachment` parent are removed after their children. Symbolic links are skipped. Other attachment folders retain their local-consumer protections.

Attached records remain in WordPress. For attachments with a usable remote source, cleanup preserves their IDs, descriptions, source URLs and image dimensions, marks them as remote, and clears obsolete local file metadata. Historical records in `externalsync` do not need an import marker or attachment taxonomy for this conversion. Records without a usable remote source are not marked as remote; their local files are still removed by the CDN cache purge. File metadata is kept when physical deletion fails so a later cleanup can retry.

Disabling CDN does not automatically restore local files. If local media are needed again, run a manual media resync.

After the file sweep, cleanup removes unused terms from registered `casawp_*` taxonomies, except `casawp_availability` and `casawp_salestype`. Within `casawp_attachment_type`, required standard types (`image`, `document`, `plan`, `offer-logo`, `sales-brochure`) and their WPML counterparts are also retained when unused. Plugin registration ensures these terms exist; deleting them would recreate the same cleanup work on every run. Unused custom attachment types remain eligible for cleanup. It checks actual term relationships rather than cached term counts, across all languages, and preserves terms with used WPML counterparts to avoid synchronized deletion affecting assigned translations. Deletions use WordPress APIs and their WPML hooks. The term pass is batched and bounded to terms present when it starts; terms created during cleanup are retained until a later import. Normal logs report term deletion counts, with individual IDs in debug mode.

Exceptions thrown by individual term-deletion hooks (for example, Yoast indexable deletion) are logged as warnings with the term ID, taxonomy, exception trace and whether the term was actually removed. Cleanup checks the database after the exception, counts any completed deletion, advances past the affected term and continues with later terms and old-log cleanup. Retained terms can be retried on a subsequent cleanup. WordPress deletion hooks remain enabled. Database errors and unsafe or stale runs still stop cleanup. A stopped manual run preserves its last known phase and partial totals in its state and failure log.

Cleanup logs identify each phase: database properties, database attachment records, filesystem files/folders, taxonomy terms, and old logs. Batch finish messages identify their phase, and phase transitions/completion are logged separately. Debug logging also includes batch starts and scheduling messages naming the next phase. Retained database record counts are explicitly labeled. File/folder counts and freed space are logged, with individual deleted file paths in debug mode. The last cleanup batch writes its finished entry before the overall totals and completion summary, for both imports and manual cleanup. Finalization remains covered by exception and fatal-error logging; a failure after batch completion is identified separately. A final summary combines the MB freed across all attachment/XML cleanup batches and old-log deletions, with a breakdown for each. The `casawp_file_cleanup_batch_size` and `casawp_file_cleanup_time_limit` filters default to 200 filesystem entries and 5 seconds per batch. Run its filesystem and finalization regression checks with `php tests/import-file-cleanup.php`.

Run the SQLite-backed legacy attachment, reference, WPML and term cleanup checks with `php tests/import-legacy-attachments.php`.

Successful import finalization also removes monthly log files except those from the current and previous calendar month, based on the site's local date. It recognizes standard `YYYYMM.log` names, legacy English month names such as `2020 Aug.log` and `2020 August.log`, and separated numeric names such as `2020-08.log` or `2020_8.log`. Spaces, hyphens and underscores are accepted as separators; month names and the `.log` extension are case-insensitive. For example, during October 2026 it keeps both October and September logs in every recognized format, regardless of file modification time. Imports, standalone cleanup and the monthly cron share this routine. Custom log names, invalid month names, directories and symbolic links are skipped. Cleanup reports deleted file counts and freed space, and logs deletion failures.
