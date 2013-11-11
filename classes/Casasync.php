<?php
namespace CasaSync;

class CasaSync {  
    public $textids = false;
    public $fields = false;
    public $meta_box = false;
    public $admin = false;
    public function __construct(){  
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
      add_action('template_redirect', array($this, 'load_jquery'));
      add_action('wp_enqueue_scripts', array($this, 'registerScriptsAndStyles'));
      add_filter("attachment_fields_to_edit", array($this, "casasync_image_attachment_fields_to_edit"), null, 2);
      add_filter("attachment_fields_to_save", array($this, "casasync_image_attachment_fields_to_save"), null, 2);
      add_action('pre_get_posts', array($this, 'customize_casasync_category'));
      add_filter( 'template_include', array($this, 'include_template_function'), 1 );
      register_activation_hook(CASASYNC_PLUGIN_DIR, array($this, 'casasync_activation'));
      register_deactivation_hook(CASASYNC_PLUGIN_DIR, array($this, 'casasync_deactivation'));
      if ( function_exists( 'add_theme_support' ) ) {
        add_theme_support( 'post-thumbnails' );
        set_post_thumbnail_size( 150, 150 ); // default Post Thumbnail dimensions
      }
      if ( function_exists( 'add_image_size' ) ) {
        add_image_size( 'category-thumb', 300, 9999 );
        add_image_size( 'casasync-thumb', 220, 180, true );
        add_image_size( 'casasync_archive', get_option('casasync_archive_thumb_w', '500'), get_option('casasync_archive_thumb_h', '250'), true );
      }
      $this->setMetaBoxes();
      $this->setTranslation();

      
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
        // checks if the file exists in the theme first,
        // otherwise serve the file from the plugin
        if ( $theme_file = locate_template( array( 'single-casasync_property.php' ) ) ) {
            $template_path = $theme_file;
        } else {
            $template_path = CASASYNC_PLUGIN_DIR . '/single.php';
        }
      }
      if (
          is_tax( 'casasync_salestype' )
        ||  is_tax( 'casasync_category' )
        ||  is_tax( 'casasync_location' )
        ||  (is_post_type_archive( 'casasync_property' ))
      ) {
        if ( $theme_file = locate_template( array( 'taxonomy-casasync_salestype.php' ) ) ) {
              $template_path = $theme_file;
          } else {
              $template_path = CASASYNC_PLUGIN_DIR . '/archive.php';
          }
      }

