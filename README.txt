=== CASAWP ===
Contributors: casasoft
Donate link: http://immobilien-plugin.ch
Tags: immobilien, real estate, openimmo, idx
Requires at least: 4.0.0
Tested up to: 4.3.1
Author: Casasoft AG
Author URI: https://casasoft.ch
License: GPL2
Stable tag: 2.0.0

Import your properties directly from your real-estate managment software!

== Description ==

Synchronize your properties directly to CASAWP, and let them be automaticaly categorized. Adjust the plugin to any theme. CASAWP imports the most common standards with the help of CASAGATEWAY without a hitch.

*** This plugin requires at least PHP 5.5.0

== Installation ==
1. Upload "casawp" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Setup your import method (see "Import Setup" for further instructions).
4. Check out yourdomain.com/immobilien to verify that it works
5. Adjust the theme files to your liking

== Import Setup ==
The plugin will look for a file in "wp-content/uploads/casawp/import/data.xml" and syncronize this data with your WordPress installation.
 
You can either place that file through FTP/Rsync or something similar.

If you are unable to export to the CASAXML standard with your management software, we can provide you with a CASAGATEWAY account so that you may convert (OpenImmo, HomegateXML, etc.) on the fly. Please refer to casasoft.ch[https://casasoft.ch/casagateway] for further instructions.

== Changelog ==
= 2.0.0 = 
* The plugin has been rewritten and renamed from casasync to CASAWP.

== Upgrade Notice ==
* Please follow the instructions upgrade-from-casasync.md if you are upgrading from casasync

== Screenshots ==
1. Archive View
2. Single View

