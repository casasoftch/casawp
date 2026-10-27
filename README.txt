=== CASAWP ===
Contributors: casasoft
Donate link: https://immobilien-plugin.ch
Tags: immobilien, real estate, openimmo, idx, casaXML, SwissRETS
Requires at least: 4.0.0
Tested up to: 7.1.3
Author: Casasoft AG
Author URI: https://casasoft.ch
License: GPL2
Stable tag: 3.5.2

Import your properties directly from your real-estate management software!

== Description ==

Synchronize your properties directly to CASAWP, and let them be automatically categorized. Adjust the plugin to any theme. CASAWP imports the most common standards with the help of CASAGATEWAY without a hitch.

*** This plugin requires at least PHP 8.1

*** For further technical infos concerning this plugin refer to the readme.md file included within the plugin files.

== Installation ==
1. Upload "casawp" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Setup your import method (see "Import Setup" for further instructions).
4. Check out yourdomain.com/immobilien to verify that it works
5. Adjust the theme files to your liking

== Settings Page ==

Open CASAWP in the WordPress admin menu. A responsive sidebar groups General, Archive, Detail view, Protected properties, Contact form, Scripts, and Logs. Settings appear in cards, and the "Save changes" bar stays within reach while scrolling. The page supports widths up to 1920 px and adapts to smaller screens.

Poppins titles, Mulish body text, CASASOFT-inspired blue buttons, subtle field backgrounds, and clear focus indicators give the settings a consistent appearance. Fonts and licenses are bundled locally and loaded only on this page, without requests to Google Fonts. Code fields retain a monospace font. Section titles sit inside the first white card, aligned with the white navigation panel. Text inputs and textareas share a maximum width of 400 px. Custom checkboxes use the button blue with a white checkmark, and the sticky bottom bar contains only the save action. The delete-all action uses a solid red button with white text. All settings dropdowns use a locally bundled Select2 theme with matching blue focus and selection states, keyboard navigation, and search for lists of ten or more options. The original select fields and saved values are retained, with native dropdowns available if JavaScript fails. The library uses a private jQuery registration to avoid replacing another plugin’s Select2. Menus close on outside clicks. A head-loaded pending state prevents native dropdowns flashing during tab navigation; it clears after initialization or restores native controls automatically if enhancement fails.

Archive filters are grouped in one card, including display types and manual room/area ranges. "Show net rent" appears in the Price section. Help documentation and GitHub links are available in the page header. Settings labels support German, English, French, and Italian. Existing option keys and tab URLs are retained.

== Import Setup ==
The plugin will look for a file in "wp-content/uploads/casawp/import/data.xml" and syncronize this data with your WordPress installation.

You can place the XML file through FTP/Rsync or something similar.

If you are unable to export to the CASAXML standard with your management software, we can provide you with a CASAGATEWAY account so that you may convert (OpenImmo, HomegateXML, etc.) on the fly.
You will then only need to add the API and SECRET keys to the plugin's options.

Please refer to https://help.casasoft.ch/hc/de/articles/22037480338845-CASAGATEWAY-API for further instructions.

How to trigger the import through WP CLI (--url for Multisites)
wp casawp import --url=https://example.com

For detailed import logs, including individual attachments and batch start/scheduling entries, add this filter to your theme's functions.php or as a PHP snippet in the Code Snippets plugin. In Code Snippets, select "Run everywhere" and activate the snippet:

add_filter('casawp_import_log_level', static function () { return 'debug'; });

Remove or deactivate the filter to return to compact batch summaries.

After a successful import, cleanup removes data-done.xml and data-error.xml, unused files in obsolete property folders, and empty attachment folders, including externalsync and its attachment parent when empty. Finder .DS_Store files inside attachment folders are also removed so they do not keep otherwise empty folders alive. With CDN enabled, CASAGATEWAY/CDN images, documents, plans, logos, and brochures are imported without local downloads. Cleanup removes unreferenced externalsync files and old local copies and generated previews of attachments that can safely use a remote source, preserving attachment records and image dimensions. Retained attachments without a usable remote source keep their local files and generated previews; files shared with other local consumers are also protected. Symbolic links are skipped. After switching CDN off, restore removed local attachments through a manual media resync.

Cleanup also removes detached legacy CASAWP attachment records with no existing parent, including old CASAGATEWAY remote-only records, and leftover metadata for missing CASAWP attachment posts. Source ownership and the absence of an existing parent determine deletion; matching IDs or filenames in content, saved metadata and options do not retain these records. Attachments with existing parents and their WPML translation groups are retained. Local files for active properties are retained when CDN is disabled. Debug logging explains why legacy records were retained.

