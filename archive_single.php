<?php 
	$address_postalcode = get_post_meta( get_the_ID(), 'casasync_property_address_postalcode', $single = true );
	$address_locality = get_post_meta( get_the_ID(), 'casasync_property_address_locality', $single = true );
	$address_region = get_post_meta( get_the_ID(), 'casasync_property_address_region', $single = true );
	$address_country = get_post_meta( get_the_ID(), 'casasync_property_address_country', $single = true );
	$categories = wp_get_post_terms( get_the_ID(), 'casasync_category'); 
    $basis = wp_get_post_terms( get_the_ID(), 'casasync_salestype'); 
    $price_currency = get_post_meta( get_the_ID(), 'price_currency', $single = true );
 	$locations = wp_get_post_terms( get_the_ID(), 'casasync_location');
 	$visitInformation = get_post_meta( get_the_ID(), 'casasync_visitInformation', $single = true ); 

	$timesegment_labels = array(
        'm' => __('monthly', 'casasync'),
        'w' => __('weekly', 'casasync'),
        'd' => __('daily', 'casasync'),
        'y' => __('yearly', 'casasync'),
        'h' => __('hourly', 'casasync')
    );


    $price = get_post_meta( get_the_ID(), $key = 'price', $single = true );
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
            	       ($propertysegment != 'full' ? '/' . substr($propertysegment, 0, -1) . '<sup>2</sup>' : '') .
                       ($timesegment != 'infinite' ? ' ' . $timesegment_labels[(string) $timesegment] : '');



    $net = get_post_meta( get_the_ID(), $key = 'netPrice', $single = true );
    $propertysegment = get_post_meta( get_the_ID(), $key = 'netprice_propertysegment', $single = true );
    $timesegment = get_post_meta( get_the_ID(), $key = 'netprice_timesegment', $single = true );
    if (!in_array($timesegment, array('m','w','d','y','h','infinite'))) {
        $timesegment = 'infinite';
    }
    if (!in_array($propertysegment, array('m2','km2','full'))) {
        $propertysegment = 'full';
    }
    $net_formated = ($price_currency ? $price_currency . ' ' : '') . 
            		   number_format(round($net), 0, '', '\'') . '.&#8211;' .
            	       ($propertysegment != 'full' ? '/' . substr($propertysegment, 0, -1) . '<sup>2</sup>' : '') .
                       ($timesegment != 'infinite' ? ' ' . $timesegment_labels[(string) $timesegment] : '');


    $gross = get_post_meta( get_the_ID(), $key = 'grossPrice', $single = true );
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
            	       ($propertysegment != 'full' ? '/' . substr($propertysegment, 0, -1) . '<sup>2</sup>' : '') .
                       ($timesegment != 'infinite' ? ' ' . $timesegment_labels[(string) $timesegment] : '');

    $basis = wp_get_post_terms( get_the_ID(), 'casasync_salestype'); 
 		$basises = array();
 		$basis_slugs = array();
 		foreach ($basis as $basi) {
 			$basises[] = $basi->name;
 			$basis_slugs[] = $basi->slug;
 		} 
 	$basis_slugs_str = '';
 	if ($basis_slugs) {
 		$the_basis = $basis_slugs[0];
 		foreach ($basis_slugs as $basis_slug) {
 			$basis_slugs_str = ' salestype_' . $basis_slug;
 		}
 	} else {
 		$the_basis = 'buy';
 	}
 ?>

 	<?php 
 		$availability = get_post_meta( get_the_ID(), 'availability', $single = true );
		$availability_label = get_post_meta( get_the_ID(), 'availability_label', $single = true );
		if (!$availability_label) {
			switch ($availability) {
				case 'available':
					$availability_label = 'Verfügbar';
					break;
				case 'reserved':
					$availability_label = 'Reserved';
					break;
				case 'planned':
					$availability_label = 'In Planung';
					break;
				case 'under-construction':
					$availability_label = 'Im Bau';
					break;
				case 'reference':
					$availability_label = 'Referenz';
					break;
				
				default:
					$availability_label = false;
					break;
			}
		}
	 ?>



<?php /******************* {title}  *************/ ?>
			<?php $the_title =  get_the_title();?>

