<?php get_header(); ?>

<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/<?php echo str_replace('-','_',get_bloginfo('language'));  ?>/all.js#xfbml=1";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>

	<?php while ( have_posts() ) : the_post();?>
	    	<?php $attachments = get_posts( array(
									'post_type' => 'attachment',
									'posts_per_page' => -1,
									'post_parent' => $post->ID,
									//'exclude'     => get_post_thumbnail_id(),
									'casasync_attachment_type' => 'image',
									'orderby' => 'menu_order',
									'order' => 'ASC'
								) ); 

	    		$documents = get_posts( array(
									'post_type' => 'attachment',
									'posts_per_page' => -1,
									'post_parent' => $post->ID,
									//'exclude'     => get_post_thumbnail_id(),
									'casasync_attachment_type' => 'document',
									'orderby' => 'menu_order'
								) ); 

	    		$plans = get_posts( array(
									'post_type' => 'attachment',
									'posts_per_page' => -1,
									'post_parent' => $post->ID,
									//'exclude'     => get_post_thumbnail_id(),
									'casasync_attachment_type' => 'plan'
								) ); 

			

				$address_street = get_post_meta( get_the_ID(), 'casasync_property_address_streetaddress', $single = true );
				$address_postalcode = get_post_meta( get_the_ID(), 'casasync_property_address_postalcode', $single = true );
				$address_region = get_post_meta( get_the_ID(), 'casasync_property_address_region', $single = true );
				$address_locality = get_post_meta( get_the_ID(), 'casasync_property_address_locality', $single = true );
				$address_country = get_post_meta( get_the_ID(), 'casasync_property_address_country', $single = true );
				$address_country_name = countrycode_to_countryname($address_country);

				$address  = ($address_street ? $address_street . '<br>' : '');
				$address .= ($address_postalcode ?  $address_postalcode . ' ': '') . ($address_locality ? $address_locality : '') . ($address_postalcode || $address_locality ? '<br>' : '');
				$address .= ($address_country_name ? $address_country_name : '');

				$casa_id = get_post_meta( get_the_ID(), 'casasync_id', $single = true );
				$casa_id_arr = explode('_', $casa_id);
				$customer_id = $casa_id_arr[0];
				$property_id = $casa_id_arr[1];

				$reference_id = get_post_meta( get_the_ID(), 'casasync_referenceId', $single = true );

				$start = get_post_meta( get_the_ID(), 'casasync_start', $single = true );

				$categories = wp_get_post_terms( get_the_ID(), 'casasync_category'); 
			      $categories_names = array();
			      foreach ($categories as $category) {
			      	$categories_names[] = casasync_convert_categoryKeyToLabel($category->name);
			      } 

			     $floors = get_post_meta( get_the_ID(), 'casasync_floors', $single = true ); 

			     $the_floors_arr = '';
				if ($floors) {
					$floors_quirk = trim($floors,"[");   
					$floors_quirk = trim($floors_quirk,"]");
					$floors_arr = explode(']+[', $floors_quirk);
					$largest_val = 0;
					$largest_key = 0;
					foreach ($floors_arr as $key => $value) {
						if ((int)$value > $largest_val ) {
							$largest_val = (int)$value;
							$largest_key = $key;
						}
						
						$the_floors_arr[] = $value . '. Stock' . ($value == '0' ? ' (EG)' : '');

	
					}
					if (isset($the_floors_arr[$largest_key])) {
						$the_floors_arr[$largest_key] = $floors_arr[$largest_key] . '. Stock (OG)';
					}
				}
			
				$basis = wp_get_post_terms( get_the_ID(), 'casasync_salestype'); 
             		$basises = array();
             		$basis_slugs = array();
             		foreach ($basis as $basi) {
             			$basises[] = __(ucfirst($basi->name),'casasync');
             			$basis_slugs[] = $basi->slug;
             		} 
             	if ($basis_slugs) {
             		$the_basis = $basis_slugs[0];
             	} else {
             		$the_basis = 'buy';
             	}


            	$price_currency = get_post_meta( get_the_ID(), 'price_currency', $single = true );
            	$timesegment_labels = array(
				                            'm' => __('month', 'casasync'),
				                            'w' => __('week', 'casasync'),
				                            'd' => __('day', 'casasync'),
				                            'y' => __('year', 'casasync'),
				                            'h' => __('hour', 'casasync')
				                        );


            	$price = get_post_meta( get_the_ID(), 'price', $single = true ); 
            	$price_formated = false;
            	$price_formated_timesegment = false;
            	if ($price) {
            		$propertysegment = get_post_meta( get_the_ID(), $key = 'price_propertysegment', $single = true );
	                $timesegment = get_post_meta( get_the_ID(), $key = 'price_timesegment', $single = true );
	                if (!in_array($timesegment, array('m','w','d','y','h','infinite'))) {
	                    $timesegment = 'infinite';
	                }
	                if (!in_array($propertysegment, array('m2','km2','full'))) {
	                    $propertysegment = 'full';
	                }
	                $price_formated = ($price_currency ? $price_currency . ' ' : '') . 
	                        		   number_format(round($price), 0, '', '\'') . '.&#8211;' .
	                        	       ($propertysegment != 'full' ? ' / ' . substr($propertysegment, 0, -1) . '<sup>2</sup>' : '');
	                $price_formated_timesegment = ($timesegment != 'infinite' ? ' / ' . $timesegment_labels[(string) $timesegment] : '');
            	}


            	$net = get_post_meta( get_the_ID(), 'netPrice', $single = true ); 
            	$net_formated = false;
            	if ($net) {
            		$propertysegment = get_post_meta( get_the_ID(), $key = 'netPrice_propertysegment', $single = true );
	                $timesegment = get_post_meta( get_the_ID(), $key = 'netPrice_timesegment', $single = true );
	                if (!in_array($timesegment, array('m','w','d','y','h','infinite'))) {
	                    $timesegment = 'infinite';
	                }
	                if (!in_array($propertysegment, array('m2','km2','full'))) {
	                    $propertysegment = 'full';
	                }
	                $net_formated = ($price_currency ? $price_currency . ' ' : '') . 
	                        		   number_format(round($net), 0, '', '\'') . '.&#8211;' . 
	                        	       ($propertysegment != 'full' ? '/ ' . substr($propertysegment, 0, -1) . '<sup>2</sup>' : '');
	                $net_formated_timesegment = ($timesegment != 'infinite' ? '/ ' . $timesegment_labels[(string) $timesegment] : '');
            	}
            	$gross = get_post_meta( get_the_ID(), 'grossprice', $single = true ); 
            	$gross_formated = false;
            	if ($gross) {
            		$propertysegment = get_post_meta( get_the_ID(), $key = 'grossprice_propertysegment', $single = true );
	                $timesegment = get_post_meta( get_the_ID(), $key = 'grossprice_timesegment', $single = true );
	                if (!in_array($timesegment, array('m','w','d','y','h','infinite'))) {
	                    $timesegment = 'infinite';
	                }
	                if (!in_array($propertysegment, array('m2','km2','full'))) {
	                    $propertysegment = 'full';
	                }
	                $gross_formated = ($price_currency ? $price_currency . ' ' : '') . 
	                        		   number_format(round($gross), 0, '', '\'') . '.&#8211;' .
	                        	       ($propertysegment != 'full' ? ' / ' . substr($propertysegment, 0, -1) . '<sup>2</sup>' : '');
	                $gross_formated_timesegment = ($timesegment != 'infinite' ? ' / ' . $timesegment_labels[(string) $timesegment] : '');
            	}

				$extra_costs_json =  get_post_meta( get_the_ID(), 'extraPrice', $single = true ); 
				  $extra_costs_arr = array();
					if ($extra_costs_json) {
						$extra_costs_arr = json_decode($extra_costs_json, true);
					}


				function casasync_distance_to_array($distance){
					if ($distance) {
						$distance_quirk = trim($distance,"[");   
						$distance_quirk = trim($distance_quirk,"]");
						$distance_arr = explode(']+[', $distance_quirk);
						return $distance_arr;
					}
					return false;
				}

				$distances = array();
				foreach (casasync_get_allDistanceKeys() as $distance_key) {
					$distance = get_post_meta( get_the_ID(), $distance_key, $single = true );
					$distance_arr = casasync_distance_to_array($distance);
					if ($distance) {
						$title = casasync_convert_distanceKeyToLabel($distance_key);
						$distances[$distance_key] = array('title' => $title, 'value' => implode($distance_arr, ' ' . __('and','casasync') . ' '));
					}
				}

				$urls = array();
				$urls = json_decode(get_post_meta( get_the_ID(), 'casasync_urls', $single = true ), true);
				


				//numeric values
				$numvals = array();
				foreach (casasync_get_allNumvalKeys() as $numval_key) {
					$numval = get_post_meta( get_the_ID(), $numval_key, $single = true );
					if ($numval) {
						$title = casasync_convert_numvalKeyToLabel($numval_key);
						$numvals[$numval_key] = array('title' => $title, 'value' => $numval, 'key' => $numval_key);
					}
				}
				$rooms = (isset($numvals['number_of_rooms']) ? $numvals['number_of_rooms']['value'] : false);
				$surface_living = (isset($numvals['surface_living']) ? $numvals['surface_living']['value'] : false);





				$features = array(); 
				$features_json = get_post_meta( get_the_ID(), 'casasync_features', $single = true );
				if ($features_json) {
					$features = json_decode($features_json, true);
				} else {
					$features = array();	
				}
				


            	$content = get_the_content(); 
            	$content_parts = explode('<hr class="property-separator" />', $content);

            	function getTextBetweenTags($string, $tagname){
				    $d = new DOMDocument();
				    $d->loadHTML($string);
				    $return = array();
				    foreach($d->getElementsByTagName($tagname) as $item){
				        $return[] = $item->textContent;
				    }
				    return (array_key_exists(0, $return) ? $return[0] : '');

				}


				$show_objectdata = true;


    		$own_seller_email = false;
			$emails = array();
			if (!get_option('casasync_seller_email_block')) {
				$email = get_post_meta( get_the_ID(), 'seller_inquiry_person_email', true );
				if ($email) {
					$firstname = get_post_meta( get_the_ID(), 'seller_inquiry_person_givenname', true );
					$lastname = get_post_meta( get_the_ID(), 'seller_inquiry_person_familyname', true );
					$gender    = get_post_meta( get_the_ID(), 'seller_inquiry_person_gender', true);
					if ($gender == 'F') {
						$honorific = 'Frau';
					} elseif ($gender == 'M') {
						$honorific = 'Herr';
					} else {
						$honorific = false;
					}
					$name = ($honorific ? $honorific . ' ' : '') . ($firstname ? $firstname . ' ' : '') . $lastname;
					$emails[] = ($name ? $name : 'Kontaktperson') . ':' . $email;
					$own_seller_email = true;
				}
			}
			if (get_option('casasync_sellerfallback_email_use') != 'never') {
				if (($own_seller_email == false && get_option('casasync_sellerfallback_email_use') == 'fallback') || (get_option('casasync_sellerfallback_email_use') == 'always')) {
					$email = get_option('casasync_sellerfallback_email');
					if ($email) {
						$name = get_option('casasync_sellerfallback_legalname');
						$emails[] = ($name ? $name : 'Hauptanbieter') . ':' . $email;
					}
				}
			}



		 	$own_seller = false;
		 	$has_seller = false;
		 	if (get_option('casasync_seller_show') == 1) {
		 		$seller_address_country = get_post_meta( get_the_ID(), 'seller_org_address_country', $single = true );
				$seller_address_locality = get_post_meta( get_the_ID(), 'seller_org_address_locality', $single = true );
				$seller_address_region = get_post_meta( get_the_ID(), 'seller_org_address_region', $single = true );
				$seller_address_postalcode = get_post_meta( get_the_ID(), 'seller_org_address_postalcode', $single = true );
				$seller_address_street = get_post_meta( get_the_ID(), 'seller_org_address_streetaddress', $single = true );

				$seller_address  = ($seller_address_street ? $seller_address_street . '<br>' : '');
				$seller_address .= ($seller_address_postalcode ?  $seller_address_postalcode . ' ': '') . ($seller_address_locality ? $seller_address_locality : '') . ($seller_address_postalcode || $seller_address_locality ? '<br>' : '');
				$seller_address .= countrycode_to_countryname($seller_address_country); 

				$sellerlegalname = get_post_meta( get_the_ID(), 'seller_org_legalname', $single = true );
				$selleremail = get_post_meta( get_the_ID(), 'seller_org_email', $single = true );
				$sellerfax = get_post_meta( get_the_ID(), 'seller_org_fax', $single = true );
				$sellerphone_direct = get_post_meta( get_the_ID(), 'seller_org_phone_direct', $single = true );
				$sellerphone_central = get_post_meta( get_the_ID(), 'seller_org_phone_central', $single = true );
				$sellerphone_mobile = get_post_meta( get_the_ID(), 'seller_org_phone_mobile', $single = true );
				
				if (
					$seller_address 
					. $sellerlegalname
					. $selleremail
					. $sellerfax
					. $sellerphone_direct
					. $sellerphone_central 
					. $sellerphone_mobile
				) {
					$own_seller = true;
					$has_seller = true;
				}

		 	}
		 	if (get_option('casasync_sellerfallback_show') == 1) {
		 		if (!$has_seller) {
		 			$seller_address_country = get_option('casasync_sellerfallback_address_country');
					$seller_address_locality = get_option('casasync_sellerfallback_address_locality');
					$seller_address_region = get_option('casasync_sellerfallback_address_region');
					$seller_address_postalcode = get_option('casasync_sellerfallback_address_postalcode');
					$seller_address_street = get_option('casasync_sellerfallback_address_street');

					$seller_address  = ($seller_address_street ? $seller_address_street . '<br>' : '');
					$seller_address .= ($seller_address_postalcode ?  $seller_address_postalcode . ' ': '') . ($seller_address_locality ? $seller_address_locality : '') . ($seller_address_postalcode || $seller_address_locality ? '<br>' : '');
					$seller_address .= countrycode_to_countryname($seller_address_country); 

					$sellerlegalname = get_option('casasync_sellerfallback_legalname');
					$selleremail = get_option('casasync_sellerfallback_email');
					$sellerfax = get_option('casasync_sellerfallback_fax');
					$sellerphone_direct = get_option('casasync_sellerfallback_phone_direct');
					$sellerphone_central = get_option('casasync_sellerfallback_phone_central');
					$sellerphone_mobile = get_option('casasync_sellerfallback_phone_mobile');

					if (
						$seller_address 
						. $sellerlegalname
						. $selleremail
						. $sellerfax
						. $sellerphone_direct
						. $sellerphone_central 
						. $sellerphone_mobile
					) {
						$own_seller = false;
						$has_seller = true;
					}
		 		}
		 	}
		 	



		  	$show_salesperson = false;
			  	//if (get_option('casasync_salesperson_show') == 1) {
			  		$salesperson_function        = get_post_meta( get_the_ID(), 'seller_person_function', true);
					$salesperson_givenname       = get_post_meta( get_the_ID(), 'seller_person_givenname', true);
					$salesperson_familyname      = get_post_meta( get_the_ID(), 'seller_person_familyname', true);
					$salesperson_email           = get_post_meta( get_the_ID(), 'seller_person_email', true);
					$salesperson_fax             = get_post_meta( get_the_ID(), 'seller_person_fax', true);
					$salesperson_phone_direct    = get_post_meta( get_the_ID(), 'seller_person_phone_direct', true);
					$salesperson_phone_central   = get_post_meta( get_the_ID(), 'seller_person_phone_central', true);
					$salesperson_phone_mobile    = get_post_meta( get_the_ID(), 'seller_person_phone_mobile', true);
					$salesperson_gender    = get_post_meta( get_the_ID(), 'seller_person_phone_gender', true);
					if ($salesperson_gender == 'F') {
						$honorific = 'Frau';
					} elseif ($salesperson_gender == 'M') {
						$honorific = 'Herr';
					} else {
						$honorific = false;
					}
					if (
						  $salesperson_function      
						. $salesperson_givenname     
						. $salesperson_familyname    
						. $salesperson_email         
						. $salesperson_fax           
						. $salesperson_phone_direct  
						. $salesperson_phone_central 
						. $salesperson_phone_mobile  
						. $salesperson_gender  
					) { 
						$show_salesperson = true;
					}?>




