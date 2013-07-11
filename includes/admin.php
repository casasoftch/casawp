<?php
/**
 * Admin functions
 **/


/* What to do when the plugin is activated? */
register_activation_hook(__FILE__,'casasync_install');

/* What to do when the plugin is deactivated? */
register_deactivation_hook( __FILE__, 'casasync_remove' );

function casasync_install() {

	//syncronisation
	add_option("casasync_live_import", '0');

	//seller fallback
	add_option("casasync_sellerfallback_show", '0');
	add_option("casasync_sellerfallback_update", '1'); // update from casasync xml head

	add_option("casasync_sellerfallback_address_country", '');
	add_option("casasync_sellerfallback_address_locality", '');
	add_option("casasync_sellerfallback_address_region", '');
	add_option("casasync_sellerfallback_address_postalcode", '');
	add_option("casasync_sellerfallback_address_street", '');

	add_option("casasync_sellerfallback_legalname", '');
	add_option("casasync_sellerfallback_email", 'info@casasoft.ch');
	add_option("casasync_sellerfallback_email_use", 'fallback'); // or always
	add_option("casasync_sellerfallback_fax", '');
	add_option("casasync_sellerfallback_phone_direct", '');
	add_option("casasync_sellerfallback_phone_central", '');
	add_option("casasync_sellerfallback_phone_mobile", '');

	// technical feedback
	add_option("casasync_feedback_update", '1'); // update from casasync xml head
	add_option("casasync_feedback_creations", '1');
	add_option("casasync_feedback_edits", '0');
	add_option("casasync_feedback_inquiries", '0');

	add_option("casasync_feedback_given_name", '');
	add_option("casasync_feedback_family_name", '');
	add_option("casasync_feedback_email", '');
	add_option("casasync_feedback_telephone", '');
	add_option("casasync_feedback_gender", 'F');

	//seller
	add_option("casasync_seller_show", '1');
	add_option("casasync_seller_email_block", '0'); //or never

	//template engine
	add_option("casasync_single_template", file_get_contents(CASASYNC_PLUGIN_DIR . '/single-template-default.php'));
	add_option("casasync_archive_template", file_get_contents(CASASYNC_PLUGIN_DIR . '/archive-template-default.php'));

}

function casasync_remove() {
	/* Delete the database field */
	//delete_option('my_first_data');
}

if ( is_admin() ){ // admin actions
  add_action( 'admin_menu', 'casasync_menu' );
} else {
  // non-admin enqueues, actions, and filters
}
function casasync_menu() {
	add_menu_page('CasaSync options page', 'CasaSync', 'administrator', CASASYNC_PLUGIN_DIR,  'casasync_add_options_page', CASASYNC_PLUGIN_URL . 'assets/img/building.png');
}


function casasync_add_options_page() {
	include(CASASYNC_PLUGIN_DIR.'includes/options.php');
}

?>