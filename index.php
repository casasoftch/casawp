<?php
/**
 * @package CasaSync
 */
/*
Plugin Name: CasaSync
Plugin URI: http://immobilien-plugin.ch
Description: Das WP Immobilien-Plugin für Ihre Website importiert Immobilien aus Ihrer Makler-Software!
Version: 2.1.4
Author: Casasoft AG
Author URI: http://casasoft.ch
License: GPL2
*/

define('CASASYNC_PLUGIN_URL', home_url() . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/');
define('CASASYNC_PLUGIN_URI', home_url() . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/');
define('CASASYNC_PLUGIN_DIR', plugin_dir_path(__FILE__) . '');

include(CASASYNC_PLUGIN_DIR . 'casasync.php');
?>