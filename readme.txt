=== CasaSync ===
Contributors: casasoft
Donate link:http://immobilien-plugin.ch
Tags: immobilien, real estate, openimmo, idx
Requires at least: 3.5.2
Tested up to: 4.0.0
Author: Casasoft AG
Author URI: http://casasoft.ch
License: GPL2
Stable tag: 3.0.9

Das WP Immobilien-Plugin für Ihre Website importiert Immobilien aus Ihrer Makler-Software!

== Description ==

Synchronisieren Sie Ihre Immobilien Objekte mit CasaSync, Lassen Sie die Einträge automatisch Kategorisieren und passen Sie das Plugin an jedes Theme an. CasaSync importiert, flexibel, zuverlässig und schnell die üblichsten Standards.

== Installation ==

Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your WordPress installation and then activate the Plugin from Plugins page.

== Frequently Asked Questions ==

= Does this plugin require a real-estate software? / Benötigt dieses Plugin eine Maklersoftware? =

It makes things alot easier. See casasoft.ch[http://www.casasoft.ch] for more details. There are currently no plans to make this plugin editable/interactive, this can however change.
Es macht Sachen viel einfacher. Siehe casasoft.ch[http://www.casasoft.ch] bezüglich einer Software. Momentan ist ein Plugin-Bearbeitungsinterface nicht geplant. Allerdings kann dies auch ändern.

= So there is absolutely no way to edit the real-estate listings manualy!? =

Well, yes and no. Essentialy the plugin activates custom post types and a rendering engine for custom meta information. If you are savy enough to enter custom fields yourself, you may go ahead and use this plugin without a export Software. If you wish to build a interface on top of that, we recommend the "Advanced Custom Fields" plugin to make your job easier. However, be aware of changes/additions to fields and behaviors within the future.

= How can I import properties =

The plugin is based on the casaXML[http://github.com/casasoftCH/casaXML] standard. If you can somehow generate this simple xml standard, than you can simply place it in the appropriate directory (/wp-content/uploads/casasync/import/data.xml) and activate a import (either this happens automaticaly, or you may activate it manually within WordPress). Essentialy you can keep this file up-to-date to ensure a synchonised state (be aware that the file will be renamed data-done.xml once imported). Currently supported is CasaXML build 5, but build 6 will eventually be prefered as soon it has been blessed.

== Changelog ==

= 3.0.9 = 
* added: more translatable categories

= 3.0.8 = 
* fixed: bug with featherlight and bootstrap 2
* added: page-attributes for post type casasync_property
* added: thumbnail gallery

= 3.0.7 = 
* fixed: admin-scripts.js bug
* new: offer-logo import and single->getLogo() function
* new: single->getSalesPerson() & single->getSeller() optional Titles/Icons

= 3.0.6 = 
* fixed: what was ment to be done at previous version

= 3.0.5 = 
* fixed: featerlight assets were not synced correctly

= 3.0.4 = 
* fixed: language imports
* fixed: imports will now happen discretely
* new: loging
* new: replaced fancybox with featherlight MIT
* fixed: many many bugfixes

= 3.0.3 = 
* new: rewrote entire import script
* bug: changed position of email field in contact form
* new: documents can now be displayed
* bug: pagination fixed
* new: WPML support
* new: converted translations to po files
* new: automatically translated ru,fr,it,ja^^

= 3.0.2 = 
* warning: single and archive tempalte files were renamed
* bug: fixed displaying of floors and organization number
* bug: changed structur of basic boxes, no empty lines
* bug: fixed pagination
* new: google maps can now switched off per zoom = 0
* new: added new contactfield for country
* new: added option for sorting archive. able to sort by date, time, price, location

= 3.0.1 = 
* bug: pref next links in single fixed for multisite installation and stability
* bug: Archive pagination now behaves like it should in bs2 and bs3
* bug: Changed fonts directory to font without the s and unified the css to target the same one
* bug: fixed ajax requests sent by single pagination with large amounts of locations/properties


= 3.0.0 = 
* new: Vollständige Überarbeitung des Plugins. Bitte beachten!
* new: Twitter Bootstrap 3 Support (Mit BS2)
* new: Objektorientiertes verhalten (alte Template Engine wird nicht mehr unterstützt!)

= 2.1.6 = 
* bug: Street number import and display

= 2.1.5 = 
* bug: emaillink fix

= 2.1.4 = 

* bug: fixes

= 2.1.3 = 

* new: {availabilitylabel}
* new: Availability import 'planned', 'reserved', 'reference', 'under-construction' etc
* new: {if_planned}, {!if_planned}
* new: {if_reference}, {!if_reference}
* bug: False translations "Bahnhof -> Autobahn"
* bug: Genereral stability improvements
* warning: This version contains a alternate back button that is not realy desirable as a solution and intended to be temporary.
* new: Google Maps API instead of iframe (Reduces console errors and a more reliable localisation)
* new: Map disapears if it could not find a match

= 2.1.2 = 

* Fixed tiny slash error
* Re-Commited translation files
* Fixed some translational mishaps
* grossPrice should now work

= 2.0 = 

* Moved it to WordPress

= 1.0.1 =

* Fixed location import
* Made location multiselect pretier

= To do = 

* bug: Sometimes the map fails to load

== Upgrade Notice ==
-

== Screenshots ==

1. Archiv Übersicht
2. Einzel Objekt
