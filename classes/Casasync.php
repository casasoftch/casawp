<?php
namespace CasaSync;

class CasaSync {  
    public $textids = false;
    public $fields = false;
    public $meta_box = false;
    public $admin = false;
    public $conversion = null;
    public $show_sticky = true;

    public function __construct(){  
        $this->conversion = new Conversion;
        
        if ( !function_exists( 'add_action' ) ) {
            echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
            exit;
        }
        add_filter('upload_dir',  array($this, 'setUploadDir'));
        $upload = wp_upload_dir();
        define('CASASYNC_CUR_UPLOAD_PATH', $upload['path'] );
        define('CASASYNC_CUR_UPLOAD_URL', $upload['url'] );
        define('CASASYNC_CUR_UPLOAD_BASEDIR', $upload['basedir'] );
        define('CASASYNC_CUR_UPLOAD_BASEURL', $upload['baseurl'] );
        remove_filter('upload_dir', array($this, 'setUploadDir'));
        add_shortcode('casasync_contact', array($this,'contact_shortcode'));
        add_action('init', array($this, 'setPostTypes'));
        add_action('admin_menu', array($this, 'setMetaBoxes'));
        add_action('wp_enqueue_scripts', array($this, 'registerScriptsAndStyles'));
        add_action('wp_enqueue_scripts', array($this, 'setOptionJsVars'));
        add_filter("attachment_fields_to_edit", array($this, "casasync_image_attachment_fields_to_edit"), null, 2);
        add_filter("attachment_fields_to_save", array($this, "casasync_image_attachment_fields_to_save"), null, 2);
        if (!is_admin()) {
            add_action('pre_get_posts', array($this, 'customize_casasync_category'));  
        }
        add_filter( 'template_include', array($this, 'include_template_function'), 1 );
        register_activation_hook(CASASYNC_PLUGIN_DIR, array($this, 'casasync_activation'));
        register_deactivation_hook(CASASYNC_PLUGIN_DIR, array($this, 'casasync_deactivation'));
    
        if ( function_exists( 'add_theme_support' ) ) {
            add_theme_support( 'post-thumbnails' );
            set_post_thumbnail_size( 150, 150 ); // default Post Thumbnail dimensions
        }
        if ( function_exists( 'add_image_size' ) ) {
            //add_image_size( 'category-thumb', 300, 9999 );
            $standard_thumbnail_width = '506';
            $standard_thumbnail_height = '360';
            $thumb_size_w    = get_option('casasync_archive_show_thumbnail_size_w', 506) != '' ? get_option('casasync_archive_show_thumbnail_size_w') : $standard_thumbnail_width;
            $thumb_size_h    = get_option('casasync_archive_show_thumbnail_size_h', 360) != '' ? get_option('casasync_archive_show_thumbnail_size_h') : $standard_thumbnail_height;
            $thumb_size_crop = get_option('casasync_archive_show_thumbnail_size_crop', 506) == false ? 'true' : 'false';
            add_image_size(
                'casasync-thumb',
                $thumb_size_w,
                $thumb_size_h,
                $thumb_size_crop
            );
        }
        $this->setMetaBoxes();

        //add_action( 'load_textdomain', array($this, 'setTranslation'));
        add_filter( 'page_template', array($this, 'casasync_page_template' ));

        add_action('plugins_loaded', array($this, 'setTranslation'));
    }




    
    function casasync_page_template( $page_template ){
        global $post;
        if ( is_page( 'casasync-archive' ) ) {
            $page_template = dirname( __FILE__ ) . '/casasync-archive.php';
        }
        return $page_template;
    }


    public function setOptionJsVars(){
        $script_params = array(
           'google_maps'           => get_option('casasync_load_googlemaps', 0),
           'google_maps_zoomlevel' => get_option('casasync_single_use_zoomlevel', 12),
           'fancybox'              => get_option('casasync_load_fancybox', 0),
           'chosen'                => get_option('casasync_load_chosen', 0),
           'load_css'              => get_option('casasync_load_css', 'bootstrapv3'),
           'load_bootstrap_js'     => get_option('casasync_load_bootstrap_scripts')
        );
        wp_localize_script( 'casasync_script', 'casasyncOptionParams', $script_params );
    }


    function casasync_activation() {
        register_uninstall_hook(__FILE__, array($this, 'casasync_uninstall'));
    }

    function casasync_deactivation() {
        // actions to perform once on plugin deactivation go here
    }

    function casasync_uninstall(){
        //actions to perform once on plugin uninstall go here
    }

    public function include_template_function( $template_path ) {
        if ( get_post_type() == 'casasync_property' && is_single()) {
            if ($_GET && isset($_GET['ajax'])) {
                $template_path = CASASYNC_PLUGIN_DIR . '/ajax/prevnext.php';
            } elseif ( $theme_file = locate_template( array( 'casasync-single.php' ) ) ) {
                $template_path = $theme_file;
            } else {
                $template_path = CASASYNC_PLUGIN_DIR . '/casasync-single.php';
            }
        }
        if (is_tax('casasync_salestype') || is_tax('casasync_availability') || is_tax('casasync_category') || is_tax('casasync_location') || is_post_type_archive( 'casasync_property' )) {
            if ( $theme_file = locate_template(array('casasync-archive.php'))) {
                $template_path = $theme_file;
            } else {
                $template_path = CASASYNC_PLUGIN_DIR . '/casasync-archive.php';
            }
        }
        return $template_path;
    }


