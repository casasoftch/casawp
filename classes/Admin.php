<?php
namespace CasaSync;

class Admin {

    public function __construct(){ 
    	register_activation_hook(CASASYNC_PLUGIN_DIR . '/classes/', array($this,'casasync_install'));
    	register_deactivation_hook( CASASYNC_PLUGIN_DIR . '/classes/', array($this,'casasync_remove' ));
    	add_action( 'admin_menu', array($this,'casasync_menu') );
    }
    public function casasync_install() {
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
