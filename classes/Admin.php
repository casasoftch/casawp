<?php
namespace CasaSync;

class Admin {

    public function __construct(){ 
    	register_activation_hook(CASASYNC_PLUGIN_DIR . '/classes/', array($this,'casasync_install'));
    	register_deactivation_hook( CASASYNC_PLUGIN_DIR . '/classes/', array($this,'casasync_remove' ));
    	add_action( 'admin_menu', array($this,'casasync_menu') );
    }
    public function casasync_install() {

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


		// NEW

		// general
		add_option('casasync_live_import', '1');
		get_option('casasync_sellerfallback_email_use', 'fallback');

		// appearance
		add_option('casasync_load_css', 'bootstrapv3');
		add_option('casasync_load_bootstrap_scripts', '1');
		add_option('casasync_load_fancybox', '1');
		add_option('casasync_load_chosen', '1');
		add_option('casasync_load_googlemaps', '1');

		//single view
		//add_option('casasync_seller_show', '1');
		add_option('casasync_share_facebook', '1');
		add_option('casasync_share_googleplus', '1');
		add_option('casasync_share_twitter', '1');
		add_option('casasync_use_captcha', '1');
		add_option('casasync_single_show_number_of_rooms', '1');
		add_option('casasync_single_show_surface_usable', '1');
		add_option('casasync_single_show_surface_living', '1');
		add_option('casasync_single_show_surface_property', '1');

		//archive view
		add_option('casasync_archive_show_location', '1');
		add_option('casasync_archive_show_number_of_rooms', '1');
		add_option('casasync_archive_show_surface_usable', '1');
		add_option('casasync_archive_show_surface_living', '1');
		add_option('casasync_archive_show_surface_property', '1');
		add_option('casasync_archive_show_price', '1');
	}

	public function casasync_remove() {
		/* Delete the database field */
		//delete_option('my_first_data');
	}

	public function casasync_menu() {
		add_menu_page(
			'CasaSync options page',
			'CasaSync',
			'administrator',
			'casasync',
			array($this,'casasync_add_options_page'),
			CASASYNC_PLUGIN_URL . 'assets/img/building.png'
		);
	}

	public function casasync_add_options_page() {
		include(CASASYNC_PLUGIN_DIR.'options.php');
	}
}
