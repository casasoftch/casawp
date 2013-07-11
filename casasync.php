<?php

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}


//include_once dirname( __FILE__ ) . '/widget.php';

if ( is_admin() ) {
	require_once CASASYNC_PLUGIN_DIR . 'admin.php';
}


//fix wordpress bug
add_action( 'parse_query', 'wpse_71157_parse_query' );
function wpse_71157_parse_query( $wp_query )
{
    if ( $wp_query->is_post_type_archive && $wp_query->is_tax )
        $wp_query->is_post_type_archive = false;
}


//upload dir
function casasync_upload_dir($upload) {
	$upload['subdir']	= '/casasync' . $upload['subdir'];
	$upload['path']		= $upload['basedir'] . $upload['subdir'];
	$upload['url']		= $upload['baseurl'] . $upload['subdir'];
	return $upload;
}

add_filter('upload_dir', 'casasync_upload_dir');
$upload = wp_upload_dir();
define('CASASYNC_CUR_UPLOAD_PATH', $upload['path'] );
define('CASASYNC_CUR_UPLOAD_URL', $upload['url'] );
define('CASASYNC_CUR_UPLOAD_BASEDIR', $upload['basedir'] );
define('CASASYNC_CUR_UPLOAD_BASEURL', $upload['baseurl'] );
remove_filter('upload_dir', 'casasync_upload_dir');