<?php /******************* {ids}  *************/ ?>
			<?php $the_ids = 'id="post-' . get_the_ID() . '"';?>

<?php /******************* {classes}  *************/ ?>
			<?php $classes = get_post_class(); ?>
			<?php $the_classes = 'class="' . implode(' ', $classes) . '' . $basis_slugs_str . '"'; ?>

<?php /******************* {permalink}  *************/ ?>
			<?php $the_permalink = get_permalink(); ?>

<?php /******************* {thumbnail}  *************/ ?>
			<?php $the_thumbnail = false; ?>
			<?php $thumbnail = get_the_post_thumbnail(get_the_ID(), 'full'); ?>
			<?php if ( $thumbnail ) { ?>
				<?php $the_thumbnail = $thumbnail; ?>		
			<?php } ?>

<?php /******************* {availabilitylabel}  *************/ ?>
	<?php 
		$the_availabilitylabel = false;
		if ($availability_label):
			$the_availabilitylabel = '<div class="availability-label availability-label-' . $availability . '">' . $availability_label . '</div>';
	 	endif;
	 ?>


<?php /******************* {datatable}  *************/ ?>
			<?php ob_start(); ?>
				<table class="table">
					<tbody>
						<?php if ($address_postalcode . $address_locality . $address_region . $address_country ): ?>
							<tr>
								<th><?php echo __('Locality', 'casasync') ?></th>
								<td>
									<?php echo 
										($address_postalcode ? $address_postalcode . ' ': '') . 
										($address_locality ? $address_locality: '') . 
											($address_locality && $address_region ? ', ' : '') .
										($address_region? $address_region: '') . 
										($address_country ? ' (' . $address_country . ')' : ''); ?>
								</td>
							</tr>
							
						<?php endif ?>

						<?php if ($locations && 1 == 0): ?>
							<tr>
								<th>Ort</th>
								<td><?php 
									if ($locations) {
										foreach ($locations as $key => $location) {
											echo ($key != 0 ? ', ' : ' ') . $location->name;
										}
									}
								 ?></td>
							</tr>
						<?php endif ?>

						<?php $rooms = get_post_meta( get_the_ID(), 'number_of_rooms', $single = true ); ?>
        				<?php if ($rooms): ?>
        					<tr>
								<th><?php echo __('Rooms', 'casasync') ?></th>
								<td><?php echo $rooms ?></td>
							</tr>
        				<?php endif ?>
						<?php $surface_living = get_post_meta( get_the_ID(), 'surface_living', $single = true ); ?>
						<?php if ($surface_living) : ?>
							<tr>
								<th><?php echo __('Living space','casasync'); ?></th>
								<td><?php echo $surface_living ?><sup>2</sup></td>
							</tr>
						<?php endif; ?>
						
						
						<?php //if ($price || $net || $gross): ?>
						<tr>
							<th><?php echo ($the_basis == 'rent' ? __('Rent price','casasync') : __('Sales price','casasync')) ?></th>
							<td>
								
				                 <?php if ($price): ?>
				                	<?php echo $price_formated ?>
				                <?php elseif ($net): ?>
				                	<?php echo $net_formated . ' (netto)' ?>
				                <?php elseif ($gross): ?>
				                	<?php echo $gross_formated . ' (brutto)' ?>
				                 <?php else: ?>
				                	Auf Anfrage
				                <?php endif ?>

							</td>
						</tr>		
						<?php //endif ?>
						
						<?php if ($visitInformation): ?>
						<tr>
							<th>Besichtigung</th>
							<td>
								<?php echo $visitInformation; ?>
							</td>
						</tr>
						<?php endif ?>					
					</tbody>
				</table>
			<?php $the_datatable = ob_get_contents();ob_end_clean();?>	


