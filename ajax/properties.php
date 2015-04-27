<?php
	header('Content-Type: application/json');

	$single = new CasaSync\Single($post);
	$propertyQuery = $single->getPropertyQuery();

	$mapData = array();
	$i=1;
	foreach ($propertyQuery->posts as $key => $post) {
		$featured_img_src = wp_get_attachment_image_src(get_post_thumbnail_id( get_the_ID()), 'large' );

		$mapData[$i] = array();
		$mapData[$i]['id']        = $post->ID;
		$mapData[$i]['title']     = $post->post_title;
		$mapData[$i]['permalink'] = get_permalink($post->ID);
		$mapData[$i]['lat']       = get_post_meta(get_the_ID(), ('casasync_property_geo_latitude'), true );
		$mapData[$i]['lng']       = get_post_meta(get_the_ID(), ('casasync_property_geo_longitude'), true );

		$mapData[$i]['images'] = array();
		$mapData[$i]['images'] = getAllAttachments($post->ID);

		$mapData[$i]['meta'] = array(
			'address_locality' => $single->address_locality,
			'area_bwf'         => $single->getNumval('area_bwf') ? $single->getNumval('area_bwf') : '',
			'area_nwf'         => $single->getNumval('area_nwf') ? $single->getNumval('area_nwf') : '',
			'price'            => $single->getPrice('auto')
		);

		$i++;
	}

	echo json_encode($mapData);


	function getAllAttachments($post_id) {
		$images = array();
		$args = array(
			'post_type' => 'attachment',
			'numberposts' => -1,
			'post_status' => 'inherit publish',
			'post_parent' => $post_id,
			'orderby' => 'menu_order',
			'order' => 'ASC'
		);

		$attachments = get_posts( $args );
		if ( $attachments ) {
			foreach ( $attachments as $attachment ) {
				if ($attachment->post_mime_type == 'image/jpeg') {
					$images[$attachment->ID] = wp_get_attachment_image_src( $attachment->ID, 'large' );
				}
			}
		}

		return $images;
	}
?>