function casasync_init() {
  //languages
  //load_theme_textdomain( 'casasync', plugin_dir_path( __FILE__ ) . '/languages' );

  $locale = get_locale();
  //$locale_file = get_template_directory_uri() . "/includes/languages/$locale.php";
  $locale_file = CASASYNC_PLUGIN_DIR . "languages/$locale.php";
  if ( is_readable( $locale_file ) ) {
    require_once( $locale_file );
  }



	$some_option = get_option('some_option');

	if(is_admin()) {
        require_once(CASASYNC_PLUGIN_DIR.'includes/admin.php');
	}

    require_once(CASASYNC_PLUGIN_DIR.'includes/import.php');
    require_once(CASASYNC_PLUGIN_DIR.'includes/core.php');
    require_once(CASASYNC_PLUGIN_DIR.'includes/shortcodes.php');


	//make sure casasync upload dir is present
	if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync')) {
		mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync');
	}

	//register post type
	$labels = array(
    	'name' => 'Properties',
    	'singular_name' => 'Property',
    	'add_new' => 'Add New',
    	'add_new_item' => 'Add New Property',
    	'edit_item' => 'Edit Property',
    	'new_item' => 'New Property',
    	'all_items' => 'All Properties',
    	'view_item' => 'View Property',
    	'search_items' => 'Search Properties',
    	'not_found' =>  'No properties found',
    	'not_found_in_trash' => 'No properties found in Trash',
    	'parent_item_colon' => '',
    	'menu_name' => 'Properties'
  	);
	$args = array(
		'labels' => $labels,
    	'public' => true,
    	'publicly_queryable' => true,
    	'show_ui' => true,
    	'show_in_menu' => true,
    	'query_var' => true,
    	'rewrite' => array( 'slug' => 'property' ),
    	'capability_type' => 'post',
    	'has_archive' => true,
    	'hierarchical' => false,
    	'menu_position' => null,
   		'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
      'menu_icon' => CASASYNC_PLUGIN_URL . 'assets/img/building.png'
	);
	register_post_type( 'casasync_property', $args );



	$labels = array(
    	'name'                => _x( 'Property Categories', 'taxonomy general name' ),
    	'singular_name'       => _x( 'Property Category', 'taxonomy singular name' ),
    	'search_items'        => __( 'Search Property Categories' ),
    	'all_items'           => __( 'All Property Categories' ),
    	'parent_item'         => __( 'Parent Property Category' ),
    	'parent_item_colon'   => __( 'Parent Property Category:' ),
    	'edit_item'           => __( 'Edit Property Category' ),
    	'update_item'         => __( 'Update Property Category' ),
    	'add_new_item'        => __( 'Add New Property Category' ),
    	'new_item_name'       => __( 'New Property Category Name' ),
    	'menu_name'           => __( 'Property Category' )
  	);

  	$args = array(
  		'hierarchical'        => true,
  		'labels'              => $labels,
  		'show_ui'             => true,
  		'show_admin_column'   => true,
  		'query_var'           => true,
  		'rewrite'             => array( 'slug' => 'property-category' )
  	);

  	register_taxonomy( 'casasync_category', array( 'casasync_property' ), $args );



  	$labels = array(
    	'name'                => _x( 'Property Locations', 'taxonomy general name' ),
    	'singular_name'       => _x( 'Property Location', 'taxonomy singular name' ),
    	'search_items'        => __( 'Search Property Locations' ),
    	'all_items'           => __( 'All Property Locations' ),
    	'parent_item'         => __( 'Parent Property Location' ),
    	'parent_item_colon'   => __( 'Parent Property Location:' ),
    	'edit_item'           => __( 'Edit Property Location' ),
    	'update_item'         => __( 'Update Property Location' ),
    	'add_new_item'        => __( 'Add New Property Location' ),
    	'new_item_name'       => __( 'New Property Location Name' ),
    	'menu_name'           => __( 'Property Location' )
  	);

  	$args = array(
  		'hierarchical'        => true,
  		'labels'              => $labels,
  		'show_ui'             => true,
  		'show_admin_column'   => true,
  		'query_var'           => true,
  		'rewrite'             => array( 'slug' => 'property-location' )
  	);

  	register_taxonomy( 'casasync_location', array( 'casasync_property' ), $args );


  	$labels = array(
    	'name'                => _x( 'Property Salestypes', 'taxonomy general name' ),
    	'singular_name'       => _x( 'Property Salestype', 'taxonomy singular name' ),
    	'search_items'        => __( 'Search Property Salestypes' ),
    	'all_items'           => __( 'All Property Salestypes' ),
    	'parent_item'         => __( 'Parent Property Salestype' ),
    	'parent_item_colon'   => __( 'Parent Property Salestype:' ),
    	'edit_item'           => __( 'Edit Property Salestype' ),
    	'update_item'         => __( 'Update Property Salestype' ),
    	'add_new_item'        => __( 'Add New Property Salestype' ),
    	'new_item_name'       => __( 'New Property Salestype Name' ),
    	'menu_name'           => __( 'Property Salestype' )
  	);

  	$args = array(
  		'hierarchical'        => false,
  		'labels'              => $labels,
  		'show_ui'             => true,
  		'show_admin_column'   => true,
  		'query_var'           => true,
  		'rewrite'             => array( 'slug' => 'property-salesstype' )
  	);

  	register_taxonomy( 'casasync_salestype', array( 'casasync_property' ), $args );


    //prefill statuses




  	//category for attachments
  	$labels = array(
    	'name'                => _x( 'Casasync Types', 'taxonomy general name' ),
    	'singular_name'       => _x( 'Casasync Type', 'taxonomy singular name' ),
    	'search_items'        => __( 'Search Casasync Types' ),
    	'all_items'           => __( 'All Casasync Types' ),
    	'parent_item'         => __( 'Parent Casasync Type' ),
    	'parent_item_colon'   => __( 'Parent Casasync Type:' ),
    	'edit_item'           => __( 'Edit Casasync Type' ),
    	'update_item'         => __( 'Update Casasync Type' ),
    	'add_new_item'        => __( 'Add New Casasync Type' ),
    	'new_item_name'       => __( 'New Casasync Type Name' ),
    	'menu_name'           => __( 'Casasync Type' )
  	);

  	$args = array(
  		'hierarchical'        => true,
  		'labels'              => $labels,
  		'show_ui'             => true,
  		'show_admin_column'   => true,
  		'query_var'           => true,
  		'rewrite'             => array( 'slug' => 'property-atachment-type' )
  	);
  	register_taxonomy( 'casasync_attachment_type', array(), $args );
  	register_taxonomy_for_object_type('casasync_attachment_type', 'attachment');
   	add_post_type_support('attachment', 'casasync_attachment_type');
	$id1 = wp_insert_term('Image', 'casasync_attachment_type', array('slug' => 'image'));
	$id2 = wp_insert_term('Plan', 'casasync_attachment_type', array('slug' => 'plan'));
	$id2 = wp_insert_term('Document', 'casasync_attachment_type', array('slug' => 'document'));

  if (get_option( 'casasync_live_import') == 1 || (isset($_GET['do_import']) && !isset($_POST['casasync_submit']) ) ) {
    casasync_import();
  }

}
add_action('init', 'casasync_init');


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
$meta_box['casasync_property'] = array(
    'id' => 'property-meta-details',
    'title' => 'Property Details',
    'context' => 'normal',
    'priority' => 'high',
    'fields' => $fields

);

