=== CASAWP ===
Contributors: casasoft
Donate link: https://immobilien-plugin.ch
Tags: immobilien, real estate, openimmo, idx, casaXML, SwissRETS
Requires at least: 4.0.0
Tested up to: 6.6.2
Author: Casasoft AG
Author URI: https://casasoft.ch
License: GPL2
Stable tag: 3.1.0

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

== Import Setup ==
The plugin will look for a file in "wp-content/uploads/casawp/import/data.xml" and syncronize this data with your WordPress installation.

You can place the XML file through FTP/Rsync or something similar.

If you are unable to export to the CASAXML standard with your management software, we can provide you with a CASAGATEWAY account so that you may convert (OpenImmo, HomegateXML, etc.) on the fly.
You will then only need to add the API and SECRET keys to the plugin's options.

Please refer to casasoft.ch[https://casasoft.ch/casagateway] for further instructions.

== Changelog ==

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