    public function customize_casasync_category($query){

        if ($query->is_main_query()) {
            if (is_tax('casasync_salestype') || is_tax('casasync_availability') || is_tax('casasync_category') || is_tax('casasync_location') || is_post_type_archive('casasync_property')) {

                $posts_per_page = get_option('posts_per_page', 10);
                $query->set('posts_per_page', $posts_per_page);
                $query->set('order', get_option('casasync_archive_order', 'DESC'));

                $query->set('ignore_sticky_posts',0);
                if (get_option( 'casasync_hide_sticky_properties_in_main')) {
                    $query->set('post__not_in', get_option( 'sticky_posts' ));
                }
                
                switch (get_option('casasync_archive_orderby', 'date')) {
                    case 'title':
                        $query->set('orderby', 'title');
                        break;
                    case 'location':
                        $query->set('meta_key', 'casasync_property_address_locality');
                        $query->set('orderby', 'meta_value');
                        break;
                    case 'price':
                        $query->set('meta_key', 'priceForOrder');
                        $query->set('orderby', 'meta_value');
                        break;
                    case 'date':
                    default:
                        $query->set('orderby', 'date');
                        break;
                }

                //$query->set('orderby', 'date');
                //$query->set('order', 'ASC');

                $taxquery_new = array();

                if ((isset($_GET['casasync_category_s']) && is_array($_GET['casasync_category_s']) )) {
                    $categories = $_GET['casasync_category_s'];
                } elseif (isset($_GET['casasync_category_s'])) {
                    $categories = array($_GET['casasync_category_s']);
                } else {
                    $categories = array();
                }
                if ($categories) {
                    $taxquery_new[] = array(
                        'taxonomy'         => 'casasync_category',
                        'terms'            => $categories,
                        'include_children' => 1,
                        'field'            => 'slug',
                        'operator'         => 'IN'
                    );
                }
                if ((isset($_GET['casasync_location_s']) && is_array($_GET['casasync_location_s']) )) {
                    $locations = $_GET['casasync_location_s'];
                } elseif (isset($_GET['casasync_location_s'])) {
                    $locations = array($_GET['casasync_location_s']);
                } else {
                    $locations = array();
                }
                if ($locations) {
                    $taxquery_new[] = array(
                        'taxonomy' => 'casasync_location',
                        'terms' => $locations,
                        'include_children' => 1,
                        'field' => 'slug',
                        'operator'=> 'IN'
                    );
                }

                $salestypes = array();
                if ((isset($_GET['casasync_salestype_s']) && is_array($_GET['casasync_salestype_s']) )) {
                    $salestypes = $_GET['casasync_salestype_s'];
                } elseif (isset($_GET['casasync_salestype_s'])) {
                    $salestypes = array($_GET['casasync_salestype_s']);
                } elseif(is_tax('casasync_salestype')) {
                    //$salestypes = array('rent','buy', 'reference');
                } else {
                    //$salestypes = array('rent','buy');
                }
                if ($salestypes) {
                    $taxquery_new[] = array(
                        'taxonomy' => 'casasync_salestype',
                        'terms' => $salestypes,
                        'include_children' => 1,
                        'field' => 'slug',
                        'operator'=> 'IN'
                     );
                }


                $availabilities = array();
                if ((isset($_GET['casasync_availability_s']) && is_array($_GET['casasync_availability_s']) )) {
                    $availabilities = $_GET['casasync_availability_s'];
                } elseif (isset($_GET['casasync_availability_s'])) {
                    $availabilities = array($_GET['casasync_availability_s']);
                } elseif(is_tax('casasync_availability')) {
                } else {
                    //reference and taken are hidden by default
                    $availabilities = array('active','reserved');
                }
                if ($availabilities) {
                    $taxquery_new[] = array(
                        'taxonomy' => 'casasync_availability',
                        'terms' => $availabilities,
                        'include_children' => 1,
                        'field' => 'slug',
                        'operator'=> 'IN'
                     );
                }

                if ($taxquery_new) {
                    $query->set('tax_query', $taxquery_new);
                }

                /*echo "<textarea cols='100' rows='30' style='position:relative; z-index:10000; width:inherit; height:200px;'>";
                print_r($query);
                echo "</textarea>";
*/
            }
        }
    }

    public function casasync_image_attachment_fields_to_edit($form_fields, $post) {
        $form_fields["origin"] = array(
            "label" => __("Custom Text Field"),
            "input" => "text", // this is default if "input" is omitted
            "value" => get_post_meta($post->ID, "_custom1", true)
        );
        $form_fields["origin"]["label"] = __("Original filename");
        $form_fields["origin"]["input"] = "text";
        $form_fields["origin"]["value"] = get_post_meta($post->ID, "_origin", true);
        return $form_fields;
    }

    public function casasync_image_attachment_fields_to_save($post, $attachment) {
        if( isset($attachment['origin']) ){
            update_post_meta($post['ID'], '_origin', $attachment['origin']);
        }
        return $post;
    }

