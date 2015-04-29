<?php
	header('Content-Type: application/json');

	/**
	 * unoptimzed way
	 **/

	$single = new CasaSync\Single($post);
	$propertyQuery = $single->getPropertyQuery();
	$mapData = array();
	$i=1;
	foreach ($propertyQuery->posts as $key => $post) {
		$featured_img_src = wp_get_attachment_image_src(get_post_thumbnail_id( get_the_ID()), 'large' );
		$mapData[$i] = array();
		$mapData[$i]['id']        = $post->ID;
		$mapData[$i]['title']     = $post->post_title;
		$mapData[$i]['excerpt']   = $post->excerpt;
		$mapData[$i]['permalink'] = get_permalink($post->ID);
		$mapData[$i]['lat']       = get_post_meta(get_the_ID(), ('casasync_property_geo_latitude'), true );
		$mapData[$i]['lng']       = get_post_meta(get_the_ID(), ('casasync_property_geo_longitude'), true );
		$mapData[$i]['images'] = array();
		$mapData[$i]['images'] = getAllAttachments($post->ID);
		$mapData[$i]['meta'] = array(
			'address_locality' => $single->address_locality,
			'area_bwf'         => $single->getNumval('area_bwf') ? $single->getNumval('area_bwf') : '',
			'area_nwf'         => $single->getNumval('area_nwf') ? $single->getNumval('area_nwf') : '',
			'price'            => $single->getPrice('auto', 'full'),
			'price_label'      => $single->main_basis == 'rent' ? __('Rent price', 'casasync') : __('Sales price', 'casasync'),

		);
		$i++;
	}
	echo json_encode($mapData);

	/**
	 * Optimized way
	 **/

	/*if(isset($_GET['post_id'])) {
		$data = getPropertyData($_GET['post_id']);
	} else {
		$data = getImportantData();
	}
	echo json_encode($data);*/

	function getAllData($post_id) {
		#$propertyQuery = $single->getPropertyQuery();

		$post = get_post($post_id);

		$mapData = array();
		$single = new CasaSync\Single($post);


		$featured_img_src = wp_get_attachment_image_src(get_post_thumbnail_id( get_the_ID()), 'large' );

		$mapData[1] = array();
		$mapData[1]['id']        = $post->ID;
		$mapData[1]['title']     = $post->post_title;
		$mapData[1]['excerpt']   = $post->post_excerpt;
		$mapData[1]['permalink'] = get_permalink($post->ID);
		$mapData[1]['lat']       = get_post_meta($post->ID, ('casasync_property_geo_latitude'), true );
		$mapData[1]['lng']       = get_post_meta($post->ID, ('casasync_property_geo_longitude'), true );
		$mapData[1]['images'] = array();
		$mapData[1]['images'] = getAllAttachments($post->ID);

		
		$mapData[1]['meta'] = array(
			'address_locality' => $single->address_locality,
			'area_bwf'         => $single->getNumval('area_bwf') ? $single->getNumval('area_bwf') : '',
			'area_nwf'         => $single->getNumval('area_nwf') ? $single->getNumval('area_nwf') : '',
			'price'            => (int) $single->getPrice('auto', 'full') ? $single->getPrice('auto', 'full') : __('On Request', 'casasync') ,
			'price_label'      => $single->main_basis == 'rent' ? __('Rent price', 'casasync') : __('Sales price', 'casasync'),
		);
		return $mapData;
	}

	function getImportantData() {
		$single = new CasaSync\Single($post);
		$propertyQuery = $single->getPropertyQuery();

		$mapData = array();
		$i=1;
		foreach ($propertyQuery->posts as $key => $post) {
			#$single = new CasaSync\Single($post);

			$mapData[$i] = array();
			$mapData[$i]['id']        = $post->ID;
			$mapData[$i]['title']     = $post->post_title;
			$mapData[$i]['excerpt']   = $post->post_excerpt;

			$mapData[$i]['lat']       = get_post_meta($post->ID, ('casasync_property_geo_latitude'), true );
			$mapData[$i]['lng']       = get_post_meta($post->ID, ('casasync_property_geo_longitude'), true );
			
			$i++;
		}
		return $mapData;
	}


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
					$meta = wp_get_attachment_image_src( $attachment->ID, 'medium' );
					$images[] = $meta[0];
				}
			}
		}
		return $images;
	}
?>