After attachment and file cleanup, unused terms in registered casawp_* taxonomies are removed, except casawp_availability and casawp_salestype. Required standard attachment types (image, document, plan, offer-logo, sales-brochure) and their WPML counterparts are retained even when unused, because plugin registration needs them and would recreate them. Unused custom attachment types can still be removed. Cleanup checks actual relationships across all languages and preserves terms with used WPML counterparts. Records and terms are removed through WordPress APIs so WPML deletion hooks run normally.

Use "Run legacy data cleanup" below "Delete all property data" in General settings to run attachment, file, empty-term and old-log cleanup without importing properties. Existing properties are retained. This runs in background batches, shows an activity bar, its current phase and freed MB, and writes detailed progress to the monthly logs. It works without data.xml and protects folders belonging to properties currently stored in WordPress across all languages when CDN is disabled. With CDN enabled, externalsync cleanup preserves files still required by retained local attachments. Imports and manual cleanup cannot run concurrently. Use "Resume cleanup" after ten minutes without progress to continue from the saved batch. "Stop cleanup" stops further deletion, keeps the partial results and releases the import lock after the worker stops. Deletions already made are retained. Interrupted manual cleanups recover automatically through a per-site cron check, with a bounded direct-batch fallback when the background queue stalls. Cron must run for each subsite. Recovery respects Stop and active workers; five retries without progress on the same batch stop the run for investigation.

Import cleanup also removes monthly log files except those from the current and previous calendar month, using the site's local date. It recognizes current names such as 202610.log and legacy names such as 2020 Aug.log, full English month names, and separated numeric month names. The monthly cron cleanup uses the same retention rule, so old logs are removed even if cron has not run.

== Changelog ==

= Unreleased =

* Security: Require administrator permissions and verified POST requests for settings saves and AJAX import start, cancel and progress reset; save only fields belonging to the selected settings tab
* Fix: Preserve quoted settings values, backslashes and the tracking property-ID placeholder across saves; escape stored values in form fields and HTML textareas
* Fix: Preserve local document files and generated previews during CDN cleanup when retained attachments lack a usable remote source or share files with local consumers
* Fix: Validate import feed structure and property IDs before changing existing listings or running queued deletion work; reject invalid responses while preserving intentional empty-feed cleanup
* Fix: Show a PDF placeholder for remote documents in the Media Library, preserve existing previews and document links, and prevent non-image attachments from rendering as broken images
* Fix: Save the disabled state when Google reCAPTCHA v3 is unchecked in Contact form settings
* Enhancement: Use locally bundled Poppins and Mulish fonts, CASASOFT-inspired buttons, and cleaner fields with clear focus indicators on the settings page
* Enhancement: Suppress third-party notice callbacks on the CASAWP settings page while preserving CASAWP messages and leaving other admin screens unchanged
* Enhancement: Add the CASASOFT logo above the settings, linking to casasoft.ch in a new tab
* Enhancement: Group archive filters in one card and label the net-rent option under Price
* Enhancement: Add a short plugin introduction and links to the CASAWP help documentation and GitHub repository above the settings tabs, including German translations
* Change: Remove the obsolete WordPress.org rating and changelog buttons from the plugin settings page
* New: Modernize the settings page with responsive sidebar navigation, grouped cards, clearer controls, a sticky save bar, and a flexible header in a layout up to 1920 px wide

* Fix: Continue cleanup after individual term-deletion hook exceptions, including Yoast indexing failures; log the affected term and actual deletion result, retain warning counts and report the correct failure phase
* Fix: Include legacy monthly log names such as "2020 Aug.log" in cleanup, preserving current/previous months across supported filename formats
* Performance: Speed up database cleanup with bounded candidate queries and bulk cache loading; schedule follow-up cleanup batches without an artificial delay
* Enhancement: Show deleted attachment, local import file and empty taxonomy term counts alongside freed MB when import or standalone cleanup finishes
* Enhancement: Reserve 100% import progress until cleanup finishes; show cleanup activity bars and current phases for imports and standalone cleanup
* Enhancement: Clearly label database, filesystem, taxonomy, and old-log cleanup phases in batch progress and scheduling messages
* Enhancement: Clean detached legacy import attachment records and orphaned CASAWP metadata in batches, preserving attachments with existing parents and their WPML translation groups
* Enhancement: Remove empty CASAWP taxonomy terms after attachment/file cleanup, excluding availability and sale type and preserving used translations
* Enhancement: Log the total MB freed by attachment/XML file cleanup and old-log retention at the end of import cleanup
* New: Add a standalone "Run legacy data cleanup" button below "Delete all property data", with background progress, shared import locking, translated status messages, and cleanup without requiring data.xml
* New: Clean obsolete import XML files, leftover property folders, and local CDN attachment copies in batches after successful imports