    function registerScriptsAndStyles(){
        switch (get_option('casasync_load_css', 'bootstrapv3')) {
            case 'bootstrapv2':
                wp_register_style( 'casasync-css', CASASYNC_PLUGIN_URL . 'assets/css/casasync_template_bs2.css' );
                wp_enqueue_style( 'casasync-css' );
                break;
            case 'bootstrapv3':
                wp_register_style( 'casasync-css', CASASYNC_PLUGIN_URL . 'assets/css/casasync_template_bs3.css' );
                wp_enqueue_style( 'casasync-css' );
                break;
            case 'none':
            default:
                break;
        }

        if (get_option( 'casasync_load_bootstrap_scripts', 'none' )) {
            switch (get_option('casasync_load_css', 'bootstrapv3')) {
                case 'bootstrapv2':
                    wp_enqueue_script(
                        'casasync_bootstrap2',
                        CASASYNC_PLUGIN_URL . 'assets/js/bootstrap.min.js',
                        array( 'jquery' ),
                        false,
                        true
                    );
                    break;
                case 'bootstrapv3':
                    wp_enqueue_script(
                        'casasync_bootstrap3_transition',
                        CASASYNC_PLUGIN_URL . 'assets/js/bootstrap3/transition.js',
                        array( 'jquery' ),
                        false,
                        true
                    );
                    wp_enqueue_script(
                        'casasync_bootstrap3_tab',
                        CASASYNC_PLUGIN_URL . 'assets/js/bootstrap3/tab.js',
                        array( 'jquery' ),
                        false,
                        true
                    );
                    wp_enqueue_script(
                        'casasync_bootstrap3_carousel',
                        CASASYNC_PLUGIN_URL . 'assets/js/bootstrap3/carousel.js',
                        array( 'jquery' ),
                        false,
                        true
                    );
                    wp_enqueue_script(
                        'casasync_bootstrap3_tooltip',
                        CASASYNC_PLUGIN_URL . 'assets/js/bootstrap3/tooltip.js',
                        array( 'jquery' ),
                        false,
                        true
                    );
                    wp_enqueue_script(
                        'casasync_bootstrap3_popover',
                        CASASYNC_PLUGIN_URL . 'assets/js/bootstrap3/popover.js',
                        array( 'jquery' ),
                        false,
                        true
                    );
                    break;
                case 'none':
                default:
                    # code...
                    break;
            }
            // Add Bootstrap v2 
        }

        wp_enqueue_script(
            'jstorage',
            CASASYNC_PLUGIN_URL . 'assets/js/jstorage.js',
            array( 'jquery' )
        );
        if(is_singular('casasync_property')) {
            wp_enqueue_script(
                'casasync_jquery_eqheight',
                CASASYNC_PLUGIN_URL . 'assets/js/jquery.equal-height-columns.js',
                array( 'jquery' ),
                false,
                true
            );
            if (get_option( 'casasync_load_fancybox', 1 )) {
                wp_enqueue_script(
                    'fancybox',
                    CASASYNC_PLUGIN_URL . 'assets/js/jquery.fancybox.pack.js',
                    array( 'jquery' ),
                    false,
                    true
                );
                wp_register_style( 'fancybox', CASASYNC_PLUGIN_URL . 'assets/css/jquery.fancybox.css' );
                wp_enqueue_style( 'fancybox' );
            }
        }

        if (get_option( 'casasync_load_chosen', 1 )) {
            wp_enqueue_script(
                'chosen',
                CASASYNC_PLUGIN_URL . 'assets/js/chosen.jquery.min.js',
                array( 'jquery' ),
                false,
                true
            );
            wp_register_style( 'chosen-css', CASASYNC_PLUGIN_URL . 'assets/css/chosen.css' );
            wp_enqueue_style( 'chosen-css' );
        }
        if (get_option( 'casasync_load_googlemaps', 1 ) && is_singular('casasync_property')) {
            wp_enqueue_script(
                'google_maps_v3',
                'https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false',
                array(),
                false,
                true
            );
        }
        wp_enqueue_script(
            'casasync_script',
            CASASYNC_PLUGIN_URL . 'assets/js/script.js',
            array( 'jquery' ),
            false,
            true
        );

    }

    public function setTranslation(){
        $locale = get_locale();

        switch (substr($locale, 0, 2)) {
            case 'de': $locale = 'de_DE'; break;
            case 'en': $locale = 'en_US'; break;
            case 'it': $locale = 'it_CH'; break;
            case 'fr': $locale = 'fr_CH'; break;
            default: $locale = 'de_DE'; break;
        }


        //$locale_file = get_template_directory_uri() . "/includes/languages/$locale.php";
       /* $locale_file = CASASYNC_PLUGIN_DIR . "languages/$locale.php";
        if ( is_readable( $locale_file ) ) {
            require_once( $locale_file );
        }*/
        load_plugin_textdomain('casasync', false, '/casasync/languages/' );
    }

    function setUploadDir($upload) {
        $upload['subdir'] = '/casasync' . $upload['subdir'];
        $upload['path']   = $upload['basedir'] . $upload['subdir'];
        $upload['url']    = $upload['baseurl'] . $upload['subdir'];
        return $upload;
    }
    
    public function setMetaBoxes(){
        //options
        $textids = array(
            'casasync_id' => 'CasaSync ID',
            'casasync_property_address_country' => 'country',
            'casasync_property_address_locality' => 'locality',
            'casasync_property_address_region' => 'region',
            'casasync_property_address_postalcode' => 'postalcode',
            'casasync_property_address_postofficeboxnumber' => 'postofficeboxnumber',
            'casasync_property_address_streetaddress' => 'streetaddress',
            'casasync_property_geo_latitude' => 'lat',
            'casasync_property_geo_longitude' => 'long',
            'offer_type' => 'offer_type',
            'price_currency' => 'price_currency',
            'price_timesegment' => 'price_timesegment',
            'price_propertysegment' => 'price_propertysegment',
            'price' => 'price',
            'grossPrice_timesegment' => 'grossPrice_timesegment',
            'grossPrice_propertysegment' => 'grossPrice_propertysegment',
            'grossPrice' => 'grossPrice',
            'netPrice_timesegment' => 'netPrice_timesegment',
            'netPrice_propertysegment' => 'netPrice_propertysegment',
            'netPrice' => 'netPrice',
        );
        $fields = array();
        foreach ($textids as $id => $label) {
            $fields[] = array(
                'name'    => $label,
                'desc'    => '',
                'id'      => $id,
                'type'    => 'text',
                'default' => ''
            );
        }
        $this->fields = $fields;

        $meta_box['casasync_property'] = array(
            'id'       => 'property-meta-details',
            'title'    => 'Property Details',
            'context'  => 'normal',
            'priority' => 'high',
            'fields'   => $fields
        );
        $this->meta_box = $meta_box;

        foreach($this->meta_box as $post_type => $value) {
            //add_meta_box($value['id'], $value['title'], array($this, 'plib_format_box'), $post_type, $value['context'], $value['priority']);
        }
    }


    public function setPostTypes(){
        $labels = array(
            'name'               => __('Properties', 'casasync'),
            'singular_name'      => __('Property', 'casasync'),
            'add_new'            => __('Add New', 'casasync'),
            'add_new_item'       => __('Add New Property', 'casasync'),
            'edit_item'          => __('Edit Property', 'casasync'),
            'new_item'           => __('New Property', 'casasync'),
            'all_items'          => __('All Properties', 'casasync'),
            'view_item'          => __('View Property', 'casasync'),
            'search_items'       => __('Search Properties', 'casasync'),
            'not_found'          => __('No properties found', 'casasync'),
            'not_found_in_trash' => __('No properties found in Trash', 'casasync'),
            'menu_name'          => __('Properties', 'casasync')
        );
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'immobilien' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
            'menu_icon'          => CASASYNC_PLUGIN_URL . 'assets/img/building.png',
            'show_in_nav_menus'  => true
        );
        register_post_type( 'casasync_property', $args );

