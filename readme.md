readme.txt
===============================


Please refer to the ``readme.txt`` for general infos concerning the plugin. This readme.md only contains further technical details.

Import debug hook
===============================

Temporarily enable advanced import diagnostics in your theme's `functions.php`, a site plugin, or a PHP snippet in Code Snippets:

```php
add_filter('casawp_import_log_level', static function () { return 'debug'; });
```

In Code Snippets, select **Run everywhere** so background batches receive the filter. Logs are saved in the site's uploads directory under `casawp/logs/YYYYMM.log`. Remove or deactivate the filter after investigating to return to compact batch summaries. Debug mode uses compact checkpoints and traces individual `save_post` callbacks. See [Import diagnostics](#import-diagnostics) for the recorded stages, hooks, HTTP requests and runtime details.

Settings page
===============================

Open **CASAWP** in the WordPress admin menu to configure General, Archive, Detail view, Protected properties, Contact form, Scripts, and Logs. The settings page uses a responsive sidebar, grouped cards, and a sticky **Save changes** bar. The layout supports widths up to 1920 px; on smaller screens, navigation wraps above the form and fields stack vertically. The introduction has flexible space, with help and GitHub links alongside it or underneath when space is limited.

The CASASOFT logo above the settings links to [casasoft.ch](https://casasoft.ch/) in a new tab. All archive filters, including their display types and manual room/area ranges, share one card. **Show net rent** appears in the **Price** section. On this page, third-party callbacks on the WordPress admin notice hooks are suppressed before rendering; CASAWP and WordPress core callbacks are retained. A scoped stylesheet and script hide standard notices unless marked with `casawp-notice`, including banners that force inline `display: block !important`. CASAWP's settings confirmations, import results, deletion confirmations, and generated-page messages remain visible; other admin screens are unaffected. This also covers standard notices inserted later by another plugin. Existing option keys and tab URLs are retained, and settings labels are available in German, English, French, and Italian.

Titles use Poppins and body text uses Mulish, with CASASOFT-inspired blue buttons, subtle field backgrounds, and visible focus indicators. Both fonts are bundled locally with their licenses and loaded only on the CASAWP settings page; viewing settings makes no requests to Google Fonts. Code fields retain a monospace font. Section titles sit inside the first white card, aligned with the white navigation panel. Text inputs and textareas share a maximum width of 400 px. Custom checkboxes use the button blue with a white checkmark, and the sticky bottom bar contains only the save action. The delete-all action uses a solid red button with white text. All settings dropdowns use a locally bundled Select2 theme with matching blue focus and selection states, keyboard navigation, and search for lists of ten or more options. The original select fields and saved values are retained, with native dropdowns available if JavaScript fails. The library uses a private jQuery registration to avoid replacing another plugin’s Select2. Menus close on outside clicks. A head-loaded pending state prevents native dropdowns flashing during tab navigation; it clears after initialization or restores native controls automatically if enhancement fails.

![CASAWP settings page with sample values](assets/settings-page.png)

Verify settings changes in a local or staging WordPress installation. External CASAGATEWAY, Google Maps, reCAPTCHA, and email delivery require separate integration testing with configured services.

Settings saves require an administrator's verified POST request and accept only the fields on the selected tab, including registered custom categories and displayed content languages. AJAX import start, cancellation and progress reset require administrator permissions, POST and an action-specific nonce in the `nonce` field (`casawp_start_import`, `casawp_cancel_import` or `casawp_reset_import_progress`). The settings UI supplies these nonces for its controls. Public gateway pokes and scheduled imports keep their existing integration paths. Stored form values are escaped for their HTML context so quoted text and HTML wrappers remain editable. Submitted values are unslashed once; the HTML wrapper and tracking settings retain the legacy slash format expected by existing frontend readers, and tracking preserves the `%casawp_id%` placeholder.

Import feed safety
===============================

CASAWP validates the XML structure and every property ID before changing existing listings or scheduling deletion work. A feed must have exactly one direct `properties` node, containing only `property` elements with nonblank IDs. Wrapper names and imported IDs are preserved. An explicit empty `properties` list still requests full cleanup; an unexpected XML response, malformed XML, or invalid property list stops the run instead. Queued database cleanup checks the feed again before deleting properties, and a rejected feed leaves a stopped status and diagnostic logs while releasing its run's lock.

A blank feed or a CasaGateway `export` containing only export metadata stops with an explicit empty-feed message on the settings page and in manual import feedback. Existing listings are retained for these rejected feeds. The reason belongs to that import run and is reset when a new run starts; other failures keep the general log notice.

CasaGateway's explicit JSON error stating that the API key could not be found produces a dedicated settings-page message asking the administrator to check the API keys. This is detected both in a download response and in an already-saved import file, before XML parsing. The monthly log records the same cause under `gateway.api_key_not_found`. The error response is not saved over an existing feed, and the key included in its detail is omitted from the notice, log entry and diagnostic exception. Other authorization and XML errors keep their existing failure messages.

An exception during an individual property update stops its owning import instead of continuing to final cleanup with an unprocessed property. Its remaining import and cleanup actions are cancelled, the run's lock is released and the existing stopped-import notice directs the administrator to the logs. This also covers failures before a post ID is available and errors while restoring manually changed attachments. Already applied updates and deletions are retained. An older worker cannot stop a newer run, and a later successful import keeps the normal cleanup behaviour.

Batched import preparation
===============================

Batched imports check that every property can be converted before applying the first batch. That result is remembered for the current import run and exact feed contents. Later batches convert only their selected properties; a new run or changed feed triggers the complete conversion check again. Each batch still reads and validates the entire XML structure and property IDs. The conversion pass retains only the current batch's converted properties in memory. Property order, translations, media handling, batch sizes and cleanup follow the existing import flow.

Each import stores its batch size, target WPML language codes, main language and forced source-language setting once for the run. Background requests read WPML's configured languages without depending on a frontend language switcher. Later batches keep that snapshot even if a contextual language lookup returns no languages or settings change. Missing WPML support stops a multilingual run rather than silently importing only its source language. Automatic CDN batches budget eight translated offers: eight properties for one language, four for two languages, two for three or four languages, and one for five or more. Non-CDN imports retain one property per batch. The manual override still sets the logical batch size when a new run starts.

At the start of each import run, CASAWP checks WPML's effective translation setting for Media (`attachment`). If WPML reports it as translatable, CASAWP logs a warning and emails the site's WordPress administrator address (`admin_email`) with the website URL and the setting to change: **WPML > Settings > Post Types Translation > Media (attachment) > Not translatable**. Both translatable modes trigger the warning. The import continues, and CASAWP does not change WPML settings or delete existing media. A successfully accepted email is sent only once while the setting remains incorrect. An import that observes non-translatable media, or inactive WPML, resets the warning so a later recurrence can notify again. Failed mail attempts are logged and retried at the next import start. The check runs once per new run, including manual, automatic and single-request imports, rather than once per property batch.

The run tracks contiguous completed batches and the number of processed properties. Cleanup requires complete coverage and the same ordered list of property IDs; a changed property list stops the run before further updates or cleanup. Content changes with the same ordered IDs retain the existing conversion check. A run started by an older plugin version without the stored plan stops safely and requires a new full import, because its counters cannot establish which properties were skipped. Single-request imports and explicit empty `properties` lists retain their normal cleanup path.

After each property batch, its successor is eligible immediately. Previously the 12–90-second scheduling delay could leave no work due when Action Scheduler checked again after its native five-second pause, ending the async chain until another cron run. The web queue now yields after a property action, releases its claim and continues in a new request. Requests executing only property batches skip the native five-second pause: keeping the worker asleep after releasing its claim lets competing runners occupy every PHP worker, delaying frontend and status requests even when batches are fast. Empty runners also skip that pause while the current import is in its property phase. Requests that execute other queue jobs, including cleanup, retain their native pause; WP-CLI is unchanged. Cron-triggered property actions arrange a shutdown dispatch after releasing their claim. Serial action execution, run checks and the first batch's ten-second startup delay are unchanged. Loopback requests and WordPress cron must still work on the host.

The native batched importer can save progress between complete properties and resume the same logical batch in another request. It checks a 15-second planning budget (filter: `casawp_import_batch_time_limit`), the average property duration and an 80% PHP memory threshold before starting another property. A property and all its language versions finish together; an unusually slow property can still exceed the budget. The saved cursor preserves feed order and ranks, and progress includes checkpointed properties. A batch counts as completed only after its entire range has finished; cleanup remains blocked until all ranges are complete. Archive range statistics are recalculated once per completed logical batch so checkpoints do not multiply full metadata scans. Custom subclasses overriding the whole `updateOffers()` method and synchronous single-request imports retain their existing whole-call behavior.

While an import is active, the web queue uses a 20-second planning budget, retained until the current request ends even if the import completes. Action Scheduler checks that budget between actions; it cannot interrupt an individual slow batch. Existing media/download work can therefore still require separate timeout investigation. Claim and failure expiry retain at least their existing 600-second baseline while the shorter budget applies; explicitly disabled expiry remains disabled. WP-CLI processing retains its existing scheduling runner limits and does not yield after each property action.

Concurrency limits and import locks apply within each site. Several multisite networks on one server can still run workers at the same time; a server-wide concurrency cap requires shared scheduling outside the individual site queues. Failed Action Scheduler imports now record the supplied exception, timeout or PHP shutdown details together with their action, run and batch IDs in the monthly import log. A scheduler timeout identifies an unfinished worker, rather than establishing the underlying server failure. Late failure notices for work already checkpointed do not stop its continuation.

Existing listings are compared with the imported main fields and a fresh fingerprint of their stored metadata before calling WordPress's post-save API. A genuinely unchanged listing keeps its last-modified date and does not trigger property save hooks. Its active/processed-run markers still update, and WPML translation linking is still checked. Changes to ordering, content or metadata retain a native save, including price-only changes; media migration, manual media changes and retry work also retain that save. Main-field changes use the existing full payload, while metadata/media-only changes use a narrow update to preserve stored escaping. Existing URL slugs are retained, and WordPress still repairs empty slugs. New listings continue through insertion and initialization. Metadata, taxonomy and attachment synchronization keep their existing paths. Custom Import subclasses overriding `updateOffer()` retain the legacy rank save before their override runs, including when it delegates to the parent implementation.

Bounded property batches allow WordPress object-cache additions so repeated reads of property and attachment metadata can be reused. The caller's previous cache-addition suspension setting is restored when the batch returns, including early returns and exceptions. Page and CDN cache settings are unchanged. Synchronous single-request imports retain their existing cache-suspension behavior.

Bounded batches assign property ranks from their saved position in the feed, including progress saved within a batch. A stale `casawp_current_rank` counter therefore cannot shift later properties on a repeat import; the counter is still updated for existing integrations. Custom whole-batch updaters and single-request imports retain their existing counter-based ordering. Existing incorrect ranks can require one corrective save before subsequent imports skip the unchanged offers.

Attachment synchronization compares titles after applying the same sanitization used when saving them. Encoded URL characters or markup stripped from a title no longer cause a repeated update of the same stored value. Real title changes still update normally; this does not change image URLs or download behavior.

Plain-text property and attachment titles also compare HTML entities by their decoded characters, so `&amp;`, `&#38;`, `&#x26;` and `&` do not cause repeated saves. Named and numeric entities for quotes, accents and other characters follow the same rule. Decoding happens once, preserving differences between single and double encoding and between nonbreaking and ordinary spaces. Titles containing literal `<` retain exact comparison to distinguish actual markup from escaped text. Equivalent titles keep their stored spelling, including when another property field requires a save. Descriptions, excerpts and other imported fields keep their existing comparison and save behavior.

Metadata synchronization compares serialized database values before writing. New or changed fields still use the existing delete/insert path, and duplicate rows are repaired using the database's key-comparison rules. Unchanged single-row values retain their metadata row IDs. Protected import identifiers, manual-media markers, SEO fields and the `casawp_protected_postmeta_keys` filter remain supported; obsolete unprotected fields are still removed and the post's metadata cache is invalidated afterward. Payloads with keys outside letters, digits and underscores, or keys differing only by letter case, retain the previous rewrite path. If the comparison query fails, synchronization also falls back to rewriting supplied fields.

Existing-property lookups skip pagination totals because the importer uses only the first matching post. Archive minimum/maximum area and room counts are calculated together in one query after each completed logical batch, retaining the existing numeric conversions, missing-value handling and option keys. These changes reduce database work; they do not add automatic recovery from a terminated worker or establish the cause of a hosting timeout. When validating on another host, compare batch duration, query counts and peak memory with its actual object-cache configuration.

Property attachment editing
===============================

The property's **Attached images and files** box provides a sortable image gallery above the other attachment details. The lower table contains documents, plans, brochures and other attachments, including unassigned files, without repeating gallery images. The summary still counts all attachments and images. Drag a preview or use its arrow buttons to change the image order. Changes save immediately, including for draft properties, and the first image becomes the featured image. **Add attachments** opens the WordPress Media Library; choose the attachment type first: image, document, plan, sales brochure or offer logo. Image and offer-logo types require image files. New uploads and unattached local files can be added; files already belonging to another post cannot be moved through this control. Existing files on the same property can be selected to assign a type.

This supports manual property management when imports are disconnected. Resumed imports remain authoritative: an explicit source media list restores the imported image order and featured image even for an otherwise unchanged offer. Files added through the new control are detached back into the Media Library, retaining their records and files, including when an import removes their property. Their `_casawp_manual_attachment` provenance remains after detachment, so subsequent import and standalone cleanup preserve them and their WPML counterparts even if a file is stored under an import directory. The property-level pending-change marker still clears after successful synchronization. A missing source media node preserves manual additions; source-upload failures keep the pending manual changes available for a retry. Older manual attachments that have not been selected through the new control keep their existing behaviour. Changes apply only to the edited property, including on multilingual sites. Editing requires permission for both the property and each affected attachment; adding also requires upload permission. Requests use POST and a property-specific nonce, and wait while import or cleanup work holds the shared lock.

Additional property details
===============================

The **Additional Meta Fields** box edits `visualReferenceId`, grouped `the_urls` links, structured `extraPrice` costs and `integratedoffers` using their existing import formats. Repeatable link rows contain type, URL, label and title; cost rows contain amount, currency, billing period and property unit. Additional offer rows also contain an offer type and whether it is included in the price, with an optional amount. Existing source-specific frequency remains hidden and preserved, along with unedited extra data. Unsupported imported structures remain read-only rather than being rewritten. Heating is displayed as readable nested lists; calculated filtering values and import bookkeeping sit inside collapsed **Import information**. Other stored details remain inspectable, and the existing Buy price field is unchanged.

The property's normal **Update** or **Publish** action saves these details in both the classic and block editors. Submitted details require POST, permission to edit the property, a property-specific nonce and the shared import/cleanup lock. A revision fingerprint rejects stale submissions. All sections are validated together before writing their metadata after the normal property fields. Save failures display an error and restore earlier metadata writes where needed. These edits apply only to the selected property. The next source import restores imported values even for an otherwise unchanged feed. Labels, help and feedback are translated in the four supported catalogs, with Swiss German binary aliases derived from German; imported content and metadata keys retain their existing values.

Delete-all operation logging
===============================

**Delete all property data** in General settings writes a start entry and a final success, failure or interruption summary to the existing monthly CASAWP logs in the current site's uploads directory. Entries include the administrator's user ID, site context, timestamps, affected database-row counts and elapsed time. Failure diagnostics identify the stage and error; interrupted operations record available partial results when PHP can run its shutdown handler. These counts describe confirmed database changes.

The settings page shows success only after the operation finishes. The result notice is stored for the initiating administrator and site, appears once on the CASAWP settings page and needs no deletion-result URL parameters. Refreshing the page does not show it again. Failure and interruption notices explain that some data may already have been deleted and direct the administrator to the logs for investigation. The existing administrator permission and nonce checks still apply. This action remains separate from **Run legacy data cleanup**. The Logs tab links to these same monthly files, with a version based on each file's modification time and size so reopening a refreshed link requests its latest contents. An already-open or downloaded copy must be reopened to display later entries.

Delete-all detaches ordinary uploads from removed properties and retains their attachment records, metadata and type relationships, including uploads whose manual marker was cleared by an earlier import. Media selected by the existing attachment-type targets are deleted only if their metadata contains a nonempty `_origin`, `_casawp_import` or `_is_remote` marker, or their stored post fields or metadata values contain `externalsync`, `casagateway` or `cdn.casasoft.com` (case-insensitive). A type assignment alone does not establish import ownership. Attachment type counts are recalculated for the remaining records. The action continues to delete database records only; physical file cleanup remains separate.

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

CASA Mail submissions show success only after the service returns a successful HTTP status and a valid JSON object without a reported error. Connection failures, timeouts, rejected requests and invalid responses use the existing delivery-error message and keep the populated form visible for retry. The request has a 20-second timeout and is not retried automatically. Service acceptance does not confirm final inbox delivery. Custom form payload filters and the existing email/Flatfox delivery routes remain supported. Theme overrides can show delivery errors using the existing `sendError` value.

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

The plugin uses WordPress registration and authentication for properties with availability `private`. The existing admission rule remains unchanged: any logged-in WordPress user, including a subscriber, can view the private area. Anonymous normal property pages redirect to the configured login page before headers or theme content can expose property details. Direct JSON, AJAX, map and WordPress REST item reads require authentication too; REST collections, search and public property queries exclude protected properties and related attachment metadata before pagination and totals are calculated. Protected comments and oEmbed previews are excluded from anonymous output. Availability checks use stored taxonomy relationships across languages and inspect every assigned term, matching the `private` slug or name.

Custom categories such as `custom_exklusiv` are separate from availability. Assigning such a category does not enable CASAWP's login protection.

Authenticated protected responses receive private, no-store cache headers. Clear any previously generated page and API caches when deploying this fix. Public-property response formats, theme overrides, logged-in editor access and internal import/cron/CLI queries retain their existing behaviour. Custom PHP queries that explicitly suppress filters retain that contract; the supported CASAWP card and JSON serializers still check access before rendering.

This protects WordPress-generated property data and API metadata. It does not add download authentication to static upload files, external CDN URLs or import XML/log files; protecting those URLs requires separate storage/server or delivery controls.

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

Normal logging summarizes routine offer and attachment outcomes per batch. Routine batch start and successful scheduling entries are shown only when `casawp_import_log_level` returns `debug`; batch finishes, processing results, cleanup phase transitions and totals remain visible normally. Scheduling failures, warnings and errors are always retained. Debug mode adds stage and callback checkpoints plus metadata and changed-attachment messages; unchanged offers and kept attachments remain counted in batch summaries. Warnings and errors always include their context. Signed URL query values, URL credentials and sensitive structured keys are redacted. Import and manual cleanup completion summaries always use English text and English number formatting, independently of the site's language; settings-page notices remain translated. Dashed separators mark "Start of import", "Start of cleanup", "End of cleanup" and "End of import", retaining valid JSON on every nonempty log line. Import boundaries apply to manual and gateway-triggered imports, in batch and single-request mode. Cleanup starts are logged once per run across background batches; standalone cleanup uses only the cleanup boundaries. The end-of-import separator follows cleanup completion. The event names are `import.separator.start`, `cleanup.separator.start`, `cleanup.separator` (cleanup end), and `import.separator.end`.

Debug mode retains the timestamp/message layout with compact structured `_meta`. It writes `stage.started` before entering each import step. Successful `stage.finished` entries are retained only for segments lasting at least 100 ms; `stage.failed` entries are always retained. Fast stage starts therefore normally have no matching finish: follow the latest stage start for the request. Successful individual offer start/finish, unchanged-offer and kept-attachment messages are omitted; their counts remain in batch summaries. Full batch durations remain on batch finish entries. Checkpoints cover feed parsing/conversion, offer lookup and saves, metadata, taxonomies, WPML linking, archive statistics, batch progress/scheduling and individual media operations such as downloads, insertion, updates, image metadata generation, terms and deletion.

Compact event metadata identifies the request, run, action, batch, stage, property/language, WordPress post, attachment and redacted media URL where applicable. A `request.context` entry supplies shared feed identity, runner context, PHP process ID, memory/time limits and runtime details once per logger/request, repeated only if its run/feed identity changes. Batch entries retain request elapsed time, memory/peak memory and database query counts; batch checkpoints include their property cursor and planning budget. Warnings and errors retain full context, including active callbacks. No SQL statements or query values are recorded; database errors retain only the prefix before quoted details. For example, a property save and its WordPress hook produce entries like these (additional runtime fields omitted here):

```json
{"2026-11-07 12:09:03":"Import stage started: offer_post_update.","_meta":{"event":"stage.started","stage":"offer_post_update","stage_sequence":17,"run_id":12345,"batch_number":2,"action_id":867,"request_id":"import_example","property_id":"353184","language":"de","post_id":42}}
{"2026-11-07 12:09:03":"WordPress hook started: save_post.","_meta":{"event":"hook.started","hook":"save_post","hook_sequence":3,"stage":"offer_post_update","action_id":867,"request_id":"import_example","post_id":42}}
{"2026-11-07 12:09:04":"WordPress hook finished: save_post.","_meta":{"event":"hook.finished","hook":"save_post","hook_sequence":3,"hook_duration_seconds":0.842,"hook_query_count":18,"action_id":867,"request_id":"import_example"}}
```

Selected property/attachment save hooks and WPML language-link hooks have `hook.started`/`hook.finished` records. A `hook.callbacks` entry lists the registered callback names and priorities when that hook is first encountered by the logger; it does not claim that every listed callback ran.

While a debug import is active, individual `save_post` callbacks have `callback.started` and `callback.finished` records with the original callback name, priority, sequence number, duration and query count. Exceptions produce `callback.failed` and are rethrown; fatal errors retain the active callback stack. A worker killed without a shutdown handler leaves its last callback start on disk. Match callbacks by request ID and callback sequence, including recursive saves. WordPress callback IDs, priorities, accepted arguments and return values are preserved; original callable entries are restored when the outer import operation exits, without reinstating callbacks removed or replaced by plugins. The tracer refreshes at hook entry and after traced callbacks so newly registered callbacks at later priorities can be traced. Callbacks added during dispatch at an already iterating priority follow WordPress's normal dispatch behaviour and may first be traced on a later invocation. The tracer does not record callback arguments or change plugin settings. Exceptions retain their file, line and stack frames without arguments.

WordPress HTTP API calls made during an active import operation have `http.started`/`http.finished` entries with the redacted URL, timeout, result status/error and duration. Request/response bodies, HTTP headers and callback arguments are not logged.

Enable detailed logging with the [Import debug hook](#import-debug-hook) above. Debug mode adds file writes around operations; normal mode emits no stage/hook/HTTP checkpoints. Diagnostics read the existing query counter without enabling `SAVEQUERIES` or adding database checkpoints.

Active imports record fatal PHP errors, including memory exhaustion and PHP execution timeouts, with the last known stage. Fatal diagnostics are written before status/queue repair, so a failure during that repair cannot erase the original evidence. A process killed by the server or operating system cannot write a shutdown entry. In that case, find the latest stage start and any unmatched callback, hook or HTTP start for the failed action/request; it identifies where execution stopped, while the hosting/PHP logs are still needed to establish why the process was killed. If the monthly log cannot be written, the entry falls back to the PHP error log.

Import file cleanup
===================

The General settings page also provides **Run legacy data cleanup** in its own block below **Delete all property data**. It runs the attachment, file, taxonomy and log cleanup in background batches, preserving existing property posts and their content. An activity bar stays visible while background batches run. A status message shows its phase, batch and freed MB; the completion summary also lists removed unused attachment records, local import files and empty taxonomy terms; monthly logs contain the detailed results. This action requires administrator permissions and a nonce. It does not download or import a feed, and works without `data.xml`: the current site's stored property IDs, across all languages and post statuses, protect active folders when CDN is disabled. With CDN enabled, `externalsync` cleanup removes unreferenced files and safely replaceable local copies, while preserving files still required by retained local attachments. Its state and totals are separate from import progress. It shares the import lock and stops if the stored property ID set or CDN mode changes. Newly requested imports wait until manual cleanup releases the lock. The existing delete-all action remains separate. If a manual cleanup makes no progress for ten minutes, **Resume cleanup** requeues its saved batch without resetting its cursor or totals. **Stop cleanup** cancels the run’s queued actions and asks an active worker to stop before further deletion; already deleted data stays deleted and partial totals remain visible. Stopping releases the cleanup’s import lock and allows a deferred import to continue. Both controls require administrator permissions, the cleanup nonce and the displayed run ID. Busy cleanup workers automatically retry their batch after 30 seconds, and continuations are scheduled after the worker lock is released.

Active manual cleanups also register a per-site WordPress cron recovery check every minute. After two minutes without progress, a missing background action is automatically requeued; the cron check can execute one bounded batch directly if Action Scheduler or its loopback chain stops advancing. Live workers are left alone until their ten-minute lease expires. Recovery preserves the saved run, cursor and totals, respects administrator stop requests and changed property/CDN settings, and stops after five recovery attempts on the same batch without progress. Progress resets that retry limit. Delayed failure notifications from older batches do not fail an advanced run. Web queue runs are capped at 20 seconds while manual cleanup is active, leaving headroom below hosting request limits; WP-CLI limits are unchanged. Completion, stop, failure and plugin deactivation remove the recovery event. WordPress cron must be triggered for each affected subsite; automatic recovery cannot run while the host or all cron triggers are unavailable.

Import progress reserves its final percent for cleanup: the import stays at 99% while a separate activity bar and current phase show that maintenance is still running. Only completed cleanup releases the import controls and reports 100%. Cleanup activity bars are indeterminate because the remaining work is discovered across batches. Both cleanup indicators use the import green with a slow five-second movement in each direction, and stop animating when reduced motion is preferred. Import and cleanup bars disappear immediately on completion; the result summary is logged and remains visible for up to ten minutes after completion or until another run starts. Reopening settings within that window shows the remaining time without extending the expiry. Expired results stay hidden. Starting either an import or manual cleanup dismisses the previous completion notice from the other operation immediately and persistently. Status responses from before a new manual cleanup are ignored, so stale results cannot reappear alongside the current operation. The saved counts and log entries remain available. Status polling continues after completion so subsequent background imports are also visible.

The import bar, status text and controls share the same run identity. Starting an import immediately shows preparation, and server-rendered status displays an existing import as soon as settings opens. Gateway retrieval, queue waiting, property processing and finalization have distinct messages. Action responses refresh the display immediately, and status polling ignores responses from before a newer action or run. Status requests time out after 15 seconds and retry instead of leaving a permanently frozen display. Returning to the browser tab refreshes the status. Cancelling and starting again restores both controls, and import controls respect standalone cleanup activity. Successful cleanup with warnings is identified in the completion summary; its existing ten-minute display window is retained.

CASAWP allows one active import per site. Background requests and failure notifications from a cancelled or completed run can arrive after another import starts, because cancelling scheduled work cannot instantly terminate a PHP request already executing. Jobs check their run identity before further work and before changing shared import state. Leftover jobs or failure notifications from a previous run therefore cannot clear the current run's lock or mark it as failed. A failed batch marks its own import as stopped, cancels its queued work and releases its lock. The page then hides its activity bars and directs the administrator to the logs. Starting another import consumes the old pending flag so it does not cause an extra repeat import.

Gateway retrieval uses a 15-second connection timeout and a 120-second total timeout, configurable with `casawp_gateway_connect_timeout` and `casawp_gateway_timeout`. Fatal PHP errors also fail the owning import when PHP can run its shutdown handler. Public gateway pokes and their scheduled import paths retain their existing endpoint and do not require an admin nonce.

Successful import finalization also cleans the current site's `casawp/import` directory in small batches. It removes the historical `data-done.xml` and `data-error.xml` files, unreferenced files belonging to properties absent from the imported feed, and empty property directories, including `externalsync` and its `attachment` parent when empty. Unreferenced Finder `.DS_Store` metadata inside attachment folders is also removed so it does not keep otherwise empty folders alive. `data.xml` and unrelated XML files are retained. A changed or invalid feed, a stale/canceled run, or recorded import/media failures prevents this file cleanup. Symbolic links are not followed.

Before sweeping files, cleanup removes detached legacy attachment records with no parent or a missing parent. It targets CASAWP import paths and CASAGATEWAY/CDN media GUIDs, including historical remote URLs stored in `_wp_attached_file`. It also removes leftover post metadata for missing attachment posts identified by their CASAWP import path; unrelated orphan metadata is retained. Local legacy media for active properties is retained when CDN is disabled.

Source ownership and the absence of an existing parent determine which legacy records are removed. Cleanup does not search arbitrary content, metadata or options for matching IDs or filenames, so the same rule applies across installations regardless of property measurements, postal codes, custom fields or old cached data. Attachments with an existing parent and ordinary local files marked as manual additions are retained. A WPML counterpart with an existing parent or preserved manual ownership protects its translation group, across all languages. WordPress attachment deletion APIs run with physical file deletion temporarily suppressed; the guarded file sweep handles physical deletion. Custom integrations can retain a detached record through `casawp_legacy_attachment_usage` (reason string and attachment ID); retained records that still require local files protect those files during CDN cleanup. Unsafe paths and symbolic links are retained.

Candidate selection bounds each source before merging its ordered page, so a large backlog does not have to be merged in full for each batch. Posts and metadata are loaded into WordPress caches in bulk. Follow-up import and manual cleanup batches are eligible immediately, without an artificial delay; Action Scheduler still controls execution and its normal request limits. The `casawp_legacy_attachment_cleanup_batch_size` filter defaults to 50 records, capped at 200. Normal logs report removed/retained counts; debug logs identify retained records and reasons.

With the CDN option enabled, CASAGATEWAY/CDN media of all supported types (`image`, `document`, `plan`, `offer-logo`, and `sales-brochure`) are imported as remote-only attachments. This includes PDF documents and plans. Cleanup removes unreferenced files from `import/attachment/externalsync` and local copies of attachments that can safely use a remote source, including generated previews. Retained attachments without a usable remote source protect their local files and derived files, regardless of extension or active property IDs. Files shared with other local consumers are also retained. These protections apply directly in `externalsync` and in nested subfolders. Empty property folders, `externalsync`, and its `attachment` parent are removed after their children. Symbolic links are skipped. Other attachment folders retain their local-consumer protections.

Attached records remain in WordPress. For attachments with a usable remote source, cleanup preserves their IDs, descriptions, source URLs and image dimensions, marks them as remote, and clears obsolete local file metadata. Historical records in `externalsync` do not need an import marker or attachment taxonomy for this conversion. Records without a usable remote source are not marked as remote; their local file metadata and files remain available so existing document links continue to work. File metadata is kept when physical deletion fails so a later cleanup can retry.

Disabling CDN does not automatically restore removed local files. If local media are needed again, run a manual media resync.

After the file sweep, cleanup removes unused terms from registered `casawp_*` taxonomies, except `casawp_availability` and `casawp_salestype`. Within `casawp_attachment_type`, required standard types (`image`, `document`, `plan`, `offer-logo`, `sales-brochure`) and their WPML counterparts are also retained when unused. Plugin registration ensures these terms exist; deleting them would recreate the same cleanup work on every run. Unused custom attachment types remain eligible for cleanup. It checks actual term relationships rather than cached term counts, across all languages, and preserves terms with used WPML counterparts to avoid synchronized deletion affecting assigned translations. Deletions use WordPress APIs and their WPML hooks. The term pass is batched and bounded to terms present when it starts; terms created during cleanup are retained until a later import. Normal logs report term deletion counts, with individual IDs in debug mode.

Exceptions thrown by individual term-deletion hooks (for example, Yoast indexable deletion) are logged as warnings with the term ID, taxonomy, exception trace and whether the term was actually removed. Cleanup checks the database after the exception, counts any completed deletion, advances past the affected term and continues with later terms and old-log cleanup. Retained terms can be retried on a subsequent cleanup. WordPress deletion hooks remain enabled. Database errors and unsafe or stale runs still stop cleanup. A stopped manual run preserves its last known phase and partial totals in its state and failure log.

Cleanup logs identify each phase: database properties, database attachment records, filesystem files/folders, taxonomy terms, and old logs. Batch finish messages identify their phase, and phase transitions/completion are logged separately. Debug logging also includes batch starts and scheduling messages naming the next phase. Retained database record counts are explicitly labeled. File/folder counts and freed space are logged, with individual deleted file paths in debug mode. The last cleanup batch writes its finished entry before the overall totals and completion summary, for both imports and manual cleanup. Finalization remains covered by exception and fatal-error logging; a failure after batch completion is identified separately. A final summary combines the MB freed across all attachment/XML cleanup batches and old-log deletions, with a breakdown for each. The `casawp_file_cleanup_batch_size` and `casawp_file_cleanup_time_limit` filters default to 200 filesystem entries and 5 seconds per batch.

Successful import finalization also removes monthly log files except those from the current and previous calendar month, based on the site's local date. It recognizes standard `YYYYMM.log` names, legacy English month names such as `2020 Aug.log` and `2020 August.log`, and separated numeric names such as `2020-08.log` or `2020_8.log`. Spaces, hyphens and underscores are accepted as separators; month names and the `.log` extension are case-insensitive. For example, during October 2026 it keeps both October and September logs in every recognized format, regardless of file modification time. Imports, standalone cleanup and the monthly cron share this routine. Custom log names, invalid month names, directories and symbolic links are skipped. Cleanup reports deleted file counts and freed space, and logs deletion failures.