      return $template_path;
    }

    public function customize_casasync_category($query){
      if ($query->is_main_query()) {
        if (
            (is_tax('casasync_salestype'))
            || (is_tax('casasync_category'))
            || (is_tax('casasync_location'))
            || (is_post_type_archive( 'casasync_property' ))
          ) {
          $posts_per_page = get_option('posts_per_page', 10);
          $query->set('posts_per_page', $posts_per_page);
          $query->set('orderby', 'date');

          $taxquery_new = array();

          if ((isset($_GET['casasync_category_s']) && is_array($_GET['casasync_category_s']) )) {
              $categories = $_GET['casasync_category_s'];
          } elseif (isset($_GET['casasync_category_s'])) {
              $categories = array($_GET['casasync_category_s']);
          } else {
              $categories = array();
          }
          if ($categories) {
            $taxquery_new[] =
               array(
                   'taxonomy' => 'casasync_category',
                   'terms' => $categories,
                   'include_children' => 1,
                   'field' => 'slug',
                   'operator'=> 'IN'
               )
            ;
          }
          if ((isset($_GET['casasync_location_s']) && is_array($_GET['casasync_location_s']) )) {
              $locations = $_GET['casasync_location_s'];
          } elseif (isset($_GET['casasync_location_s'])) {
              $locations = array($_GET['casasync_location_s']);
          } else {
              $locations = array();
          }
          if ($locations) {
            $taxquery_new[] =
               array(
                   'taxonomy' => 'casasync_location',
                   'terms' => $locations,
                   'include_children' => 1,
                   'field' => 'slug',
                   'operator'=> 'IN'
               )
            ;
          }


          if ((isset($_GET['casasync_salestype_s']) && is_array($_GET['casasync_salestype_s']) )) {
              $salestypes = $_GET['casasync_salestype_s'];
          } elseif (isset($_GET['casasync_salestype_s'])) {
              $salestypes = array($_GET['casasync_salestype_s']);
          } else {
              $salestypes = array('rent','buy');
          }
          if ($salestypes) {
            $taxquery_new[] =
               array(
                   'taxonomy' => 'casasync_salestype',
                   'terms' => $salestypes,
                   'include_children' => 1,
                   'field' => 'slug',
                   'operator'=> 'IN'
               )
            ;
          }

          if ($taxquery_new) {
            $query->set('tax_query', $taxquery_new);
          }

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

    public function load_jquery() {
      //if (!is_admin() && get_option( 'casasync_load_jquery', 0 ) ) {
          wp_enqueue_script('jquery');
      //}
    }

    function registerScriptsAndStyles(){
      
      switch (get_option( 'casasync_template', 0 )) {
        default:
          wp_register_style( 'casasync-css', CASASYNC_PLUGIN_URL . 'assets/css/casasync_template_bs3.css' );
            wp_enqueue_style( 'casasync-css' );
          break;
      }
      
      if (get_option( 'casasync_load_scripts', 1 )) {
        wp_enqueue_script(
          'casasync_bootstrap_transition',
          CASASYNC_PLUGIN_URL . 'assets/js/bootstrap3/transition.js'
        );
        wp_enqueue_script(
          'casasync_bootstrap_tab',
          CASASYNC_PLUGIN_URL . 'assets/js/bootstrap3/tab.js'
        );
        wp_enqueue_script(
          'casasync_bootstrap_carousel',
          CASASYNC_PLUGIN_URL . 'assets/js/bootstrap3/carousel.js'
        );

      }

      wp_enqueue_script(
          'casasync_jquery_eqheight',
          CASASYNC_PLUGIN_URL . 'assets/js/jquery.equal-height-columns.js'
        );

      //if (get_option( 'casasync_load_fancybox', 1 )) {
        wp_enqueue_script(
          'fancybox',
          CASASYNC_PLUGIN_URL . 'assets/js/jquery.fancybox.js',
          array( 'bootstrap' )
        );
        wp_register_style( 'fancybox', CASASYNC_PLUGIN_URL . 'assets/css/jquery.fancybox.css' );
        wp_enqueue_style( 'fancybox' );
      //};

      wp_enqueue_script(
        'google_maps_v3',
        'https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false'
      );

      if (get_option( 'casasync_load_scripts', 1 )) {
        wp_enqueue_script(
          'casasync_script',
          CASASYNC_PLUGIN_URL . 'assets/js/script.js'
        );
      }



      /*
      if (get_option( 'casasync_load_bootstrap_css', 1 )) {
        wp_register_style( 'twitter-bootstrap', CASASYNC_PLUGIN_URL . 'assets/css/bootstrap.min.css' );
        wp_enqueue_style( 'twitter-bootstrap' );

        wp_register_style( 'twitter-bootstrap', CASASYNC_PLUGIN_URL . 'assets/css/bootstrap-responsive.min.css' );
        wp_enqueue_style( 'twitter-bootstrap' );
      }
      if (get_option( 'casasync_load_multiselector', 1 )) {
        wp_enqueue_script(
          'bootstrap_multiselect',
          CASASYNC_PLUGIN_URL . 'assets/js/bootstrap-multiselect.js'
        );
      }
      
      if (get_option( 'casasync_load_stylesheet', 1 )) {
        wp_register_style( 'casasync-style', CASASYNC_PLUGIN_URL . 'assets/css/casasync.css?v=2' );
        wp_register_style( 'casasync-style-ie', CASASYNC_PLUGIN_URL . 'assets/css/casasync_ie.css' );
        $GLOBALS['wp_styles']->add_data( 'casasync-style-ie', 'conditional', 'IE' );
        wp_enqueue_style( 'casasync-style' );
        //wp_enqueue_style( 'casasync-style-ie' );
      }

      if (get_option( 'casasync_load_fontawesome', 1 )) {
        wp_register_style( 'casasync-fontawesome', CASASYNC_PLUGIN_URL . 'assets/css/font-awesome.min.css' );
        wp_enqueue_style( 'casasync-fontawesome' );
      }
*/

    }

    function setTranslation(){
      $locale = get_locale();
      //$locale_file = get_template_directory_uri() . "/includes/languages/$locale.php";
      $locale_file = CASASYNC_PLUGIN_DIR . "languages/$locale.php";
      if ( is_readable( $locale_file ) ) {
        require_once( $locale_file );
      }
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
            'name' => $label,
            'desc' => '',
            'id' => $id,
            'type' => 'text',
            'default' => ''
        );
      }
      $this->fields = $fields;

      $meta_box['casasync_property'] = array(
          'id' => 'property-meta-details',
          'title' => 'Property Details',
          'context' => 'normal',
          'priority' => 'high',
          'fields' => $fields
      );
      $this->meta_box = $meta_box;

      foreach($this->meta_box as $post_type => $value) {
          //add_meta_box($value['id'], $value['title'], array($this, 'plib_format_box'), $post_type, $value['context'], $value['priority']);
      }
    }

    public function setPostTypes(){
      //register post type
      $labels = array(
          'name'               => 'Properties',
          'singular_name'      => 'Property',
          'add_new'            => 'Add New',
          'add_new_item'       => 'Add New Property',
          'edit_item'          => 'Edit Property',
          'new_item'           => 'New Property',
          'all_items'          => 'All Properties',
          'view_item'          => 'View Property',
          'search_items'       => 'Search Properties',
          'not_found'          =>  'No properties found',
          'not_found_in_trash' => 'No properties found in Trash',
          'parent_item_colon'  => '',
          'menu_name'          => 'Properties'
        );
      $args = array(
        'labels'               => $labels,
          'public'             => true,
          'publicly_queryable' => true,
          'show_ui'            => true,
          'show_in_menu'       => true,
          'query_var'          => true,
          'rewrite'            => array( 'slug' => 'property' ),
          'capability_type'    => 'post',
          'has_archive'        => true,
          'hierarchical'       => false,
          'menu_position'      => null,
          'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
          'menu_icon'          => CASASYNC_PLUGIN_URL . 'assets/img/building.png'
      );
      register_post_type( 'casasync_property', $args );

      $labels = array(
          'name'              => _x( 'Property Categories', 'taxonomy general name' ),
          'singular_name'     => _x( 'Property Category', 'taxonomy singular name' ),
          'search_items'      => __( 'Search Property Categories' ),
          'all_items'         => __( 'All Property Categories' ),
          'parent_item'       => __( 'Parent Property Category' ),
          'parent_item_colon' => __( 'Parent Property Category:' ),
          'edit_item'         => __( 'Edit Property Category' ),
          'update_item'       => __( 'Update Property Category' ),
          'add_new_item'      => __( 'Add New Property Category' ),
          'new_item_name'     => __( 'New Property Category Name' ),
          'menu_name'         => __( 'Property Category' )
        );
        $args = array(
          'hierarchical'      => true,
          'labels'            => $labels,
          'show_ui'           => true,
          'show_admin_column' => true,
          'query_var'         => true,
          'rewrite'           => array( 'slug' => 'property-category' )
        );

        register_taxonomy( 'casasync_category', array( 'casasync_property' ), $args );

        $labels = array(
          'name'              => _x( 'Property Locations', 'taxonomy general name' ),
          'singular_name'     => _x( 'Property Location', 'taxonomy singular name' ),
          'search_items'      => __( 'Search Property Locations' ),
          'all_items'         => __( 'All Property Locations' ),
          'parent_item'       => __( 'Parent Property Location' ),
          'parent_item_colon' => __( 'Parent Property Location:' ),
          'edit_item'         => __( 'Edit Property Location' ),
          'update_item'       => __( 'Update Property Location' ),
          'add_new_item'      => __( 'Add New Property Location' ),
          'new_item_name'     => __( 'New Property Location Name' ),
          'menu_name'         => __( 'Property Location' )
        );
        $args = array(
          'hierarchical'      => true,
          'labels'            => $labels,
          'show_ui'           => true,
          'show_admin_column' => true,
          'query_var'         => true,
          'rewrite'           => array( 'slug' => 'property-location' )
        );

        register_taxonomy( 'casasync_location', array( 'casasync_property' ), $args );


        $labels = array(
          'name'              => _x( 'Property Salestypes', 'taxonomy general name' ),
          'singular_name'     => _x( 'Property Salestype', 'taxonomy singular name' ),
          'search_items'      => __( 'Search Property Salestypes' ),
          'all_items'         => __( 'All Property Salestypes' ),
          'parent_item'       => __( 'Parent Property Salestype' ),
          'parent_item_colon' => __( 'Parent Property Salestype:' ),
          'edit_item'         => __( 'Edit Property Salestype' ),
          'update_item'       => __( 'Update Property Salestype' ),
          'add_new_item'      => __( 'Add New Property Salestype' ),
          'new_item_name'     => __( 'New Property Salestype Name' ),
          'menu_name'         => __( 'Property Salestype' )
        );
        $args = array(
          'hierarchical'      => false,
          'labels'            => $labels,
          'show_ui'           => true,
          'show_admin_column' => true,
          'query_var'         => true,
          'rewrite'           => array( 'slug' => 'property-salesstype' )
        );

        register_taxonomy( 'casasync_salestype', array( 'casasync_property' ), $args );

        //category for attachments
        $labels = array(
          'name'              => _x( 'Casasync Types', 'taxonomy general name' ),
          'singular_name'     => _x( 'Casasync Type', 'taxonomy singular name' ),
          'search_items'      => __( 'Search Casasync Types' ),
          'all_items'         => __( 'All Casasync Types' ),
          'parent_item'       => __( 'Parent Casasync Type' ),
          'parent_item_colon' => __( 'Parent Casasync Type:' ),
          'edit_item'         => __( 'Edit Casasync Type' ),
          'update_item'       => __( 'Update Casasync Type' ),
          'add_new_item'      => __( 'Add New Casasync Type' ),
          'new_item_name'     => __( 'New Casasync Type Name' ),
          'menu_name'         => __( 'Casasync Type' )
        );
        $args = array(
          'hierarchical'      => true,
          'labels'            => $labels,
          'show_ui'           => true,
          'show_admin_column' => true,
          'query_var'         => true,
          'rewrite'           => array( 'slug' => 'property-atachment-type' )
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
        'recipients' => 'Jens Stalder:js@casasoft.ch',
        'ccs' => '',
        'post_id' => false
      ), $atts ) );
      $errors = array();
      $table = '';
      $validation = false;



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
          'state'       => __('Kanton', 'casasync'), //'Kanton',
          'subject'     => __('Subject', 'casasync'), //'Betreff',
          'message'     => __('Message', 'casasync'), //'Nachricht',
          'recipient'   => __('Recipient', 'casasync'), //'Rezipient',
      );




      if (!empty($_POST)) {
          $validation = true;
          $required = array(
            'firstname',
            'lastname',
            'emailreal',
            'subject',
            'street',
            'postal_code',
            'locality'
          );
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
              $casa_id_arr = explode('_', $casa_id);
              $customer_id = $casa_id_arr[0];
              $property_id = $casa_id_arr[1];

              
              //REM
              if (get_option('casasync_remCat', false ) && get_option('casasync_remCat_email', false )) {
                  $categories = wp_get_post_terms( get_the_ID(), 'casasync_category'); 
                  if ($categories) {
                      $type = casasync_convert_categoryKeyToLabel($categories[0]->name); 
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
                      12 => 'DE', //LANG
                      13 => '', //anrede
                      14 => (isset($_POST['firstname']) ? $_POST['firstname'] : ''),
                      15 => (isset($_POST['lastname']) ? $_POST['lastname'] : ''),
                      16 => (isset($_POST['company']) ? $_POST['company'] : ''),
                      17 => (isset($_POST['street']) ? $_POST['street'] : ''),
                      18 => (isset($_POST['postal_code']) ? $_POST['postal_code'] : ''),
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
                          $message.= '<tr><td align="left" style="padding-right:10px" valign="top"><strong>'.$fieldlabels[$key].'</strong></td><td align="left">' . nl2br($value) . '</td></tr>';
                      }
                  }
              }
              if ($post_id) {
                  $message .= '<tr></td colspan="2">&nbsp;</td></tr>';
                  $message .= '<tr>';
                  $message .= '<td colspan="2" class="property"><a href="' . get_permalink($post_id) . '" style="text-decoration: none; color: #969696; font-weight: bold; font-family: Helvetica, Arial, sans-serif;">Objekt anzeigen ...</a></td>';
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

              // :CC
              /*$the_ccs = array();
              if (isset($css_arr) && $css_arr) {
                  foreach ($ccs_arr as $cc) {
                      $the_ccs[] = $cc[1];
                  }
                  $the_cc = implode(', ', $the_ccs);

                  $headers .= "Cc: " . $the_cc . "\r\n";
              }*/

              foreach ($recipientses as $recipient2) {
                  if (isset($recipient2[1])) {
                      if (wp_mail($recipient2[1], 'Neue Anfrage', $template, $header)) {
                          return '<p class="alert alert-success">Vielen Dank!</p>';
                      } else {
                          return '<p class="alert alert-danger">Fehler!</p>';
                      }
                  } else {
                      if (isset($remCat)) {
                          return '<p class="alert alert-success">Vielen Dank!</p>';
                      } else {
                          return '<p class="alert alert-danger">Fehler!</p>';
                      }
                  }
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
              echo "<strong>" . __('Please consider the following errors and try sending it again', 'casasync')  . "</strong>"; //Bitte beachten Sie folgendes und versuchen Sie es erneut
              echo "<ul>";
              echo "<li>".implode('</li><li>', $errors) . '</li>';
              echo '</ul>';
              echo "</div>";
          }
          echo $table;
      ?>
          <form id="casasyncPropertyContactForm" class="casasync-contactform-form" method="POST" action="">
              <input id="theApsoluteRealEmailField" type="text" name="email" value="" placeholder="NlChT8 AuSf$lLeN" />
              <div class="casasync-contactform-row">
                  <div class="col-md-5">
                    <div class="form-group">
                      <label for="firstname"><?php echo __('First name', 'casasync') ?></label>
                      <input name="firstname" class="form-control" value="<?php echo (isset($_POST['firstname']) ? $_POST['firstname'] : '') ?>" type="text" id="firstname" />
                    </div>
                  </div>
                  <div class="col-md-7">
                    <div class="form-group">
                      <label for="lastname"><?php echo __('Last name', 'casasync') ?></label>
                      <input name="lastname" class="form-control" value="<?php echo (isset($_POST['lastname']) ? $_POST['lastname'] : '') ?>" type="text" id="lastname" />
                    </div>
                  </div>
              </div>
              <div class="casasync-contactform-row">
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="street"><?php echo __('Street', 'casasync') ?></label>
                    <input name="street" class="form-control" value="<?php echo (isset($_POST['street']) ? $_POST['street'] : '') ?>"  type="text" id="street" />
                  </div>
                </div>
              </div>
              <div class="casasync-contactform-row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label for="postal_code"><?php echo __('ZIP', 'casasync') ?></label>
                      <input name="postal_code" class="form-control" value="<?php echo (isset($_POST['postal_code']) ? $_POST['postal_code'] : '') ?>"  value="<?php echo (isset($_POST['postal_code']) ? $_POST['postal_code'] : '') ?>" type="text" id="postal_code" />
                    </div>
                  </div>
                  <div class="col-md-8">
                    <div class="form-group">
                      <label for="locality"><?php echo __('Locality', 'casasync') ?></label>
                      <input name="locality" class="form-control" value="<?php echo (isset($_POST['locality']) ? $_POST['locality'] : '') ?>"  type="text" id="locality" />
                    </div>
                  </div>
              </div>
              <div class="casasync-contactform-row">
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="phone"><?php echo __('Phone', 'casasync') ?></label>
                    <input name="phone" class="form-control" value="<?php echo (isset($_POST['phone']) ? $_POST['phone'] : '') ?>"  type="text" id="tel" />
                  </div>
                </div>
              </div>
              <div class="casasync-contactform-row">
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="emailreal"><?php echo __('Email', 'casasync') ?></label>
                    <input name="emailreal" class="form-control" value="<?php echo (isset($_POST['emailreal']) ? $_POST['emailreal'] : '') ?>" type="text" id="emailreal" />
                  </div>
                </div>
              </div>
              <div class="casasync-contactform-row">
                  <div class="col-md-12">
                    <div class="form-group">
                      <label for="message"><?php echo __('Message', 'casasync') ?></label>
                      <textarea name="message" class="form-control" id="message"><?php echo (isset($_POST['message']) ? $_POST['message'] : '') ?></textarea>
                    </div>
                  </div>
              </div>
              <div class="casasync-contactform-row">
                <div class="form-group">
                  <div class="col-md-7">
                      <p class="form-control-static text-muted small"><?php echo __('Please fill out all the fields', 'casasync') ?></p>
                  </div>
                  <div class="col-md-5">
                      <input type="submit" class="casasync-contactform-send" value="<?php echo __('Send', 'casasync') ?>" />
                  </div>
                  <div class="clearBoth"></div>
                </div>
              </div>
          </form>

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
          echo     '<td>'.'</tr>';
      }

      echo '</table>';

    }


}  