<?php /******************* {title}  *************/ ?>
			<?php $the_title =  get_the_title();?>

<?php /******************* {ids}  *************/ ?>
			<?php $the_ids = 'id="post-' . get_the_ID() . '"';?>

<?php /******************* {classes}  *************/ ?>
			<?php $classes = get_post_class(); ?>
			<?php $the_classes = 'class="' . implode(' ', $classes) . '"'; ?>

<?php /******************* {gallery}  *************/ ?>
			<?php $the_gallery = false; ?>
			<?php if ( $attachments ) { ?>
				<?php ob_start(); ?>
					<div class="casasync-slider-currentimage" id="slider">
						<div class="row-fluid">
							<div class="span12" id="carousel-bounding-box">
								<div id="casasyncCarousel" class="carousel slide">
									<div class="carousel-inner">
										<!-- <nav style="position:absolute; top:50%; margin-top:-30px; height:30px; width:100%;">
						                	<a style="padding:5px; height:20px; background-color:rgba(0,0,0,0.8); border-radius:5px;" class="pull-left" href="#"><i class="icon icon-arrow-left icon-white"></i> Previous</a>
						                	<a style="padding:5px; height:20px; background-color:rgba(0,0,0,0.8); border-radius:5px;" class="pull-right" href="#">Next <i class="icon icon-arrow-right icon-white"></i></a>
						                </nav> -->
										<?php $i = 0; foreach ( $attachments as $attachment ) { $i++; ?>
							                <div class="<?php echo ($i == 1 ? 'active' : '') ?> item" data-slide-number="<?php echo $i-1 ?>">
							                	<?php $thumbimgL = wp_get_attachment_image( $attachment->ID, 'full', true ); ?>
							                	<a href="<?php echo wp_get_attachment_url( $attachment->ID ); ?>" class="casasync-fancybox" data-fancybox-group="casasync-property-images"><?php echo $thumbimgL; ?></a>
							                </div>
										<?php } ?>
									</div>
									<a class="casasync-carousel-left" href="#casasyncCarousel" data-slide="prev"><i>‹</i></a>
		                            <a class="casasync-carousel-right" href="#casasyncCarousel" data-slide="next"><i>›</i></a>

								</div>
							</div>
							<div id="carousel-text" class="carousel-caption" ></div>
							<div id="slide-content">
								<?php  $i = 0; foreach ( $attachments as $attachment ) { $i++; ?>
									<div id="slide-content-<?php echo ($i-1) ?>">
										<h4><?php echo (!is_numeric($attachment->post_title) ? $attachment->post_title : get_the_title()) ?></h4>
										<p><?php echo $attachment->post_excerpt ?></p>
									</div>
								<?php } ?>
							</div>
						</div>
					</div>

					<?php if ($i > 1) { ?>
						<div class="casasync-slider-thumbnails hidden-phone" id="slider-thumbs">
		                    <ul class="row-fluid thumbnail-pane active">
			             	<?php
								$i = 0;
								foreach ( $attachments as $attachment ) {
									$i++;
									$class = "post-attachment thumbnail mime-" . sanitize_title( $attachment->post_mime_type ) . ($i == 1 ? ' active' : '');
									$thumburl = wp_get_attachment_url($attachment->ID);
									$thumbimg = wp_get_attachment_image( $attachment->ID, 'casasync-thumb', true );

									echo '<li class="span3 ' . $class . ' "><a href="'.$thumburl.'" id="carousel-selector-'.($i-1).'">' . $thumbimg . '</a></li>';
									echo ($i % 4 == 0 ? '</ul><ul class="row-fluid thumbnail-pane hidden">' : '');
								} 
							?>
							</ul>
						</div><!-- /thumbnails -->
					<?php } ?>
				<?php $the_gallery = ob_get_contents();ob_end_clean();?>		
			<?php } ?>

