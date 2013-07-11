<?php 

// GET FEATURED IMAGE
  //add_image_size('featured_preview', 55, 55, true);  

  function casasync_get_featured_image($post_ID) {
    $post_thumbnail_id = get_post_thumbnail_id($post_ID);
    if ($post_thumbnail_id) {
      $post_thumbnail_img = wp_get_attachment_image_src($post_thumbnail_id, 'thumbnail');
      return $post_thumbnail_img[0];
    } else {
      return 'http://placehold.it/150&text=Kein+Bild';
    }
  }
  // ADD NEW COLUMN
  function casasync_columns_head($defaults) {
    $defaults['featured_image'] = '';
    return $defaults;
  }

  // SHOW THE FEATURED IMAGE
  function casasync_columns_content($column_name, $post_ID) {
    if ($column_name == 'featured_image') {
      $post_featured_image = casasync_get_featured_image($post_ID);
      if ($post_featured_image) {
        echo '<img height="75" width="75" src="' . $post_featured_image . '" />';
      }
    }
  }

 	add_filter('manage_posts_columns', 'casasync_columns_head');  
	add_action('manage_posts_custom_column', 'casasync_columns_content', 10, 2);  