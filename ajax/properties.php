<?php 
	$single = new CasaSync\Single($post);
	$propertyQuery = $single->getPropertyQuery();

	$mapData = array();
	$i=1;
	foreach ($propertyQuery->posts as $key => $post) {
		$featured_img_src = wp_get_attachment_image_src(get_post_thumbnail_id( get_the_ID()), 'medium' );
		$mapData[$i] = array();
		$mapData[$i]['id'] = $post->ID;
		$mapData[$i]['title'] = $post->post_title;
		$mapData[$i]['permalink'] = get_permalink($post->ID);
		$mapData[$i]['lat'] = get_post_meta(get_the_ID(), ('casasync_property_geo_latitude'), true );
		$mapData[$i]['lng'] = get_post_meta(get_the_ID(), ('casasync_property_geo_longitude'), true );
		$mapData[$i]['img_src'] = $featured_img_src['0'];
		$i++;
	}
	echo json_encode($mapData);
?>