<?php /******************* {content}  *************/ ?>
            <?php ob_start();  ?>
	            <ul id="casasync-tabnav" class="nav nav-tabs">
	            		<li class="active">
	            			<a href="#text_basics" data-toggle="tab"><small><?php echo __("Base data", 'casasync') ?></small></a>
	            		</li>
	            	<?php /* ?><?php foreach ($content_parts as $i => $part): ?>
	            		<li>
	            			<?php if (substr_count($part, '<h2>')): ?>
	            				<?php $title = getTextBetweenTags($part, 'h2'); ?>
	            			<?php else: ?>
	            				<?php $title = 'Beschreibung'; ?>
	            			<?php endif ?>
	            			<a href="#text_<?php echo $i+1; ?>" data-toggle="tab">&nbsp;&#9998;&nbsp;<small><?php echo $title; ?></small></a>
	            		</li>
	            	<?php $i++; endforeach ?>
	            	<?php */ ?>
	            	<?php if ($content): ?>
	            		<li>
	            			<a href="#text_description" data-toggle="tab">&nbsp;&#9998;&nbsp;<small><?php echo __('Description', 'casasync') ?></small></a>
	            		</li>
	            	<?php endif ?>
	            		<li>
	            			<a href="#text_numbers" data-toggle="tab"><i class="icon icon-file-alt"></i> <small><?php echo __("Specifications", 'casasync') ?></small></a>
	            		</li>
	            	<?php if ($documents || $plans): ?>
	            		<li>
	            			<a href="#text_documents" data-toggle="tab"><i class="icon icon-file"></i> <small><?php echo __("Plans & Documents", 'casasync') ?></small></a>
	            		</li>
	            	<?php endif ?>
	            		
	            </ul>
	            <div id="casasync-tabcontent" class="tab-content">
	            	<div class="tab-pane fade in active" id="text_basics">
	            		<div class="row-fluid casasync-single-mainitems">
			                <div class="well span4">
			                	<?php $lines = 4; $wellcount = 0; ?>
			                	<h4><?php echo implode(', ', $categories_names) ?></h4>
			                	<?php if ($rooms && $wellcount < $lines): ?>
			                		<?php $wellcount++; ?>
	            					<?php echo __("Rooms:", 'casasync') ?> <?php echo $rooms ?><br>
	            				<?php endif ?>
	            				<?php if ($the_floors_arr && !isset($the_floors_arr[1]) && $wellcount < $lines): ?>
	            					<?php $wellcount++; ?>
	            					<?php echo __("Floor:", 'casasync') ?> <?php echo $the_floors_arr[0]; ?><br>
	            				<?php endif ?>
	            				<?php if ($surface_living && $wellcount < $lines): ?>
	            					<?php $wellcount++; ?>
	            					<?php echo __("Living space:", 'casasync') ?> <?php echo $surface_living ?><sup>2</sup>
	            				<?php endif ?>
	            				<?php if ($wellcount-$lines < 0){
	            					echo str_repeat('<br />&nbsp;', ($wellcount-$lines)*(-1));
	            				}?>
			                </div>
			                <div class="well span4">
			                	<?php $wellcount = 0; ?>
			                	<h4><?php echo __("Address", 'casasync') ?></h4>
			                	<?php if ($address):
			                		echo $address; 	
			                		$wellcount = substr_count($address, '<br>') + 1;
			                	endif ?>
			                	<?php if ($wellcount-$lines < 0){
	            					echo str_repeat('<br />&nbsp;', ($wellcount-$lines)*(-1));
	            				}?>		                
			                </div>
			                <div class="well span4">
			                 	<?php $wellcount = 0; ?>
			                 	<?php echo '<h4>' . implode(', ', $basises) ?></h4>
			                 	<?php if ($gross || $net): ?>
			                 		<?php if ($gross_formated && $wellcount < $lines): ?>
			                 			<?php $wellcount++; ?>
			                			<?php echo $gross_formated; ?> (brutto)<br>
			                 		<?php endif ?>
			                 		<?php if ($net_formated && $wellcount < $lines): ?>
			                 			<?php $wellcount++; ?>
			                			<?php echo $net_formated; ?> (netto)<br>
			                 		<?php endif ?>
			                 	<?php else: ?>
			                 		<?php if ($price_formated && $wellcount < $lines): ?>
			                 			<?php $wellcount++; ?>
			                			<?php echo $price_formated ?><br>
			                 		<?php endif ?>
			                 	<?php endif ?>
			                 	<?php if (!$price && !$net && !$gross): ?>
			                 		Auf Anfrage
			                 	<?php endif ?>
			                 	
			 					<?php if ($wellcount-$lines < 0){
	            					echo str_repeat('<br />&nbsp;', (($wellcount-$lines)*(-1))-1); //-1 odd bug
	            				}?>
			                </div>
		                </div>
		                <?php if ($address): ?>
		                	<?php $map_url = "https://maps.google.com/maps?f=q&amp;source=s_q&amp;hl=" . substr(get_locale(), 0, 2)  . "&amp;geocode=&amp;q=" . urlencode( str_replace('<br>', '+', $address )) . "&amp;aq=&amp;ie=UTF8&amp;hq=&amp;hnear=" . urlencode( str_replace('<br>', '+', $address )) . "&amp;t=m&amp;z=14&amp;output=embed" ?>
		                	<div class="hidden-phone">
		                		<iframe width="100%" height="350" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="<?php echo $map_url ?>"></iframe><br /><small><a href="<?php echo $map_url ?>" class="casasync-fancybox" data-fancybox-type="iframe"><?php echo __('View lager version', 'casasync') ?></a></small>		                
		                	</div>
		                	<div class="visible-phone">
		                		<a class="btn btn-success btn-block" href="<?php echo $map_url ?>"><i class="icon icon-map-marker"></i> Auf Google Maps anzeigen</a>
		                	</div>
		                <?php endif ?>
	            	</div>
	            	<?php /*foreach ($content_parts as $i => $part): ?>
	            		<div class="tab-pane fade" id="text_<?php echo $i+1; ?>">
	            			<?php echo $part; ?>
	            		</div>
	            	<?php $i++; endforeach */?>
	            		<?php if ($content): ?>
	            			<div class="tab-pane fade" id="text_description">
	            				<!-- <h2><?php echo __('Description', 'casasync') ?></h2> -->
	            				<?php echo $content; ?>
	            			</div>
	            		<?php endif ?>
	            		<div class="tab-pane fade" id="text_numbers">
	            				<h3><!-- <i class="icon icon-tags"></i>  --><?php echo __('Offer','casasync'); ?></h3>
		            			<table class="table">

			            			<?php if (!$gross || !$net): ?>
				                 		<?php if ($price_formated): ?>
				                			<tr><td width="25%"><?php echo ($the_basis == 'rent' ? 'Rent price' : __('Sales price', 'casasync')) ?></td><td width="75%"><?php echo $price_formated ?> <?php echo $price_formated_timesegment ?></td></tr>
				                 		<?php endif ?>
				                 	<?php endif ?>
				                 	<?php if ($gross_formated): ?>
			                			<tr><td width="25%"><?php echo ($the_basis == 'rent' ? 'Rent price' : __('Sales price', 'casasync')) ?> (brutto)</td><td width="75%"><?php echo $gross_formated ?> <?php echo $gross_formated_timesegment ?></td></tr>
			                 		<?php endif ?>
			                 		<?php if ($net_formated): ?>
			                			<tr><td width="25%"><?php echo ($the_basis == 'rent' ? 'Rent price' : __('Sales price', 'casasync')) ?> (netto)</td><td width="75%"><?php echo $net_formated ?> <?php echo $net_formated_timesegment ?></td></tr>
			                 		<?php endif ?>
			                 		<?php if (!$gross && !$net && !$price): ?>
			                			<tr><td width="25%"><?php echo ($the_basis == 'rent' ? 'Rent price' : __('Sales price', 'casasync')) ?></td><td width="75%">Auf Anfrage</td></tr>
			                 		<?php endif ?>
		            				<?php if ($extra_costs_arr): ?>
		            					<tr><td><?php echo __('Additional costs','casasync') ?></td><td>
		            					<ul class="unstyled">
		            						<?php foreach ($extra_costs_arr as $extra_cost): ?>
		            							<li>
		            								<?php if($extra_cost['title']) : ?><strong><?php echo $extra_cost['title'] ?>: </strong><?php endif; ?>
		            								<span><?php echo $extra_cost['value'] ?></span>
		            							</li>
		            						<?php endforeach ?>
		            					</ul>

		            				</td></tr>
		            				<?php endif ?>
		            				<?php if ($start): ?>
			                			<tr><td width="25%"><?php echo __('Availability starts','casasync'); ?></td><td width="75%"><?php echo date(get_option('date_format'), strtotime($start)); ?></td></tr>
			                 		<?php endif ?>

		            				
		            			</table>
							<?php if ($address || $the_floors_arr || $numvals || $property_id || $reference_id): ?>
		            			<h3><!-- <i class="icon icon-building"></i>  --><?php echo __('Property','casasync'); ?></h3>
		            			<table class="table">
		            				
		            				<?php if ($reference_id): ?>
		            					<tr><td width="25%"><?php echo __('Reference','casasync') ?></td><td width="75%"><?php echo $reference_id ?></td></tr>
		            				<?php elseif ($property_id): ?>	
		            					<!-- <tr><td width="25%"><?php echo __('Object ID','casasync') ?></td><td width="75%"><?php echo $property_id ?></td></tr> -->
		            				<?php endif ?>
		            				
		            				<tr><td width="25%"><?php echo __('Address','casasync') ?></td><td width="75%"><?php echo $address ?></td></tr>
		            				<?php if ($the_floors_arr): ?>
		            					<tr><td width="25%"><?php echo __('Floor(s)','casasync') ?></td><td width="75%"><?php 
		            						echo "<ul><li>" . implode("</li><li>", $the_floors_arr) . "</li></ul>";
		            					?></td></tr>	
		            				<?php endif ?>
		            				
		            				<?php if ($numvals): ?>
		            					<?php $store = ''; ?>
		            					<?php foreach ($numvals as $numval): ?>
		            						<?php if (in_array($numval['key'], array(
		            							'number_of_apartments',
		            							'number_of_floors',
		            							'floor',
		            							'number_of_rooms',
		            							'number_of_bathrooms',
		            							'room_height'
		            						))): ?>
		            							<tr>
		            								<td width="25%"><?php echo __($numval['title'], 'casasync') ?></td>
													<td width="75%"><?php echo $numval['value'] ?><?php echo (in_array($numval['key'], array('surface_living', 'surface_property')) ? '<sup>2</sup>' : '' ) ?></td>
												</tr>
		            						<?php else: ?>
		            							<?php $store .= '
		            								<tr>
		            									<td width="25%">' . __($numval['title'], 'casasync')  . '</td>
														<td width="75%">' . $numval['value'] .  (in_array($numval['key'], array('surface_living', 'surface_property')) ? '<sup>2</sup>' : '' ) .'</td>
													</tr>
		            							'; ?>
		            						<?php endif ?>
		            					<?php endforeach ?>
		            					<?php //echo '<tr><td colspan="2"></td></tr>' ?>
		            					<?php echo $store; ?>
		            				<?php endif ?>
		            			</table>
		            		<?php endif ?>
		            		<?php if ($features): ?>
		            			<h3><?php echo __('Features','casasync'); ?></h3>
		            				<div class="casasync-features">
            							<?php foreach ($features as $feature){
            								switch ($feature['key']) {
            									case 'wheel-chair-access':
            										echo "<span class='label'><i class='icon icon-ok'></i> " . __('Wheelchair accessible', 'casasync') . ($feature['value'] ? ': ' . $feature['value'] . ' Eingänge' : '') . '</span>';
            										break;
            									case 'animals-alowed':
            										echo "<span class='label'>" . ($feature['value'] ? $feature['value'] . ' ' : '') . __('Pets allowed', 'casasync') . '</span>';
            										break;
            									default:
            										echo "<span class='label'><i class='icon icon-ok'></i> " . ($feature['value'] ? $feature['value'] . ' ' : '') . ' ' . casasync_convert_featureKeyToLabel($feature['key']) . '</span>';
            										break;
            								}
            							} ?>
            						</div>
            				<?php endif ?>
            				<div class="row-fluid">
	            			<?php if ($distances): ?>
	            				<div class="span6">
		            				<h3><?php echo __('Distances','casasync'); ?></h3>
			            			<?php if ($distances): ?>
		            					<ul class="unstyled">
		            					<?php if ($distances): ?>
		            						<?php foreach ($distances as $key => $value): ?>
		            							<li>
		            								<strong><?php echo $value['title'] ?>: </strong>
		            								<span><?php echo $value['value'] ?></span>
		            							</li>
		            						<?php endforeach ?>
		            					<?php endif ?>
		            					</ul>
			            			<?php endif ?>
		            			</div>
		            				
	            			<?php endif ?>

	            			<?php if ($urls): ?>
	            				<div class="span6">
	            					<h3><?php echo __('Links', 'casasync') ?></h3>
	            					<ul class="unstyled">
		            					<?php foreach ($urls as $key => $url): ?>
		            						<li>
		            							<a href="<?php echo $url['href'] ?>" title="<?php echo $url['title'] ?>" target="blank"><?php echo $url['label'] ?></a>
		            						</li>
		            					<?php endforeach ?>
		            				</ul>
	            				</div>
	            			<?php endif ?>
	            			</div>
	            		</div>
	            		<div class="tab-pane fade" id="text_documents">
	            			<?php if ($plans): ?>
	            				<h2><?php echo __('Plans','casasync'); ?></h2>
	            				<ul>
	            				<?php foreach ($plans as $plan): ?>
	            					<?php
	            						$classes = '';
	            						$data = '';
	            						$excerpt = $plan->post_excerpt;
	            						$title = $plan->post_title;
	            						$url = wp_get_attachment_url( $plan->ID );
		            					if (in_array($plan->post_mime_type, array('image/jpeg', 'image/png', 'image/jpg'))) {
		            						$classes = 'casasync-fancybox';
		            						$data = 'data-fancybox-group="casasync-property-plans"';
		            					}
	            					?>
	            					<li><a href="<?php echo $url; ?>" class="<?php echo $classes ?>" title="<?php echo $excerpt; ?>" target="_blank" <?php echo $data ?>><?php echo $title ?></a>
	            						<?php echo ($excerpt ? ': ' . $excerpt : ''); ?>
	            					</li>
	            				<?php endforeach ?>
	            				</ul>
	            			<?php endif ?>
	            			<?php if ($documents): ?>
	            				<h2><?php echo __('Documents','casasync'); ?></h2>
								<ul>
	            				<?php foreach ($documents as $document): ?>
									<?php
	            						$classes = '';
	            						$data = '';
	            						$excerpt = $document->post_excerpt;
	            						$title = $document->post_title;
	            						$url = wp_get_attachment_url( $document->ID );
		            					if (in_array($document->post_mime_type, array('image/jpeg', 'image/png', 'image/jpg'))) {
		            						$classes = 'casasync-fancybox';
		            						$data = 'data-fancybox-group="casasync-property-documents"';
		            					}
	            					?>
	            					<li><a href="<?php echo $url; ?>" class="<?php echo $classes ?>" title="<?php echo $excerpt; ?>" target="_blank" <?php echo $data ?>><?php echo $title ?></a>
	            						<?php echo ($excerpt ? ': ' . $excerpt : ''); ?>
	            					</li>
	            				<?php endforeach ?>
	            				</ul>	            			
	            			<?php endif ?>
	            		</div>
	            	</div>

				<?php $the_content = ob_get_contents();ob_end_clean();?>		