        $labels = array(
            'name'              => __( 'Property categories', 'casasync'),
            'singular_name'     => __( 'Category', 'casasync'),
            'search_items'      => __( 'Search Categories', 'casasync'),
            'all_items'         => __( 'All Categories', 'casasync'),
            'parent_item'       => __( 'Parent Category', 'casasync'),
            'parent_item_colon' => __( 'Parent Category:', 'casasync'),
            'edit_item'         => __( 'Edit Category', 'casasync'),
            'update_item'       => __( 'Update Category', 'casasync'),
            'add_new_item'      => __( 'Add New Category', 'casasync'),
            'new_item_name'     => __( 'New Category Name', 'casasync'),
            'menu_name'         => __( 'Category', 'casasync')
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-kategorie' )
        );
        register_taxonomy( 'casasync_category', array( 'casasync_property' ), $args );

        $labels = array(
            'name'              => __( 'Property locations', 'casasync' ),
            'singular_name'     => __( 'Location', 'casasync' ),
            'search_items'      => __( 'Search Locations', 'casasync'),
            'all_items'         => __( 'All Locations', 'casasync'),
            'parent_item'       => __( 'Parent Location', 'casasync'),
            'parent_item_colon' => __( 'Parent Location:', 'casasync'),
            'edit_item'         => __( 'Edit Location', 'casasync'),
            'update_item'       => __( 'Update Location', 'casasync'),
            'add_new_item'      => __( 'Add New Location', 'casasync'),
            'new_item_name'     => __( 'New Location Name', 'casasync'),
            'menu_name'         => __( 'Location', 'casasync')
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-ortschaft' )
        );
        register_taxonomy( 'casasync_location', array( 'casasync_property' ), $args );

