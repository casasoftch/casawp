=== casawp ===
Contributors: casasoft
Donate link:http://immobilien-plugin.ch
Tags: immobilien, real estate, openimmo, idx
Requires at least: 3.5.2
Tested up to: 4.3.1
Author: Casasoft AG
Author URI: http://casasoft.ch
License: GPL2
Stable tag: 2.0.0

Das WP Immobilien-Plugin für Ihre Website importiert Immobilien aus Ihrer Makler-Software!

== Description ==

Synchronisieren Sie Ihre Immobilien Objekte mit CasaWp, Lassen Sie die Einträge automatisch Kategorisieren und passen Sie das Plugin an jedes Theme an. CasaWp importiert, flexibel, zuverlässig und schnell die üblichsten Standards.

== Installation ==

Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your WordPress installation and then activate the Plugin from Plugins page.

== Frequently Asked Questions ==

= Does this plugin require a real-estate software? / Benötigt dieses Plugin eine Maklersoftware? =

It makes things alot easier. See casasoft.ch[http://www.casasoft.ch] for more details. There are currently no plans to make this plugin editable/interactive, this can however change.
Es macht Sachen viel einfacher. Siehe casasoft.ch[http://www.casasoft.ch] bezüglich einer Software. Momentan ist ein Plugin-Bearbeitungsinterface nicht geplant. Allerdings kann dies auch ändern.

= So there is absolutely no way to edit the real-estate listings manualy!? =

Well, yes and no. Essentialy the plugin activates custom post types and a rendering engine for custom meta information. If you are savy enough to enter custom fields yourself, you may go ahead and use this plugin without a export Software. If you wish to build a interface on top of that, we recommend the "Advanced Custom Fields" plugin to make your job easier. However, be aware of changes/additions to fields and behaviors within the future.

= How can I import properties =

The plugin is based on the casaXML[http://github.com/casasoftCH/casaXML] standard. If you can somehow generate this simple xml standard, than you can simply place it in the appropriate directory (/wp-content/uploads/casawp/import/data.xml) and activate a import (either this happens automaticaly, or you may activate it manually within WordPress). Essentialy you can keep this file up-to-date to ensure a synchonised state (be aware that the file will be renamed data-done.xml once imported). Currently supported is CasaXML build 5, but build 6 will eventually be prefered as soon it has been blessed.

== Changelog ==

= 2.0.0 = 

* The plugin has been rewritten and renamed from casasync to CASAWP.

== Upgrade Notice ==

* Please follow the instructions upgrade-from-casasync.md if you are upgrading from casasync

== Screenshots ==

1. Archiv Übersicht
2. Einzel Objekt

