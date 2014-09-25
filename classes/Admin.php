<?php
namespace CasaSync;

class Admin {

	public function __construct(){ 
		add_action( 'admin_menu', array($this,'casasync_menu') );
		add_action( 'admin_enqueue_scripts', array($this,'registerAdminScriptsAndStyles' ));
	}
	
	public function casasync_install() {
		// general
		add_option('casasync_live_import', '1');
		// appearance
		add_option('casasync_load_css', 'bootstrapv3');
		add_option('casasync_load_bootstrap_scripts', '1');
		add_option('casasync_load_fancybox', '1');
		add_option('casasync_load_chosen', '1');
		add_option('casasync_load_googlemaps', '1');
		//single view
		add_option('casasync_share_facebook', '1');
		add_option('casasync_share_googleplus', '0');
		add_option('casasync_share_twitter', '0');
		add_option('casasync_use_captcha', '0');
		add_option('casasync_single_show_number_of_rooms', '1');
		add_option('casasync_single_show_surface_usable', '1');
		add_option('casasync_single_show_surface_living', '1');
		add_option('casasync_single_show_surface_property', '1');
		add_option('casasync_single_use_zoomlevel', '12');
		//archive view
		add_option('casasync_archive_show_location', '1');
		add_option('casasync_archive_show_number_of_rooms', '1');
		add_option('casasync_archive_show_surface_usable', '1');
		add_option('casasync_archive_show_surface_living', '1');
		add_option('casasync_archive_show_surface_property', '1');
		add_option('casasync_archive_show_year_built', '1');
		add_option('casasync_archive_show_price', '1');
		add_option('casasync_archive_show_availability', '1');
		add_option('casasync_archive_show_thumbnail_size_w', '506');
		add_option('casasync_archive_show_thumbnail_size_h', '360');
		add_option('casasync_archive_show_thumbnail_size_crop', '1');
		//contactform
		add_option('casasync_request_per_mail', '1');
		add_option('casasync_request_per_remcat', '0');
		add_option('casasync_request_per_mail_fallback', '0');
		add_option('casasync_form_firstname_required', '1');
		add_option('casasync_form_lastname_required', '1');
		add_option('casasync_form_street_required', '1');
		add_option('casasync_form_postalcode_required', '1');
		add_option('casasync_form_locality_required', '1');
		add_option('casasync_form_phone_required', '1');
		add_option('casasync_form_email_required', '1');
		add_option('casasync_form_message_required', '1');
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
			CASASYNC_PLUGIN_URL . 'plugin_assets/img/building.png'
		);
	}

	public function casasync_add_options_page() {
		include(CASASYNC_PLUGIN_DIR.'options.php');
	}

	public function registerAdminScriptsAndStyles() {
		wp_register_style( 'casasync-admin-css', CASASYNC_PLUGIN_URL . 'plugin_assets/css/casasync-admin.css' );
        wp_enqueue_style( 'casasync-admin-css' );

        wp_enqueue_script(
            'casasync_admin_scipts',
            CASASYNC_PLUGIN_URL . 'plugin_assets/js/admin-scripts.js',
            array( 'jquery' ),
            false,
            true
        );
	}


}