* Fix: Repair missing offer-logo attachment terms and ignore empty XML features so these import errors no longer block file cleanup
* Fix: Only show log and import XML download links when the corresponding files exist
* Fix: Use the site's uploads directory for log links and older log file checks, including multisite subsites
* Fix: Use HTTPS for log and XML links when the admin page uses HTTPS to prevent insecure download blocking
* Fix: Keep the Scheduled Actions menu visible when WPForms is active
* Fix: Load property metaboxes reliably regardless of plugin load order, without requiring WPML reactivation
* Enhancement: Clean monthly logs after successful imports and through cron, retaining only the current and previous calendar month
* Enhancement: Apply CDN mode to imported images, documents, plans, logos, and brochures; clarify the CDN setting label
* Enhancement: Improve JSON import logging with readable batch summaries, run and property context, detailed failure diagnostics, and optional attachment debug logging
* New: Add a link to trigger a gateway poke for the current site from the General settings tab
* New: Add a data.xml download link beneath the logs using the site's uploads URL
* New: Show an attached media overview with previews and image-type assignments below the metaboxes in the property edit screen

= 3.5.2 =

* Fix: Cache failed remote media downloads across properties and languages to avoid repeated requests to unavailable sources
* Fix: Apply explicit remote media download timeouts and log failure status, duration, and retry cooldown
* Fix: Discard failed or empty downloads before they can become local media files

= 3.5.1 =

* Fix: Preserve general settings, including custom slug and language, when saving Protected properties or Logs
* Fix: Preserve general email settings when saving other tabs
* Fix: Support German (Switzerland) with the existing German plugin translations
* Fix: Ship regional translation catalogs as real files to prevent gettext errors after installation

= 3.5.0 =

* New: Route Flatfox property inquiries directly in REMCAT format

= 3.4.9 =

* Fix: Ignore invalid upstream features and only display features defined by Casasoft Standards

= 3.4.8 =

* Enhancement: Translate the dedicated CASAWP settings page in German, English, French, and Italian

= 3.4.7 =

* Change: Gateway pokes are handled asynchronously and respond with HTTP 204 instead of rendering a full page

= 3.4.6 =

* Fix: guard Laminas FormMultiCheckbox view access on PHP 8

= 3.4.5 =

* Fix: Missing feature translations

= 3.4.4 =

* Fix: Legacy CDN images support before re-imports

= 3.4.3 =

* Fix: missing translator declaration in HeatService

= 3.4.2 =

* Fix: Document links broke in recent patch

= 3.4.1 =

* Fix: New self-healing sometimes caused batch fails

= 3.4.0 =

* New: Option to import content with Auto Translate
* New: Structured Data for Single Views
* New: ignore SEO meta fields for import
* New: Heating now integrated
* Update: ES translation fixes
* Update: Filter price range addition
* Fix: Structured Data fixes & Yoast harmonization
* Fix: Hashing now also considering image order changes
* Fix: Media Import handling is now more robust with CDN usage
* Fix: WPML Import hardening
* Fix: Media import hardening
* Fix: IT translation fixes
* Fix: Action Scheduler no longer seen as separate plugin (fixes direct installation issues)
* Fix: translations no longer symlinks

= 3.3.0 =

* update: Batch import as new default method, with new single request import option

= 3.2.6 =

* fix: Title & Content updates only were skipped due to hashing

= 3.2.5 =

* fix: Post thumbnail was not always being set

= 3.2.4 =

* fix: Custom category & custom region change recognition

= 3.2.3 =

* fix: Working hashing for media

= 3.2.2 =

* fix: Working hashing for custom categories

= 3.2.1 =

* fix: Working hashing for taxonomies

= 3.2.0 =

* new: Added possibility to order by rand in queries
* new: Option for single request imports
* new: Option to delete all properties and media through direct SQL command button
* update: Added new categories and translations
* update: More robust import handling (hashing, cancellations, pending imports)
* update: Enhanced logging and debugging possibilities

= 3.1.4 =

* update: Adjusted import process for additional early property deletion
* update: Heavily reduced creation of logs / duration of logs being saved
* fix: Updated method to purge WPEngine caches

= 3.1.3 =

* fix: remove unused crons

= 3.1.2 =