<?php /******************* {quicktags}  *************/ ?>
			<?php ob_start(); ?>
				<?php 
             		foreach ($categories as $category) {
             			echo '<span class="label label-info">' . casasync_convert_categoryKeyToLabel($category->name) . '</span> ';
             		}
             	?>
             	<?php 
             		foreach ($basis as $basi) {
             			echo '<span class="label">' . __(ucfirst($basi->name), 'casasync') . '</span>';
             		}
				?>
				<!-- <span class="label"><i class="icon-calendar icon-white"></i> <?php echo get_the_date(); ?></span>  -->
				<?php 
					$attachments = get_children( array( 'post_parent' => get_the_ID(), 'casasync_attachment_type' => 'image' ) );
					$count = count( $attachments );
				?>
				<?php if ($count): ?>
					<span class="label"><i class="icon-picture icon-white"></i> <?php echo $count ?></span> 
				<?php endif ?>
				<?php 
					$attachments = get_children( array( 'post_parent' => get_the_ID(), 'casasync_attachment_type' => 'document' ) );
					$count = count( $attachments );
				?>
				<?php if ($count): ?>
					<span class="label"><i class="icon-file icon-white"></i> <?php echo $count ?></span> 
				<?php endif ?>
				<?php 
					$attachments = get_children( array( 'post_parent' => get_the_ID(), 'casasync_attachment_type' => 'plan' ) );
					$count = count( $attachments );
				?>
				<?php if ($count): ?>
					<span class="label"><i class="icon-flag icon-white"></i> <?php echo $count ?></span> 
				<?php endif ?>
				
				<?php if ($availability && $availability_label && $availability == 'reserved'): ?>
					<span class="label label-important"><i class="icon-warning-sign icon-white"></i> <?php echo $availability_label  ?></span>
				<?php endif ?>
			<?php $the_quicktags = ob_get_contents();ob_end_clean();?>		



	 	<?php if (isset($archive_single_view) && $archive_single_view == 'featured') : ?>
	 		<article <?php echo $the_ids ?> <?php post_class('casasync-featured'); ?>>
				<?php if($the_thumbnail): ?>
					<div class="archive-property-thumbnail">
						<?php echo $the_thumbnail; ?>
					</div>
				<?php endif; ?>
				<div class="row-fluid">
					<div class="span12 archive-property-content">
						<h2 class="entry-title"><a href="<?php echo $the_permalink ?>" title="<?php echo $the_title ?>" rel="bookmark"><?php echo $the_title; ?></a></h2>
						<div class="entry-meta">
							<?php echo $the_datatable; ?>
						</div>
						<?php if ($the_basis == 'reference'): ?>
							
						<?php else: ?>
							<div class="entry-summary"><a href="<?php echo $the_permalink; ?>" class="btn btn-primary pull-right"><?php echo __('Details', 'casasync') ?> <i class="icon icon-arrow-right icon-white"></i></a></div>
						<?php endif ?>
						
					</div>
				</div>
			</article>
		<?php else: ?>


 		<?php $template = casasync_get_archive_single_template(); ?>
 		<?php $template = casasync_interpret_gettext($template); ?>
		<?php 

			$template = str_replace('{ids}', $the_ids, $template);
			$template = str_replace('{classes}', $the_classes, $template);
			
			$template = str_replace('{title}', $the_title, $template);
			$template = str_replace('{datatable}', $the_datatable, $template);
			$template = str_replace('{quicktags}', $the_quicktags, $template);
			$template = str_replace('{locality}', $address_locality, $template);
			$template = str_replace('{country}', $address_country, $template);

			$template = casasync_template_set_if($template, 'thumbnail', $the_thumbnail);
			$template = casasync_template_set_if_not($template, 'thumbnail', $the_thumbnail);

			$template = casasync_template_set_if($template, 'availabilitylabel', $the_availabilitylabel);
			$template = casasync_template_set_if_not($template, 'availabilitylabel', $the_availabilitylabel);

			$template = casasync_template_set_if($template, 'permalink', $the_permalink);
			$template = casasync_template_set_if_not($template, 'permalink', $the_permalink);

			$template = casasync_template_set_if_not($template, 'planned', ($the_basis == 'planned' ? true : false));
			$template = casasync_template_set_if($template, 'planned', ($the_basis == 'planned' ? false : true));

			$template = casasync_template_set_if_not($template, 'reference', ($the_basis == 'reference' ? true : false));
			$template = casasync_template_set_if($template, 'reference', ($the_basis == 'reference' ? false : true));

			$template = str_replace('{permalink}', $the_permalink, $template);


			echo $template

		 ?>
	<?php endif ?>