        $labels = array(
            'name'                       => __( 'Property salestypes', 'casasync' ),
            'singular_name'              => __( 'Salestype', 'casasync' ),
            'search_items'               => __( 'Search Salestypes', 'casasync' ),
            'popular_items'              => __( 'Popular Salestypes', 'casasync' ),
            'all_items'                  => __( 'All Salestypes', 'casasync' ),
            'edit_item'                  => __( 'Edit Salestype', 'casasync' ),
            'update_item'                => __( 'Update Salestype', 'casasync' ),
            'add_new_item'               => __( 'Add New Salestype', 'casasync' ),
            'new_item_name'              => __( 'New Salestype Name', 'casasync' ),
            'separate_items_with_commas' => __( 'Separate salestypes with commas', 'casasync' ),
            'add_or_remove_items'        => __( 'Add or remove salestypes', 'casasync' ),
            'choose_from_most_used'      => __( 'Choose from the most used salestypes', 'casasync' ),
            'not_found'                  => __( 'No Salestypes found.', 'casasync' ),
            'menu_name'                  => __( 'Salestype', 'casasync' )
        );
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-vermarktungsart' )
        );
        register_taxonomy( 'casasync_salestype', array( 'casasync_property' ), $args );

        $labels = array(
            'name'                       => __( 'Property availability', 'casasync' ),
            'singular_name'              => __( 'Availability', 'casasync' ),
            'search_items'               => __( 'Search availabilities', 'casasync' ),
            'popular_items'              => __( 'Popular Availabilities', 'casasync' ),
            'all_items'                  => __( 'All Availabilities', 'casasync' ),
            'edit_item'                  => __( 'Edit Availability', 'casasync' ),
            'update_item'                => __( 'Update Availability', 'casasync' ),
            'add_new_item'               => __( 'Add New Availability', 'casasync' ),
            'new_item_name'              => __( 'New Availability Name', 'casasync' ),
            'separate_items_with_commas' => __( 'Separate availabilities with commas', 'casasync' ),
            'add_or_remove_items'        => __( 'Add or remove availabilities', 'casasync' ),
            'choose_from_most_used'      => __( 'Choose from the most used availabilities', 'casasync' ),
            'not_found'                  => __( 'No Availabilities found.', 'casasync' ),
            'menu_name'                  => __( 'Availability', 'casasync' )
        );
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-verfuegbarkeit' )
        );
        register_taxonomy( 'casasync_availability', array( 'casasync_property' ), $args );

        $labels = array(
          'name'              => __( 'Property Attachment Types', 'casasync' ),
            'singular_name'     => __( 'Attachment Type', 'casasync' ),
            'search_items'      => __( 'Search Attachment Types', 'casasync' ),
            'all_items'         => __( 'All Attachment Types', 'casasync' ),
            'parent_item'       => __( 'Parent Attachment Type', 'casasync' ),
            'parent_item_colon' => __( 'Parent Attachment Type:', 'casasync' ),
            'edit_item'         => __( 'Edit Attachment Type', 'casasync' ),
            'update_item'       => __( 'Update Attachment Type', 'casasync' ),
            'add_new_item'      => __( 'Add New Attachment Type', 'casasync' ),
            'new_item_name'     => __( 'New Attachment Type Name', 'casasync' ),
            'menu_name'         => __( 'Attachment Type', 'casasync' )
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-anhangstyp' )
        );
        register_taxonomy( 'casasync_attachment_type', array(), $args );
        register_taxonomy_for_object_type('casasync_attachment_type', 'attachment');
        add_post_type_support('attachment', 'casasync_attachment_type');
        $id1 = wp_insert_term('Image', 'casasync_attachment_type', array('slug' => 'image'));
        $id2 = wp_insert_term('Plan', 'casasync_attachment_type', array('slug' => 'plan'));
        $id3 = wp_insert_term('Document', 'casasync_attachment_type', array('slug' => 'document'));
    }



    public function contact_shortcode($atts){
        extract( shortcode_atts( array(
            'recipients' => 'Casasoft:dev@casasoft.ch',
            'remcat'     => false,
            'ccs'        => '',
            'post_id'    => false
        ), $atts ) );

        $table = '';
        $validation = false;
        $errors = false;
        $remcat_sended = false;
        $email_sended = false;

        $rec_ar1 = explode(';', $recipients);
        $recipientses = array();
        foreach ($rec_ar1 as $key => $value) {
            $recipientses[] = explode(':', trim(str_replace('<br />', '', $value)));
        }
        $cc_ar1 = explode(';', $ccs);
        $ccs_arr = array();
        foreach ($cc_ar1 as $key => $value) {
            $ccs_arr[] = explode(':', trim(str_replace('<br />', '', $value)));
        }
        //labels and whitelist
        $fieldlabels = array(
            'firstname'   => __('First name', 'casasync'), //'Vorname',
            'lastname'    => __('Last name', 'casasync'), //'Nachname',
            'emailreal'   => __('Email', 'casasync'), //'E-Mail',
            'salutation'  => __('Salutation', 'casasync'), //'Anrede',
            'title'       => __('Title', 'casasync'), //'Titel',
            'phone'       => __('Phone', 'casasync'), //'Telefon',
            'email'       => 'E-Mail SPAM!',
            'company'     => __('Company', 'casasync'), //'Firma',
            'street'      => __('Street', 'casasync'), //'Strasse',
            'postal_code' => __('ZIP', 'casasync'), //'PLZ',
            'locality'    => __('Locality', 'casasync'), //'Stadt',
            'country'     => __('Country', 'casasync'), // Land
            'state'       => __('Kanton', 'casasync'), //'Kanton',
            'subject'     => __('Subject', 'casasync'), //'Betreff',
            'message'     => __('Message', 'casasync'), //'Nachricht',
            'recipient'   => __('Recipient', 'casasync'), //'Rezipient',
        );

        if (!empty($_POST)) {
            $validation = true;

            $required = array();
            $required[] = get_option('casasync_form_firstname_required',  false) ? 'firstname'   : null;
            $required[] = get_option('casasync_form_lastname_required',   false) ? 'lastname'    : null;
            $required[] = get_option('casasync_form_street_required',     false) ? 'street'      : null;
            $required[] = get_option('casasync_form_postalcode_required', false) ? 'postal_code' : null;
            $required[] = get_option('casasync_form_locality_required',   false) ? 'locality'    : null;
            $required[] = get_option('casasync_form_phone_required',      false) ? 'phone'       : null;
            $required[] = get_option('casasync_form_email_required',      false) ? 'emailreal'   : null;
            $required[] = get_option('casasync_form_message_required',    false) ? 'message'     : null;

            $companyname = get_bloginfo( 'name' );
            $companyAddress = '{STREET}
                <br />
                CH-{ZIP} {CITY}
                <br />
                Tel. {PHONE}
                <br />
            Fax {FAX}';

            //not alowed fields!!!
            foreach($_POST as $key => $value){
                if (!array_key_exists($key, $fieldlabels)) {
                    $errors[] = '<b>Form ERROR!</b>: please contact the administrator. Ilegal Field has been posted[' . $key . ']'; //ausfüllen
                    $validation = false;
                }
            }

            //required
            foreach ($required as $name) {
                if (array_key_exists($name, $_POST)) {
                    if (!$_POST[$name]) {
                        $errors[] = '<b>' . $fieldlabels[$name] . '</b>: ' . __('Required', 'casasync'); //ausfüllen
                        $validation = false;
                    }
                }
            }
            //spam
            if ($_POST['email'] || strpos($_POST['message'], 'http://')) {
                $validation = false;
            }
            if ($validation) {
                $casa_id = get_post_meta( $post_id, 'casasync_id', $single = true );
                $casa_id_arr = preg_split('/(?<=\d)(?=[a-z])|(?<=[a-z])(?=\d)/i', $casa_id);
                $property_id = $casa_id_arr[0];
                $property_lang = $casa_id_arr[1];

                //REM
                if ($remcat != '') {
                    $categories = wp_get_post_terms( get_the_ID(), 'casasync_category'); 
                    if ($categories) {
                        $type = $this->conversion->casasync_convert_categoryKeyToLabel($categories[0]->name); 
                    } else {
                        $type = '';
                    }
                    $remCat = array(
                        0  => $_SERVER['SERVER_NAME'],
                        1  => get_post_meta( $post_id, 'seller_org_legalname', true ),
                        2  => get_post_meta( $post_id, 'seller_org_address_streetaddress', true ),
                        3  => get_post_meta( $post_id, 'seller_org_address_postalcode', true ),
                        4  => get_post_meta( $post_id, 'seller_org_address_locality', true ),
                        5  => get_post_meta( $post_id, 'seller_person_givenname', true ) . ' ' . get_post_meta( $post_id, 'seller_person_familyname', true ),
                        6  => get_option('casasync_remCat_email', ''),
                        7  => $property_id,
                        8  => get_permalink($post_id),
                        9  => get_post_meta($post_id, 'casasync_property_address_streetaddress', true),
                        10 => get_post_meta($post_id, 'casasync_property_address_locality', true),
                        11 => $type,
                        12 => $property_lang,
                        13 => '', //anrede
                        14 => (isset($_POST['firstname']) ? $_POST['firstname'] : ''),
                        15 => (isset($_POST['lastname']) ? $_POST['lastname'] : ''),
                        16 => (isset($_POST['company']) ? $_POST['company'] : ''),
                        17 => (isset($_POST['street']) ? $_POST['street'] : ''),
                        18 => (isset($_POST['country']) ? $_POST['country'] . '-' : '') . (isset($_POST['postal_code']) ? $_POST['postal_code'] : ''),
                        19 => (isset($_POST['locality']) ? $_POST['locality'] : ''),
                        20 => (isset($_POST['phone']) ? $_POST['phone'] : ''),
                        21 => (isset($_POST['mobile']) ? $_POST['mobile'] : ''),
                        22 => (isset($_POST['fax']) ? $_POST['fax'] : ''),
                        23 => filter_input(INPUT_POST, 'emailreal', FILTER_VALIDATE_EMAIL),
                        24 => (isset($_POST['message']) ? $_POST['message'] : ''),
                        25 => '',
                        26 => ''
                    );

                    $remCat_str = '';
                    foreach ($remCat as $key => $value) {
                        $remCat_str .= '#' . $value;
                    }
    
                    $header  = "From: \"\" <remcat@casasync.ch>\r\n";
                    $header .= "MIME-Version: 1.0\r\n";
                    $header .= "Content-Type: text/plain; charset=ISO-8859-1\r\n";
                    

                    wp_mail(get_option('casasync_remCat_email', false), 'Neue Anfrage', utf8_decode($remCat_str), $header);
                    $remcat_sended = true;
                }
    
                $template = file_get_contents(CASASYNC_PLUGIN_DIR . 'email_templates/message_de.html');
    
                $the_thumbnail = '';
                $thumbnail = get_the_post_thumbnail($post_id, array(250, 250));
    
                if ( $thumbnail ) { $the_thumbnail = $thumbnail; }
    
                $thumb  = '<table border="0">';
                $thumb .= '<tr>';
                $thumb .= '<td><a href="' . get_permalink($post_id) . '">' . $the_thumbnail . '</a></td>';
                $thumb .= '</tr>';
                $thumb .= '</table>';
    
                $message = '<table width="100%">';
                foreach($_POST as $key => $value){
                    if (array_key_exists($key, $fieldlabels)) {
                        if ($key != 'email') {
                            if($key != 'country') {
                                $message.= '<tr><td align="left" style="padding-right:10px" valign="top"><strong>'.$fieldlabels[$key].'</strong></td><td align="left">' . nl2br($value) . '</td></tr>';
                            } else {
                                $countries = $this->conversion->country_arrays();
                                $country_name = (isset($countries[$value])) ? ($countries[$value]) : ($value);
                                $message.= '<tr><td align="left" style="padding-right:10px" valign="top"><strong>'.$fieldlabels[$key].'</strong></td><td align="left">' . nl2br($country_name) . '</td></tr>';
                            }
                        }
                    }
                }
                if ($post_id) {
                    $message .= '<tr></td colspan="2">&nbsp;</td></tr>';
                    $message .= '<tr>';
                    $message .= '<td colspan="2" class="property"><a href="' . get_permalink($post_id) . '" style="text-decoration: none; color: #969696; font-weight: bold; font-family: Helvetica, Arial, sans-serif;">' . __('Show property ...') .'</a></td>';//Objekt anzeigen ...
                    $message .= '</tr>';
                }
                $message.='</table>';

                $template = str_replace('{:logo_src:}', '#', $template);
                $template = str_replace('{:logo_url:}', '#', $template);
                $template = str_replace('{:site_title:}', $_SERVER['SERVER_NAME'], $template);
                $template = str_replace('{:domain:}', $_SERVER['SERVER_NAME'], $template);
    
                $template = str_replace('{:src_social_1:}', '#', $template);
                $template = str_replace('{:src_social_2:}', '#', $template);
                $template = str_replace('{:src_social_3:}', '#', $template);
                $template = str_replace('{:sender_title:}', get_the_title( $post_id ), $template);

                //strings
                $template = str_replace('{:html_title:}', __('Inquiry for a property online', 'casasync'), $template); //'Anfrage für ein Objekt online'
                $template = str_replace('{:title:}', __('New inquiry', 'casasync'), $template); //'Neue Anfrage'
                $p_msg = sprintf(__('A new inquiry from %s has been sent', 'casasync'), '<a style="color:#99CCFF" href="http://' . $_SERVER['SERVER_NAME'] . '">http://' . $_SERVER['SERVER_NAME'] . '</a>');
                $template = str_replace('{:primary_message:}', $p_msg, $template);


                if ($message) {
                    $template = str_replace('{:message:}', $message, $template);
                }
    
                if ($thumb) {
                    $template = str_replace('{:thumb:}', $thumb, $template);
                }
    
                $template = str_replace('{:support_email:}', 'support@casasoft.ch', $template);
                $template = str_replace('{:href_mapify:}', 'http://'. $_SERVER['SERVER_NAME'], $template);
                $template = str_replace('{:href_casasoft:}', 'http://casasoft.ch', $template);
    
                $template = str_replace('{:href_social_1:}', '#', $template);
                $template = str_replace('{:href_social_2:}', '#', $template);
                $template = str_replace('{:href_social_3:}', '#', $template);
    
                $template = str_replace('{:href_message_archive:}','http://'. $_SERVER['SERVER_NAME'] . '', $template);
                $template = str_replace('{:href_message_edit:}', '#', $template);
    
                $sender_email    = filter_input(INPUT_POST, 'emailreal', FILTER_VALIDATE_EMAIL);
                $sender_fistname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
                $sender_lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
    
                $header  = "From: \"$sender_fistname $sender_lastname\" <$sender_email>\r\n";
                $header .= "MIME-Version: 1.0\r\n";
                $header .= "Content-Type: text/html; charset=UTF-8\r\n";
    
                foreach ($recipientses as $recipient2) {
                    if (isset($recipient2[0])) {
                        if (wp_mail($recipient2[0], 'Neue Anfrage', $template, $header)) {
                            $email_sended = true;
                        }
                    }
                }
                if($remcat_sended === true or $email_sended === true) {
                    echo '<p class="alert alert-success">' . __('Thank you!', 'casasync') . '</p>'; //Vielen Dank!
                } else {
                    echo '<p class="alert alert-danger">' . __('Error!', 'casasync') . '</p>'; //Fehler!
                }
            }

        } else {
            $validation = false;
        }

        $form = '';
        if (!$validation) {
            ob_start();
            if ($errors) {
                echo '<div class="alert alert-danger">';
                echo "<strong>" . __('Please consider the following errors and try sending it again', 'casasync')  . "</strong>";
                echo "<ul>";
                echo "<li>".implode('</li><li>', $errors) . '</li>';
                echo '</ul>';
                echo "</div>";
            }
            echo $table;
        ?>

        <?php
            // Bootstrap 2 Form Layout
            if (get_option('casasync_load_css') == 'bootstrapv2'):
        ?>
            <form class="form casasync-property-contact-form" id="casasyncPropertyContactForm" method="POST" action="">
                <input id="theApsoluteRealEmailField" type="text" name="email" value="" placeholder="NlChT8 AuSf$lLeN" />
                <div class="row-fluid">
                    <div class="span5">
                        <label for="firstname"><?php echo __('First name', 'casasync') ?></label>
                        <input name="firstname" class="span12" value="<?php echo (isset($_POST['firstname']) ? $_POST['firstname'] : '') ?>" type="text" id="firstname" />
                    </div>
                    <div class="span7">
                        <label for="lastname"><?php echo __('Last name', 'casasync') ?></label>
                        <input name="lastname" class="span12" value="<?php echo (isset($_POST['lastname']) ? $_POST['lastname'] : '') ?>" type="text" id="lastname" />
                    </div>
                </div>
                <div class="row-fluid">
                </div>
                <div class="row-fluid">
                    <label for="street"><?php echo __('Street', 'casasync') ?></label>
                    <input name="street" class="span12" value="<?php echo (isset($_POST['street']) ? $_POST['street'] : '') ?>"  type="text" id="street" />
                </div>
                <div class="row-fluid">
                    <div class="span4">
                        <label for="postal_code"><?php echo __('ZIP', 'casasync') ?></label>
                        <input name="postal_code" class="span12" value="<?php echo (isset($_POST['postal_code']) ? $_POST['postal_code'] : '') ?>"  value="<?php echo (isset($_POST['postal_code']) ? $_POST['postal_code'] : '') ?>" type="text" id="postal_code" />
                    </div>
                    <div class="span8">
                        <label for="locality"><?php echo __('Locality', 'casasync') ?></label>
                        <input name="locality" class="span12" value="<?php echo (isset($_POST['locality']) ? $_POST['locality'] : '') ?>"  type="text" id="locality" />
                    </div>
                </div>
                <div class="row-fluid">
                    <div class="span12">
                        <label for="country"><?php echo __('Country', 'casasync') ?></label>
                        <select name="country" id="country" class="span12" style="margin-bottom:10px;">
                            <?php
                                $arr_countries = $this->conversion->country_arrays();
                                $arr_search   = array("Ä","ä","Ö","ö","Ü","ü");
                                $arr_replace  = array("Azze","azze","Ozze","ozze","Uzze","uzze");
                                $arr_modified = array();
                                foreach($arr_countries as $key => $val) {
                                    $arr_modified[$key] = str_replace($arr_search, $arr_replace, $val);
                                }
                                asort($arr_modified);
                                $arr_ordered_countries = array();
                                foreach($arr_modified as $key => $val) {
                                    $arr_ordered_countries[$key] = str_replace($arr_replace, $arr_search, $val);
                                }
                                
                                foreach($arr_ordered_countries AS $code => $country)
                                {
                                    (!isset($_POST['country'])) ? ($_POST['country'] = 'CH') : ('');
                                    $selected = ($_POST['country'] == $code ) ? ('selected=selected') : ('');
                                    echo '<option value="' . $code . '" ' . $selected . '>' . $country . '</option>';
                                }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row-fluid">
                    <label for="phone"><?php echo __('Phone', 'casasync') ?></label>
                    <input name="phone" class="span12" value="<?php echo (isset($_POST['phone']) ? $_POST['phone'] : '') ?>"  type="text" id="phone" />
                </div>
                 <div class="row-fluid">
                    <label for="emailreal"><?php echo __('Email', 'casasync') ?></label>
                    <input name="emailreal" class="span12" value="<?php echo (isset($_POST['emailreal']) ? $_POST['emailreal'] : '') ?>" type="text" id="emailreal" />
                </div>
                <div class="row-fluid">
                    <div class="span12">
                        <label for="message"><?php echo __('Message', 'casasync') ?></label>
                        <textarea name="message" class="span12" id="message"><?php echo (isset($_POST['message']) ? $_POST['message'] : '') ?></textarea>
                    </div>
                </div>
                <div class="row-fluid">
                    <div class="span7"><br>
                        <small><?php echo __('Please fill out all the fields', 'casasync') ?></small>
                    </div>
                    <div class="span5"><br>
                        <input type="submit" class="btn btn-primary pull-right" value="<?php echo __('Send', 'casasync') ?>" />
                    </div>
                </div>
            </form>
        <?php else: ?>
            <form id="casasyncPropertyContactForm" class="casasync-contactform-form" method="POST" action="<?php echo get_permalink(); ?>">
                <input id="theApsoluteRealEmailField" type="text" name="email" value="" placeholder="NlChT8 AuSf$lLeN" />
                <div class="casasync-row">
                    <div class="casasync-col-md-5">
                        <div class="casasync-form-group">
                            <label for="firstname"><?php echo __('First name', 'casasync') ?></label>
                            <input name="firstname" class="casasync-form-control" value="<?php echo (isset($_POST['firstname']) ? $_POST['firstname'] : '') ?>" type="text" id="firstname" />
                        </div>
                    </div>
                    <div class="casasync-col-md-7">
                        <div class="casasync-form-group">
                            <label for="lastname"><?php echo __('Last name', 'casasync') ?></label>
                            <input name="lastname" class="casasync-form-control" value="<?php echo (isset($_POST['lastname']) ? $_POST['lastname'] : '') ?>" type="text" id="lastname" />
                        </div>
                    </div>
                </div>
                <div class="casasync-row">
                    <div class="casasync-col-md-12">
                        <div class="form-group">
                            <label for="street"><?php echo __('Street', 'casasync') ?></label>
                            <input name="street" class="casasync-form-control" value="<?php echo (isset($_POST['street']) ? $_POST['street'] : '') ?>"  type="text" id="street" />
                        </div>
                    </div>
                </div>
                <div class="casasync-row">
                    <div class="casasync-col-md-4">
                        <div class="casasync-form-group">
                            <label for="postal_code"><?php echo __('ZIP', 'casasync') ?></label>
                            <input name="postal_code" class="casasync-form-control"  value="<?php echo (isset($_POST['postal_code']) ? $_POST['postal_code'] : '') ?>" type="text" id="postal_code" />
                        </div>
                    </div>
                    <div class="casasync-col-md-8">
                        <div class="form-group">
                            <label for="locality"><?php echo __('Locality', 'casasync') ?></label>
                            <input name="locality" class="casasync-form-control" value="<?php echo (isset($_POST['locality']) ? $_POST['locality'] : '') ?>"  type="text" id="locality" />
                        </div>
                    </div>
                </div>
                <div class="casasync-row">
                    <div class="casasync-col-md-12">
                        <div class="casasync-form-group">
                            <label for="country"><?php echo __('Country', 'casasync') ?></label>
                            <select name="country" id="country" class="casasync-form-control">
                                <?php
                                    $arr_countries = $this->conversion->country_arrays();
                                    $arr_search   = array("Ä","ä","Ö","ö","Ü","ü");
                                    $arr_replace  = array("Azze","azze","Ozze","ozze","Uzze","uzze");
                                    $arr_modified = array();
                                    foreach($arr_countries as $key => $val) {
                                        $arr_modified[$key] = str_replace($arr_search, $arr_replace, $val);
                                    }
                                    asort($arr_modified);
                                    $arr_ordered_countries = array();
                                    foreach($arr_modified as $key => $val) {
                                        $arr_ordered_countries[$key] = str_replace($arr_replace, $arr_search, $val);
                                    }
                                    
                                    foreach($arr_ordered_countries AS $code => $country)
                                    {
                                        (!isset($_POST['country'])) ? ($_POST['country'] = 'CH') : ('');
                                        $selected = ($_POST['country'] == $code ) ? ('selected=selected') : ('');
                                        echo '<option value="' . $code . '" ' . $selected . '>' . $country . '</option>';
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="casasync-row">
                    <div class="casasync-col-md-12">
                        <div class="casasync-form-group">
                            <label for="phone"><?php echo __('Phone', 'casasync') ?></label>
                            <input name="phone" class="casasync-form-control" value="<?php echo (isset($_POST['phone']) ? $_POST['phone'] : '') ?>"  type="text" id="phone" />
                        </div>
                    </div>
                </div>
                 <div class="casasync-row">
                    <div class="casasync-col-md-12">
                        <div class="casasync-form-group">
                            <label for="emailreal"><?php echo __('Email', 'casasync') ?></label>
                            <input name="emailreal" class="casasync-form-control" value="<?php echo (isset($_POST['emailreal']) ? $_POST['emailreal'] : '') ?>" type="text" id="emailreal" />
                        </div>
                    </div>
                </div>
                <div class="casasync-row">
                    <div class="casasync-col-md-12">
                        <div class="casasync-form-group">
                            <label for="message"><?php echo __('Message', 'casasync') ?></label>
                            <textarea name="message" class="casasync-form-control" id="message" rows="3"><?php echo (isset($_POST['message']) ? $_POST['message'] : '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="casasync-row">
                    <div class="casasync-form-group">
                        <div class="casasync-col-md-7">
                            <p class="casasync-form-control-static casasync-text-muted casasync-small"><?php echo __('Please fill out all the fields', 'casasync') ?></p>
                        </div>
                        <div class="casasync-col-md-5">
                            <input type="submit" class="casasync-contactform-send" value="<?php echo __('Send', 'casasync') ?>" />
                        </div>
                        <div class="clearBoth"></div>
                    </div>
                </div>
            </form>

        <?php endif; ?>
        <?php
            $form = ob_get_contents();
            ob_end_clean();
        } //validation
        return $form;  
    }


    //Format meta boxes
    function plib_format_box() {
        global $post;

        // Use nonce for verification
        echo '<input type="hidden" name="plib_meta_box_nonce" value="', wp_create_nonce(basename(__FILE__)), '" />';
        echo '<table class="form-table">';

        foreach ($this->meta_box[$post->post_type]['fields'] as $field) {
            // get current post meta data
            $meta = get_post_meta($post->ID, $field['id'], true);

            echo '<tr>'.
                '<th style="width:20%"><label for="'. $field['id'] .'">'. $field['name']. '</label></th>'.
            '<td>';
            switch ($field['type']) {
                case 'text':
                    echo '<input type="text" name="'. $field['id']. '" id="'. $field['id'] .'" value="'. ($meta ? $meta : $field['default']) . '" size="30" style="width:97%" />'. '<br />'. $field['desc'];
                    break;
                case 'textarea':
                    echo '<textarea name="'. $field['id']. '" id="'. $field['id']. '" cols="60" rows="4" style="width:97%">'. ($meta ? $meta : $field['default']) . '</textarea>'. '<br />'. $field['desc'];
                    break;
                case 'select':
                    echo '<select name="'. $field['id'] . '" id="'. $field['id'] . '">';
                    foreach ($field['options'] as $option) {
                        echo '<option '. ( $meta == $option ? ' selected="selected"' : '' ) . '>'. $option . '</option>';
                    }
                    echo '</select>';
                    break;
                case 'radio':
                    foreach ($field['options'] as $option) {
                        echo '<input type="radio" name="' . $field['id'] . '" value="' . $option['value'] . '"' . ( $meta == $option['value'] ? ' checked="checked"' : '' ) . ' />' . $option['name'];
                    }
                    break;
                case 'checkbox':
                    echo '<input type="checkbox" name="' . $field['id'] . '" id="' . $field['id'] . '"' . ( $meta ? ' checked="checked"' : '' ) . ' />';
                    break;
            }
            echo '<td>'.'</tr>';
        }
        echo '</table>';
    }
}