* update: Import alerting on fail & increase of Action Scheduler timeout
* update: Translations
* fix: Security upgrades preventing injections
* fix: Several PHP warnings

= 3.1.1 =

* fix: Recaptcha V2 integration error
* fix: Laminas "Project" service


= 3.1.0 =

* new: Option to override dynamic batch sizes for import
* update: remove midnight cron for import
* fix: Laminas Services & Factories

= 3.0.0 =

* new: Asynchronous queue importer with integrated Action Scheduler & progress bar
* new: Option to automatically set property translations 
* new: Recaptcha V3 integration option
* new: Option to force XML language of properties
* new: Option to add data protection checkbox for forms
* update: Migrated Zend Framework 2 to Laminas framework
* update: Casamodules for Laminas
* update: Conversion service & sanitizer updates
* update: Rooms & area considered for order
* update: Location filter can now also filter lvl 1 & 2 (country / region)
* update: Various translation updates
* update: Added various new numvals
* update: Added new action hook when import is finished (casawp_import_finished)
* update: New filter routes for price querying
* update: Registerted post type & taxonomies to be available in WP rest API
* fix: Various translation fixes
* fix: PHP 8.2 compatibility issues
* fix: Some filter reductions in plugin.php
* fix: Fixed field name for net & gross price segment
* fix: Location import now sets lvl 3 locality as primary, if Yoast is active
* fix: Import of zone types to meta field was not working
* fix: Various warnings
* fix: Removal of all features on a property did not update correctly
* fix: Availabilities did not always filter utilities

= 2.6.0 =

* new: area filters
* new: possibility for neutral gender in form requests
* update: remove screen_icon
* update: new CASAMAIL url
* update: no dynamic font awesome icons anymore
* update: price_range_custom filter
* update: chosen harvest custom fork for new jquery
* update: jquery migration to 3.5.1
* update: no default maps api key anymore
* update: language updates
* update: log names
* update: wp-admin ui clean up & improvements
* update: js dependencies
* fix: "parking" was missing as category
* fix: path fix in order to work with child themes
* fix: db prepare function fix for term objects
* fix: PHP 7.4 Zend Framework compatibility fix
* fix: hide form for reference properties
* fix: taxonomy reduction filter fixes
* fix: fixed check for displaying visualReferenceId or referenceId

= 2.5.0 =

* update: backend interface names/strings/descriptions updates and clean up
* new: remote viewing
* new: post type casawp_property now shown in WP Rest API
* fix: send mail to viewPerson, if there is a viewPerson but no inquiryPerson
* new: property_reference ID can now be entered in form shortcode to directly connect a property in the request
* update: import performance improvements
* new: area filters
* fix: deleted "chdir" as this broke some other plugin backend links
* fix: feature filter
* fix: import post title attachment
* update: ajax filters filter each other
* fix: price filter rent
* new: guest toilets
* update: translations
* fix: getAllDocuments
* update: CDN OG Meta
* fix: integrated offers
* new: integrated meta filters

= 2.4.0 =
* update: translations
* update: contact form fixes to allow objects for Filters
* update: preperations for uploads
* update: contact html5 required attributes
* update: inquiries wont be stored anylonger. (DSGVO)
* update: Casadistance
* new: Option to load casawp.js in Footer
* fix: better language detection when load order with WPML is iregular
* update: imports now smartly skipps duplicates ???
* update: Added the Arabic Emirates as an contact form country option field
* new: Buttons to hide and show maps. Map does not load by default. There is an option to reactivate that.
* update: updated dependencies (casadistance, bootstrap4, )
* new: Option to activate gateway CDN for images. (only use with permission)
* fix: odd media names during import
* new: New frontend way to sort by price/date/title/location
* fix: preperations for PHP 7.2


= 2.3.1 =
* fix: fixed javascript bugs for IE 10/11 for Casadistance


= 2.3.0 =
* new: Casadistance
* update: Old map can use specified api key defined in options page
* fix: m3 numeric values now also have seperators

= 2.2.9 =
* update: IT/FR translations
* new: Ausländerwohnung
* update: casastandards
* new: Engadine house
* fix: salestypes shortcode now supports string
* fix: double units for numval rendering
* update: zendframework
* new: Bootstrap 4 integration
* new: Patrizierhaus
* new: Spanish translations
* update: project imports are more reliable now
* update: WPML fix to avoid user language and blog language confusion
* fix: media uploads with query params work now

= 2.2.8 =
* fix: double m/m2 renders
* new: Rental deposit
* update: Country order for contact forms
* update: Allow Reference in Project Properties
* fix: cleaned up project view files
* update: casamodules
* new: custom datapoints option for renderDatapoints method

