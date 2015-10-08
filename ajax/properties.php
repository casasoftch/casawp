<?php
	header('Content-Type: application/json');

	/**
	 * unoptimzed way
	 **/

	$archive = new CasaWp\Archive();
	$mapData = array();
	$i=1;
	foreach ($archive->getProperties() as $property) {
		$mapData[$i] = array();
		$mapData[$i]['id']        = $property->property->ID;
		$mapData[$i]['title']     = $property->property->post_title;
		$mapData[$i]['excerpt']   = $property->property->excerpt;
		$mapData[$i]['permalink'] = get_permalink($property->property->ID);
		$mapData[$i]['lat']       = get_post_meta($property->property->ID, ('casawp_property_geo_latitude'), true );
		$mapData[$i]['lng']       = get_post_meta($property->property->ID, ('casawp_property_geo_longitude'), true );
		$mapData[$i]['images'] = array();
		$mapData[$i]['images'] = getAllAttachments($property->property->ID);
		$mapData[$i]['meta'] = array(
			'address_locality' => $property->address_locality,
			'area_bwf'         => $property->getNumval('area_bwf') ? $property->getNumval('area_bwf') : '',
			'area_nwf'         => $property->getNumval('area_nwf') ? $property->getNumval('area_nwf') : '',
			'price'            => (int) $property->getPrice('auto', 'full') ? $property->getPrice('auto', 'full') : __('On Request', 'casawp'),
			'price_label'      => $property->main_basis == 'rent' ? __('Rent price', 'casawp') : __('Sales price', 'casawp'),
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
		$single = new CasaWp\Single($post);


		$featured_img_src = wp_get_attachment_image_src(get_post_thumbnail_id( get_the_ID()), 'large' );

		$mapData[1] = array();
		$mapData[1]['id']        = $post->ID;
		$mapData[1]['title']     = $post->post_title;
		$mapData[1]['excerpt']   = $post->post_excerpt;
		$mapData[1]['permalink'] = get_permalink($post->ID);
		$mapData[1]['lat']       = get_post_meta($post->ID, ('casawp_property_geo_latitude'), true );
		$mapData[1]['lng']       = get_post_meta($post->ID, ('casawp_property_geo_longitude'), true );
		$mapData[1]['images'] = array();
		$mapData[1]['images'] = getAllAttachments($post->ID);

		
		$mapData[1]['meta'] = array(
			'address_locality' => $single->address_locality,
			'area_bwf'         => $single->getNumval('area_bwf') ? $single->getNumval('area_bwf') : '',
			'area_nwf'         => $single->getNumval('area_nwf') ? $single->getNumval('area_nwf') : '',
			'price'            => (int) $single->getPrice('auto', 'full') ? $single->getPrice('auto', 'full') : __('On Request', 'casawp') ,
			'price_label'      => $single->main_basis == 'rent' ? __('Rent price', 'casawp') : __('Sales price', 'casawp'),
		);
		return $mapData;
	}

	function getImportantData() {
		$single = new CasaWp\Single($post);
		$propertyQuery = $single->getPropertyQuery();

		$mapData = array();
		$i=1;
		foreach ($propertyQuery->posts as $key => $post) {
			#$single = new CasaWp\Single($post);

			$mapData[$i] = array();
			$mapData[$i]['id']        = $post->ID;
			$mapData[$i]['title']     = $post->post_title;
			$mapData[$i]['excerpt']   = $post->post_excerpt;

			$mapData[$i]['lat']       = get_post_meta($post->ID, ('casawp_property_geo_latitude'), true );
			$mapData[$i]['lng']       = get_post_meta($post->ID, ('casawp_property_geo_longitude'), true );
			
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


