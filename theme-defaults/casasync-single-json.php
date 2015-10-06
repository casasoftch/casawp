<?php 

	$offer = $casasync->getOffer($post);
	$offer_array = $offer->to_array();

	echo json_encode($offer_array, true);