= 2.2.7 =
* fix: category translator
* update: Translations

= 2.2.6 =
* update: Translations for new Terms
* fix: Message fields can now be defined as required
* new: Legal Name is now a form-standard
* fix: trid import fix for WPML
* fix: Visual Reference ID for view person
* new: Optional extra cost segmentation option
* update: Tighter CASAMODULE implementation “dev-master”

= 2.2.5 =
* update: New feature values and categories
* fix: utility import
* new: rooms, utility, price, region, feature filter
* update: translations
* fix: empty xmls will cause deletions now
* fix: archive ajax optimizations
* fix: legacy query parameters are more stable now
* new: more countries in contact form
* fix: form posts will not cause double posts anylonger
* fix: re-enabled integratedOffers integration
* new: override_name, override_exerpt, custom_description to replace them during import
* fix: required field options work again for contact forms
* new: contact forms now shows if fields are required


= 2.2.4 =
* new: multiple OR kriterias for shortcode integrations
* new: BETA Ajax based archive filtering
* fix: resolved some legacy linking issues
* fix: CASAMAIL project_reference
* fix: Contact Form optional required fields
* fix: CASAMAIL offer mixup
* new: order by modified
* fix: Hiding custom categories works again
* fix: Main language installs other than German will work better now
* new: custom sorting based on import order
* update: updated the casasoft standard-modules
* new: custom field support with import


= 2.2.3 =
* fix: new CASMAIL domain service@casamail.com

= 2.2.2 =
* fix: Salutation should not be a required field. This caused some legacy forms to not post.

= 2.2.1 =
* fix: imports now consider trashed objects as candidates for update
* fix: if the xml contains 0 properties it will be ignored instead (to prevent false deletion) This will be considered in the future.

= 2.2.0 =
* optimized: WPML optimizations
* new: (alpha) Project integration
* new: action hooks casawp_before_inquirystore
* new: Support for additional publisher custom values
* fix: Translations for MVC modules
* fix: Filter labels are now translatable and previewable
* new: [casawp_properties] shortcode
* new: max restriction for archive datapoints
* new: Adjustable filter UI preferenzes
* new: [casawp_contactform] shortcode
* fix: added google maps api key
* new: (alpha) basic private property support with global login
* fix: refactored contact form logic
* new: Custom contact forms
* new: Archive-Filters are now hidable
* optimized: datapoints are more adjustable
* new: Salutation for Contact-Forms are now enabled
* new: private login system through WP User management
* optimized: post_date should now be imported correctly
* optimized: api endpoint is now https://casagateway.ch
* new: Order By start
* new: contactform shortcode now accepts custom direct_recipient_email option
* optimizations: CASAMAILs property_reference now includes the visualReferenceID
* new: datapoint nwf now available for archive views

= 2.1.1 =
* Now ignores modification dates during imports.
* Availability reemerges as hidden field so that requests can creeps back into the query
* isTaken and isActive methods
* Option for limiting image/media imports for reference properties.
* Some translations from Steven
* Hash checking and persistence checks during imports improve subsequent import speeds now
* Some alterations to the poke hook

= 2.1.0 =
* Added categories_not, locations_not, salestypes_not, availabilities_not to query service
* Initial work on similar property system
* Missing ReferenceIDs alerts during imports implemented
* Reworked Offer-Collections and looping
* visitPerson integration
* Added VisualReferenceId Support
* Updated CasaStandarts to 1.0.12
* Fixed custom Category labels
* Fixed casawp berfore after functions
* Fixed double nonce nonsence
* Filter (location) is now affected by availability
* Expandend FilterService to be used in themes to generate seperate WP_query objects
* Added isReserved method to the offer object
* Creation/Modification dates are now respected during XML imports

= 2.0.4 =
* Cleaned up plugin activation hooks for dependency ordering
* Added IE compatibility for Basicboxes
* Translated Contactform Labels
* htmlspecialchars for og:metas
* Added JSON API items
* Fixed excerpt imports
* Fixed gallery thumbnail rendering
* Replaced default arrows

= 2.0.3 =
* Update system

= 2.0.2 =
* Further security preparations for WordPress.org publishing

= 2.0.1 =
* Preparations for WordPress.org publishing

= 2.0.0 =
* The plugin has been rewritten and renamed from casasync to CASAWP.

== Upgrade Notice ==
* Please follow the instructions upgrade-from-casasync.md if you are upgrading from casasync

== Screenshots ==
1. Archive View
2. Single View