<?php /******************* {cta}  *************/  ?>
				<?php $the_cta = false; ?>
				<?php if ($emails): ?>
					<?php ob_start();?>
			    		<a href="#casasyncPropertyContactForm" id="casasyncKontactAnchor"><i class="icon icon-envelope"></i> <?php echo __('Directly contact the provider now', 'casasync') ?></a>	
					<?php $the_cta = ob_get_contents();ob_end_clean();?>
				<?php endif ?>


<?php /********************** {share} *********/ ?>
				<?php $the_share = false; ?>
					<?php if (get_option( 'casasync_share_facebook', false )): ?>						
						<?php ob_start();?>
						<div class="fb-like" data-send="true" data-layout="button_count" data-href="<?php echo "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>" data-width="200" data-show-faces="true"></div>
						<?php $the_share = ob_get_contents();ob_end_clean();?>
					<?php endif ?>


<?php /******************* {pagination}  *************/  ?>
				<?php $the_pagination = false; ?>
            	<?php ob_start();?>
	            	<nav id="casasyncSinglePaginate" class="casasync-single-paginate btn-group row-fluid">
						<?php //previous_post_link( '%link', '' . _x( '&larr;', 'Previous post link', 'hegglin' ) . '' ); ?>
						<?php echo casasyncSingleNext('casasync-single-left-percent btn'); ?>
						<a class="btn casasync-single-back casasync-single-left-percent" href="<?php echo get_post_type_archive_link( 'casasync_property' );  ?>"><?php echo __('Back to the list','casasync'); ?></a>
						<?php echo casasyncSinglePrev('casasync-single-left-percent btn'); ?>
					</nav><!-- #nav-above -->
				<?php $the_pagination = ob_get_contents();ob_end_clean(); ?>


