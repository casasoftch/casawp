<?php 

	$offers_array = array();
	if (have_posts()) {
		while (have_posts()) {
			the_post();
			$offer = $casasync->prepareOffer($post);
			$offer_array = $offer->to_array();
			$offers_array[] = $offer_array;
		}
	}

	echo json_encode($offers_array, true);