add_action('admin_menu', 'plib_add_box');

//Add meta boxes to post types
function plib_add_box() {
    global $meta_box;

    foreach($meta_box as $post_type => $value) {
        add_meta_box($value['id'], $value['title'], 'plib_format_box', $post_type, $value['context'], $value['priority']);
    }
}

//Format meta boxes
function plib_format_box() {
  global $meta_box, $post;

  // Use nonce for verification
  echo '<input type="hidden" name="plib_meta_box_nonce" value="', wp_create_nonce(basename(__FILE__)), '" />';

  echo '<table class="form-table">';

  foreach ($meta_box[$post->post_type]['fields'] as $field) {
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

// Save data from meta box
/*function plib_save_data($post_id) {
    global $meta_box,  $post;

    //Verify nonce
    if (isset($_POST['plib_meta_box_nonce'])) {
      if (!wp_verify_nonce($_POST['plib_meta_box_nonce'], basename(__FILE__))) {
          return $post_id;
      }
    }

    //Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    //Check permissions
    if ('page' == $_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) {
            return $post_id;
        }
    } elseif (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    foreach ($meta_box[$post->post_type]['fields'] as $field) {
        $old = get_post_meta($post_id, $field['id'], true);
        $new = $_POST[$field['id']];

        if ($new && $new != $old) {
            update_post_meta($post_id, $field['id'], $new);
        } elseif ('' == $new && $old) {
            delete_post_meta($post_id, $field['id'], $old);
        }
    }
}

add_action('save_post', 'plib_save_data');*/


function casasync_load_bootstrap() {
	wp_enqueue_script(
		'bootstrap',
		CASASYNC_PLUGIN_URL .'assets/js/bootstrap.min.js'	);

}
if (get_option( 'casasync_load_bootstrap', 1 )) {
  add_action( 'wp_enqueue_scripts', 'casasync_load_bootstrap' );
}

function casasync_load_bootstrap_css() {
  wp_register_style( 'twitter-bootstrap', CASASYNC_PLUGIN_URL . 'assets/css/bootstrap.min.css' );
  wp_enqueue_style( 'twitter-bootstrap' );

  wp_register_style( 'twitter-bootstrap', CASASYNC_PLUGIN_URL . 'assets/css/bootstrap-responsive.min.css' );
  wp_enqueue_style( 'twitter-bootstrap' );
}
if (get_option( 'casasync_load_bootstrap_css', 1 )) {
  add_action( 'wp_enqueue_scripts', 'casasync_load_bootstrap_css' );
}
function casasync_load_bootstrap_multiselect() {
	wp_enqueue_script(
		'bootstrap_multiselect',
    CASASYNC_PLUGIN_URL . 'assets/js/bootstrap-multiselect.js'
	);
}
if (get_option( 'casasync_load_multiselector', 1 )) {
  add_action( 'wp_enqueue_scripts', 'casasync_load_bootstrap_multiselect' );
}

function casasync_load_script() {
	wp_enqueue_script(
		'casasync_script',
    CASASYNC_PLUGIN_URL . 'assets/js/script.js'
	);
}
if (get_option( 'casasync_load_scripts', 1 )) {
  add_action( 'wp_enqueue_scripts', 'casasync_load_script' );
}


function load_jquery() {

    // only use this method is we're not in wp-admin
    if ( ! is_admin() ) {

        // deregister the original version of jQuery
        //wp_deregister_script('jquery');

        // register it again, this time with no file path
        //wp_register_script('jquery', '', FALSE, '1.6.4');

        // add it back into the queue
        wp_enqueue_script('jquery');

    }

}

if (get_option( 'casasync_load_jquery', 0 )) {
  add_action('template_redirect', 'load_jquery');
}

function casasync_load_fancybox() {
  wp_enqueue_script(
    'fancybox',
    CASASYNC_PLUGIN_URL . 'assets/js/jquery.fancybox.js',
    array( 'bootstrap' )
  );
  wp_register_style( 'fancybox', CASASYNC_PLUGIN_URL . 'assets/css/jquery.fancybox.css' );
  wp_enqueue_style( 'fancybox' );
}
if (get_option( 'casasync_load_fancybox', 1 )) {
  add_action( 'wp_enqueue_scripts', 'casasync_load_fancybox' );
}

function casasync_load_stylesheet(){
  wp_register_style( 'casasync-style', CASASYNC_PLUGIN_URL . 'assets/css/casasync.css' );
  wp_enqueue_style( 'casasync-style' );
}
if (get_option( 'casasync_load_stylesheet', 1 )) {
  add_action( 'wp_enqueue_scripts', 'casasync_load_stylesheet' );
}

function casasync_load_fontawesome(){
  wp_register_style( 'casasync-fontawesome', CASASYNC_PLUGIN_URL . 'assets/css/font-awesome.min.css' );
  wp_enqueue_style( 'casasync-fontawesome' );
}

if (get_option( 'casasync_load_fontawesome', 1 )) {
  add_action( 'wp_enqueue_scripts', 'casasync_load_fontawesome' );
}

/**
 * Adding our custom fields to the $form_fields array
 */
function casasync_image_attachment_fields_to_edit($form_fields, $post) {
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
add_filter("attachment_fields_to_edit", "casasync_image_attachment_fields_to_edit", null, 2);

function casasync_image_attachment_fields_to_save($post, $attachment) {
	if( isset($attachment['origin']) ){
		update_post_meta($post['ID'], '_origin', $attachment['origin']);
	}
	return $post;
}
add_filter("attachment_fields_to_save", "casasync_image_attachment_fields_to_save", null, 2);


//category archive loop settings
function customize_casasync_category($query){
  if ($query->is_main_query()) {
  	if (
        (is_tax('casasync_salestype'))
        || (is_tax('casasync_category'))
        || (is_tax('casasync_location'))
        || (is_post_type_archive( 'casasync_property' ))
      ) {
  		$query->set('posts_per_page', '20');
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
add_action('pre_get_posts', 'customize_casasync_category');

//custom archive template
function include_template_function( $template_path ) {
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
    	||	is_tax( 'casasync_category' )
    	||	is_tax( 'casasync_location' )
      ||  (is_post_type_archive( 'casasync_property' ))
    ) {
    	if ( $theme_file = locate_template( array( 'taxonomy-casasync_salestype.php' ) ) ) {
            $template_path = $theme_file;
        } else {
            $template_path = CASASYNC_PLUGIN_DIR. '/archive.php';
        }
    }

    return $template_path;
}
add_filter( 'template_include', 'include_template_function', 1 );



if ( function_exists( 'add_theme_support' ) ) {
	add_theme_support( 'post-thumbnails' );
        set_post_thumbnail_size( 150, 150 ); // default Post Thumbnail dimensions
}

if ( function_exists( 'add_image_size' ) ) {
	add_image_size( 'category-thumb', 300, 9999 );
	add_image_size( 'casasync-thumb', 220, 180, true );
  add_image_size( 'casasync_archive', get_option('casasync_archive_thumb_w', '500'), get_option('casasync_archive_thumb_h', '250'), true );
}


/**
 * Activation, Deactivation and Uninstall Functions
 *
 **/
register_activation_hook(__FILE__, 'casasync_activation');
register_deactivation_hook(__FILE__, 'casasync_deactivation');


function casasync_activation() {

	//actions to perform once on plugin activation go here


    //register uninstaller
    register_uninstall_hook(__FILE__, 'casasync_uninstall');
}

function casasync_deactivation() {

	// actions to perform once on plugin deactivation go here

}

function casasync_uninstall(){

    //actions to perform once on plugin uninstall go here

}