<?php /******************* {contactform}  *************/ ?>
				<?php $the_contactform = false; ?>
				<?php if ($emails) {?>
					<?php ob_start(); ?>
						<?php echo do_shortcode( '[casasync_contact recipients="' . implode(';', $emails) . '" post_id="' . get_the_ID() . '"]' );?>
					<?php $the_contactform = ob_get_contents();ob_end_clean(); ?>
				<?php }; ?>

<?php  /******************* {seller}  *************/ ?>
				<?php $the_seller = false; ?>
				<?php if ($has_seller): ?>
					<?php ob_start(); ?>
					  		<address>
					  		<?php if ($sellerlegalname): ?><strong><?php echo $sellerlegalname ?></strong><br><?php endif ?>
					  		<?php echo $seller_address ?></address>
					  		<div class="casasync-seller-infos">
					  			<?php if ($selleremail): ?>
					  				<p>
						  				<span class="label"><?php echo __('Email', 'casasync') ?></span>
						  				<?php $objektlink = get_permalink(); ?>
						  				<?php $mailto = 'mailto:' . $salesperson_email . '?subject=Ich%20habe%20eine%20Frage%20bez%C3%BCglich%20dem%20Objekt%3A%20' . get_the_title() . '&body='. rawurlencode(__('I am interested concerning this property. Please contact me.', 'casasync')) . '%0A%0ALink: ' . $objektlink;?>
						  				<span class="value break-word"><a href="<?php echo $mailto ?>"><i class="icon icon-envelope"></i> <?php echo $selleremail ?></a></span>
						  			</p>
					  			<?php endif; ?>
					  			
					  			<?php if ($sellerphone_mobile): ?>
					  				<p>
						  				<span class="label"><?php echo __('Mobile', 'casasync') ?></span>
						  				<span class="value break-word"><i class="icon icon-mobile-phone"></i> <?php echo $sellerphone_mobile ?></span>
						  			</p>
					  			<?php endif; ?>
					  			<?php if ($sellerphone_direct): ?>
					  				<p>
						  				<span class="label"><?php echo __('Phone direct', 'casasync') ?></span>
						  				<span class="value break-word"><i class="icon icon-phone"></i> <?php echo $sellerphone_direct ?></span>
						  			</p>
					  			<?php endif; ?>
					  			<?php if ($sellerphone_central): ?>
					  				<p>
						  				<span class="label"><?php echo __('Phone', 'casasync') ?></span>
						  				<span class="value break-word"><i class="icon icon-phone"></i> <?php echo $sellerphone_central ?></span>
						  			</p>
					  			<?php endif; ?>
					  			<?php if ($sellerfax): ?>
					  				<p>
						  				<span class="label"><?php echo __('Fax', 'casasync') ?></span>
						  				<span class="value break-word"><?php echo $sellerfax ?></span>
						  			</p>
					  			<?php endif; ?>
					  		</div>
					<?php $the_seller = ob_get_contents(); ob_end_clean(); ?>
				<?php endif ?>

<?php /******************* {salesperson}  *************/ ?>
				<?php $the_salesperson = false; ?>
				<?php ob_start(); ?>
				<?php if ($show_salesperson) : ?>
			  			
			  			<p>
				  			<?php if ($salesperson_givenname . $salesperson_familyname): ?>
					  			<?php echo ($honorific ? $honorific . ' ' : '') . ($salesperson_givenname ? $salesperson_givenname . ' ' : '') . $salesperson_familyname; ?>
				  			<?php endif; ?>
				  			<?php if ($salesperson_function): ?>
					  			<br><i><?php echo $salesperson_function ?></i>
					  		<?php endif ?>
				  		</p>
		  				<div class="casasync-salesperson-infos">
		  					<?php if ($salesperson_email): ?>
		  						<p>
					  				<span class="label"><?php echo __('Email', 'casasync') ?></span>
					  				<?php $objektlink = get_permalink(); ?>
					  				<?php $mailto = 'mailto:' . $salesperson_email . '?subject=Ich%20habe%20eine%20Frage%20bez%C3%BCglich%20dem%20Objekt%3A%20' . get_the_title() . '&body=Ich%20interessiere%20mich%20f%C3%BCr%20dieses%20Objekt.%20Bitte%20nehmen%20Sie%20Kontakt%20mit%20mir%20auf.' . '%0A%0ALink: ' . $objektlink;?>
					  				<span class="value break-word"><a href="<?php echo $mailto ?>"><i class="icon icon-envelope"></i> <?php echo $salesperson_email ?></a></span>
					  			</p>
			  				<?php endif; ?>
				  			<?php if ($salesperson_phone_mobile): ?>
				  				<p>
					  				<span class="label"><?php echo __('Mobile', 'casasync') ?></span>
					  				<span class="value break-word"><i class="icon icon-mobile-phone"></i> <?php echo $salesperson_phone_mobile ?></span>
					  			</p>
					  		<?php endif; ?>
				  			<?php if ($salesperson_phone_direct): ?>
				  				<p>
					  				<span class="label"><?php echo __('Phone direct', 'casasync') ?></span>
					  				<span class="value break-word"><i class="icon icon-phone"></i> <?php echo $salesperson_phone_direct ?></span>
					  			</p>
				  			<?php endif; ?>
				  			<?php if ($salesperson_phone_central): ?>
				  				<p>
					  				<span class="label"><?php echo __('Phone', 'casasync') ?></span>
					  				<span class="value break-word"><i class="icon icon-phone"></i> <?php echo $salesperson_phone_central ?></span>
					  			</p>
				  			<?php endif; ?>
				  			<?php if ($salesperson_fax): ?>
				  				<p>
					  				<span class="label"><?php echo __('Fax', 'casasync') ?></span>
					  				<span class="value break-word"><?php echo $salesperson_fax ?></span>
					  			</p>
				  			<?php endif; ?>
		  				</div>
				<?php endif; ?>
				<?php $the_salesperson = ob_get_contents(); ob_end_clean(); ?>

		
				<?php $template = casasync_get_single_template(); ?>
				<?php $template = casasync_interpret_gettext($template); ?>
				<?php 
					$template = str_replace('{ids}', $the_ids, $template);
					$template = str_replace('{classes}', $the_classes, $template);
					$template = str_replace('{title}', $the_title, $template);
					$template = str_replace('{gallery}', $the_gallery, $template);
					$template = str_replace('{content}', $the_content, $template);
					$template = str_replace('{pagination}', $the_pagination, $template);

					function get_string_between($string, $start, $end){
						$string = " ".$string;
						$pos = strpos($string,$start);
						if ($pos == 0) return "";
							$pos += strlen($start);
							$len = strpos($string,$end,$pos) - $pos;
						return substr($string,$pos,$len);
					}



					if ($the_cta) {
						$before_cta = get_string_between($template, "{if_cta}", "{cta}");
						$after_cta = get_string_between($template, "{cta}", "{end_if_cta}");
						$template = str_replace($before_cta.'{cta}'.$after_cta, $before_cta . $the_cta . $after_cta, $template);
						$template = str_replace('{if_cta}', '', $template);
						$template = str_replace('{end_if_cta}', '', $template);
					} else {
						$template_cta_rm = get_string_between($template, "{if_cta}", "{end_if_cta}");
						$template = str_replace("{if_cta}" . $template_cta_rm . "{end_if_cta}", '', $template);
						$template = str_replace("{cta}", '', $template);
					}

					if ($the_share) {
						$before_share = get_string_between($template, "{if_share}", "{share}");
						$after_share = get_string_between($template, "{share}", "{end_if_share}");
						$template = str_replace($before_share.'{share}'.$after_share, $before_share . $the_share . $after_share, $template);
						$template = str_replace('{if_share}', '', $template);
						$template = str_replace('{end_if_share}', '', $template);
					} else {
						$template_share_rm = get_string_between($template, "{if_share}", "{end_if_share}");
						$template = str_replace("{if_share}" . $template_share_rm . "{end_if_share}", '', $template);
						$template = str_replace("{share}", '', $template);
					}


					if ($the_contactform) {
						$before_contactform = get_string_between($template, "{if_contactform}", "{contactform}");
						$after_contactform = get_string_between($template, "{contactform}", "{end_if_contactform}");
						$template = str_replace($before_contactform.'{contactform}'.$after_contactform, $before_contactform . $the_contactform . $after_contactform, $template);
						$template = str_replace('{if_contactform}', '', $template);
						$template = str_replace('{end_if_contactform}', '', $template);
					} else {
						$template_contactform_rm = get_string_between($template, "{if_contactform}", "{end_if_contactform}");
						$template = str_replace("{if_contactform}" . $template_contactform_rm . "{end_if_contactform}", '', $template);
						$template = str_replace("{contactform}", '', $template);
					}


					if ($the_seller) {
						$before_seller = get_string_between($template, "{if_seller}", "{seller}");
						$after_seller = get_string_between($template, "{seller}", "{end_if_seller}");
						$template = str_replace($before_seller.'{seller}'.$after_seller, $before_seller . $the_seller . $after_seller, $template);
						$template = str_replace('{if_seller}', '', $template);
						$template = str_replace('{end_if_seller}', '', $template);
					} else {
						$template_seller_rm = get_string_between($template, "{if_seller}", "{end_if_seller}");
						$template = str_replace('{if_seller}' . $template_seller_rm . '{end_if_seller}', '', $template);
						$template = str_replace("{seller}", '', $template);
					}


					if ($the_salesperson) {
						$before_salesperson = get_string_between($template, "{if_salesperson}", "{salesperson}");
						$after_salesperson = get_string_between($template, "{salesperson}", "{end_if_salesperson}");
						$template = str_replace($before_salesperson.'{salesperson}'.$after_salesperson, $before_salesperson . $the_salesperson . $after_salesperson, $template);
						$template = str_replace('{if_salesperson}', '', $template);
						$template = str_replace('{end_if_salesperson}', '', $template);
					} else {
						$template_salesperson_rm = get_string_between($template, "{if_salesperson}", "{end_if_salesperson}");
						$template = str_replace('{if_salesperson}' . $template_salesperson_rm . '{end_if_salesperson}', '', $template);
						$template = str_replace("{salesperson}", '', $template);
					}

					
					echo $template


				 ?>



	<?php endwhile; ?>
<?php wp_reset_query(); ?>
<?php get_footer(); ?>