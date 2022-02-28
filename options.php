<?php
	if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

	if(isset($_POST['casawp_submit'])) {
		$saved_custom_categories = array();
		foreach ($_POST AS $key => $value) {
			if ($key == 'casawp_before_content' || $key == 'casawp_after_content') {
				$allowed_html = array(
					'div' => array(
						'id' => array(),
						'class' => array()
					)
				);
				$value = wp_kses( $value, $allowed_html );
			} else {
				$value = sanitize_text_field($value);
			}
			if (substr($key, 0, 7) == 'custom_') {
				$parts = explode('/', $key); //firstpart [0] is slug the second [1] is either the langcode or if it should be shown
				$saved_custom_categories[$parts[0]][$parts[1]] = $value;
        // array('custom_bauland' => array(
        //  'show' => 1,
        //  'de' => 'Deutscher text'
        // ))
				// if (!array_key_exists('show', $saved_custom_categories[$parts[0]])) {
				// 	$saved_custom_categories[$parts[0]]['show'] = '0';
				// }
			}
			if (substr($key, 0, 6) == 'casawp') {
				update_option( $key, $value );
			}
		}

		if (count($saved_custom_categories) > 0) {

      //set empty shows to 0
      foreach ($saved_custom_categories as $key => $cat) {
        if (!array_key_exists('show', $cat)) {
					$saved_custom_categories[$key]['show'] = '0';
				}
      }
			update_option('casawp_custom_category_translations', $saved_custom_categories);
		}

		$current = isset($_GET['tab']) ? $_GET['tab'] : 'general';
		switch ($current) {
			case 'appearance':
				$checkbox_traps = array(
					'casawp_load_css',
					'casawp_load_scripts',
					'casawp_load_bootstrap_scripts',
					//'casawp_load_fancybox',
					'casawp_load_featherlight',
					'casawp_load_chosen'
				);
				break;
			case 'singleview':
				$checkbox_traps = array(
					'casawp_share_facebook',
					#'casawp_share_googleplus',
					#'casawp_share_twitter',
					'casawp_single_show_number_of_rooms',
					#'casawp_single_show_surface_usable',
					#'casawp_single_show_surface_living',
					'casawp_single_show_area_bwf',
					'casawp_single_show_area_sia_nf',
					'casawp_single_show_surface_property',
					'casawp_single_show_floor',
					'casawp_single_show_number_of_floors',
					'casawp_single_show_year_built',
					'casawp_single_show_year_renovated',
					'casawp_single_show_carousel_indicators',
					'casawp_single_show_availability',
					'casawp_sellerfallback_show_organization',
					'casawp_sellerfallback_show_person_view',
					'casawp_load_googlemaps',
					'casawp_casadistance_active',
					'casawp_casadistance_basecss',
					'casawp_load_maps_immediately'
				);
				break;
			case 'archiveview':
				$checkbox_traps = array(
					'casawp_show_sticky_properties',
					'casawp_hide_sticky_properties_in_main',
					'casawp_archive_show_street_and_number',
					'casawp_archive_show_zip',
					'casawp_archive_show_location',
					'casawp_archive_show_number_of_rooms',
					#'casawp_single_show_surface_usable',
					#'casawp_single_show_surface_living',
					'casawp_archive_show_area_bwf',
					'casawp_archive_show_area_nwf',
					'casawp_archive_show_area_sia_nf',
					'casawp_archive_show_area_sia_gf',
					'casawp_archive_show_surface_property',
					'casawp_archive_show_floor',
					'casawp_archive_show_number_of_floors',
					'casawp_archive_show_year_built',
					'casawp_archive_show_year_renovated',
					'casawp_archive_show_price',
					'casawp_archive_show_excerpt',
					'casawp_archive_show_availability',
					'casawp_archive_show_thumbnail_size_crop',
					'casawp_prefer_extracost_segmentation',
					'casawp_filter_hide',
					'casawp_ajaxify_archive'
				);
				break;
			case 'contactform':
				$checkbox_traps = array(
					'casawp_show_email_organisation',
					'casawp_show_email_person_view',
					'casawp_form_firstname_required',
					'casawp_form_lastname_required',
					'casawp_form_street_required',
					'casawp_form_postalcode_required',
					'casawp_form_locality_required',
					'casawp_form_phone_required',
					'casawp_form_email_required',
					'casawp_form_message_required',
					'casawp_casamail_direct_recipient',
					'casawp_form_gender_neutral'
				);
				break;
			case 'general':
			default:
				$checkbox_traps = array(
					'casawp_use_casagateway_cdn',
					'casawp_limit_reference_images',
					'casawp_permanently_delete_properties',
					'casawp_auto_translate_properties',
					'casawp_custom_slug',
					'casawp_live_import',
					'casawp_sellerfallback_email_use',
					'casawp_remCat',
					'casawp_remCat_email',
					'casawp_before_content',
					'casawp_after_content',
					'casawp_legacy'
				);
				break;
		}

		//reset
		if(get_option('casawp_request_per_remcat') == false) {
			update_option('casawp_remCat_email', '');
		}
		if(get_option('casawp_request_per_mail_fallback') == false) {
			update_option('casawp_request_per_mail_fallback_value', '');
		}

		foreach ($checkbox_traps as $trap) {
			if (!isset($_POST[$trap])) {
				update_option( $trap, '0' );
			}
		}
		echo '<div class="updated"><p><strong>' . __('Einstellungen gespeichert..', 'casawp' ) . '</strong></p></div>';
	}


	if (isset($_GET['do_import']) && !isset($_POST['casawp_submit'])) {
		if (get_option( 'casawp_live_import') == 0) {
			?> <div class="updated"><p><strong><?php _e('Daten wurden importiert..', 'casawp' ); ?></strong></p></div> <?php
		}
	}
?>


<div class="wrap">
	<h1><strong>CASA</strong><span style="font-weight:100">WP</span></h1>
	<?php
		// Tabs
		$tabs = array(
			'general'     => 'Allgemein',
			'archiveview' => 'Archiv',
			'singleview'  => 'Detail',
			'private'     => 'Geschützte Objekte',
			'contactform' => 'Kontaktformular',
			'appearance'  => 'Skripte',
			'logs' => 'Logs'
		);
	    echo '<h2 class="nav-tab-wrapper">';
	    echo '<div style="float:right;">
	        <a href="http://wordpress.org/support/view/plugin-reviews/casawp" target="_blank" class="add-new-h2">Rate this plugin</a>
	        <a href="http://wordpress.org/plugins/casawp/changelog/" target="_blank" class="add-new-h2">Changelog</a>
	    </div>';
	    $current = isset($_GET['tab']) ? $_GET['tab'] : 'general';
	    foreach( $tabs as $tab => $name ){
	        $class = ( $tab == $current ) ? ' nav-tab-active' : '';
	        echo "<a class='nav-tab$class' href='?page=casawp&tab=$tab'>$name</a>";

	    }
	    echo '</h2>';
	?>


	<form action="" method="post" id="options_form" name="options_form">
		<?php
			$table_start = '<table class="form-table"><tbody>';
			$table_end   = '</tbody></table>';
			switch ($current) {
				case 'appearance':
					?>
					<?php /******* Appearance *******/ ?>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">Bootstrap</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>Template</span></legend>
										<?php $name = 'casawp_viewgroup'; ?>
										<?php $text = 'Darstellungs-Template auswählen'; ?>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="bootstrap4" <?php echo (get_option($name) == 'bootstrap4' ? 'checked="checked"' : ''); ?>> Version 4
										</label>
										<br>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="bootstrap3" <?php echo (get_option($name) == 'bootstrap3' ? 'checked="checked"' : ''); ?>> Version 3
										</label>
										<br>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="bootstrap2" <?php echo (get_option($name) == 'bootstrap2' ? 'checked="checked"' : ''); ?>> Version 2
										</label>
										<br>
									</fieldset>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">CSS</th>
								<td class="front-static-pages">
									<fieldset>
										<?php $name = 'casawp_load_css'; ?>
										<?php $text = 'CASAWP CSS laden'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">JavaScript</th>
								<td class="front-static-pages">
									<fieldset>
										<?php $name = 'casawp_load_scripts'; ?>
										<?php $text = 'CASAWP JS laden'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<?php /* ?>
										<?php $name = 'casawp_load_fancybox'; ?>
										<?php $text = 'Fancybox'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<php */ ?>
										<?php $name = 'casawp_load_featherlight'; ?>
										<?php $text = 'Featherlight laden'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<?php $name = 'casawp_load_chosen'; ?>
										<?php $text = 'jQuery Harvest chosen laden'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
					<?php
					break;
				case 'singleview':
					?>
						<?php /******* Single View *******/ ?>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">Social Media</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>Folgende Social Media Plattformen anzeigen</span></legend>
										<?php $name = 'casawp_share_facebook'; ?>
										<?php $text = 'Facebook Button "Teilen" anzeigen'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<?php #$name = 'casawp_share_googleplus'; ?>
										<?php #$text = 'Google+'; ?>
										<?php /*<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br> */ ?>
										<?php #$name = 'casawp_share_twitter'; ?>
										<?php #$text = 'Twitter'; ?>
										<?php /*<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label> */ ?>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php echo $table_start; ?>
							<tr valign="top" style="opacity: 0; position: absolute; left: -9999px; top: -9999px; max-height: 0; overflow: hidden;">
								<th scope="row">Welche Werte sollen angezeigt werden? Das 2. Feld bestimmt die Ordnung der Darstellung.</th>
								<td id="front-static-padges">
									<fieldset>
										<legend class="screen-reader-text"><span>Welche Werte sollen angezeigt werden? Das 2. Feld bestimmt die Ordnung der Darstellung.</span></legend>
										<?php $name = 'casawp_single_show_number_of_rooms'; ?>
										<?php $text = 'Anzahl Zimmer'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_single_show_number_of_rooms_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_single_show_area_sia_nf'; ?>
										<?php $text = 'Nutzfläche'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_single_show_area_sia_nf_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_single_show_area_bwf'; ?>
										<?php $text = 'Wohnfläche'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_single_show_area_bwf_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_single_show_surface_property'; ?>
										<?php $text = 'Grundstücksfläche'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_single_show_surface_property_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_single_show_floor'; ?>
										<?php $text = 'Etage'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_single_show_floor_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_single_show_number_of_floors'; ?>
										<?php $text = 'Anzahl Etage'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_single_show_number_of_floors_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_single_show_year_built'; ?>
										<?php $text = 'Baujahr'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_single_show_year_built_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_single_show_year_renovated'; ?>
										<?php $text = 'Letzte Renovation'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_single_show_year_renovated_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_single_show_availability'; ?>
										<?php $text = 'Verfügbarkeit'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_single_show_availability_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">Google Maps</th>
								<td class="front-static-pages">
									<?php $name = 'casawp_load_googlemaps'; ?>
									<?php $text = 'Karte aktivieren'; ?>
									<label>
										<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
									</label>
									<br />
									<?php $name = 'casawp_casadistance_active'; ?>
									<?php $text = 'Distanzen anzeigen'; ?>
									<label>
										<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
									</label>
									<br>
									<?php $name = 'casawp_casadistance_basecss'; ?>
									<?php $text = 'Distanzen CSS aktivieren'; ?>
									<label>
										<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
									</label>
									<br>
									<?php $name = 'casawp_load_maps_immediately'; ?>
									<?php $text = 'Karte sofort laden'; ?>
									<label>
										<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
									</label>
									<br>
									<?php $name = 'casawp_google_apikey'; ?>
									<?php $text = 'Maps JavaScript API'; ?>
									<label class="block-label block-label--intd" for="<?php echo $name; ?>"><?php echo $text ?></label>
									<input name="<?php echo $name ?>" id="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text">
									
									<?php $name = 'casawp_single_use_zoomlevel'; ?>
									<?php $text = 'Zoom'; ?>
									<label class="block-label block-label--intd" for="<?php echo $name; ?>"><?php echo $text ?></label>
									<select name="<?php echo $name ?>" id="<?php echo $name ?>">
										<option <?php echo (get_option($name)  == '0' ? 'selected="selected"' : ''); ?> value="0">0</option>
										<option <?php echo (get_option($name)  == '1' ? 'selected="selected"' : ''); ?> value="1">1</option>
										<option <?php echo (get_option($name)  == '2' ? 'selected="selected"' : ''); ?> value="2">2</option>
										<option <?php echo (get_option($name)  == '3' ? 'selected="selected"' : ''); ?> value="3">3</option>
										<option <?php echo (get_option($name)  == '4' ? 'selected="selected"' : ''); ?> value="4">4</option>
										<option <?php echo (get_option($name)  == '5' ? 'selected="selected"' : ''); ?> value="5">5</option>
										<option <?php echo (get_option($name)  == '6' ? 'selected="selected"' : ''); ?> value="6">6</option>
										<option <?php echo (get_option($name)  == '7' ? 'selected="selected"' : ''); ?> value="7">7</option>
										<option <?php echo (get_option($name)  == '8' ? 'selected="selected"' : ''); ?> value="8">8</option>
										<option <?php echo (get_option($name)  == '9' ? 'selected="selected"' : ''); ?> value="9">9</option>
										<option <?php echo (get_option($name) == '10' ? 'selected="selected"' : ''); ?> value="10">10</option>
										<option <?php echo (get_option($name) == '11' ? 'selected="selected"' : ''); ?> value="11">11</option>
										<option <?php echo (get_option($name) == '12' ? 'selected="selected"' : ''); ?> value="12">12</option>
										<option <?php echo (get_option($name) == '13' ? 'selected="selected"' : ''); ?> value="13">13</option>
										<option <?php echo (get_option($name) == '14' ? 'selected="selected"' : ''); ?> value="14">14</option>
										<option <?php echo (get_option($name) == '15' ? 'selected="selected"' : ''); ?> value="15">15</option>
										<option <?php echo (get_option($name) == '16' ? 'selected="selected"' : ''); ?> value="16">16</option>
										<option <?php echo (get_option($name) == '17' ? 'selected="selected"' : ''); ?> value="17">17</option>
										<option <?php echo (get_option($name) == '18' ? 'selected="selected"' : ''); ?> value="18">18</option>
										<option <?php echo (get_option($name) == '19' ? 'selected="selected"' : ''); ?> value="19">19</option>
									</select>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php echo $table_start; ?>
							<tr valign="top" style="opacity: 0; position: absolute; left: -9999px; top: -9999px; max-height: 0; overflow: hidden;">
								<th scope="row">Galerienavigation</th>
								<td id="front-static-padges">
									<fieldset>
										<legend class="screen-reader-text"><span>Galerienavigation</span></legend>
										<?php $name = 'casawp_single_show_carousel_indicators'; ?>
										<?php $text = 'Navigation mit Kreisen'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
							<tr valign="top" style="opacity: 0; position: absolute; left: -9999px; top: -9999px; max-height: 0; overflow: hidden;">
								<th scope="row">Thumbnails</th>
								<td id="front-static-padges">
									<fieldset>
										<legend class="screen-reader-text"><span>Thumbnails</span></legend>
										<?php $name = 'casawp_single_max_thumbnails'; ?>
										<?php $text = 'Maximale Anzahl Thumbnails'; ?>
										<label>
											<input name="<?php echo $name ?>" type="number" value="<?php echo get_option($name); ?>" class="tog small-text"> <?php echo $text ?>
										</label>
									</fieldset>
									<fieldset>
										<legend class="screen-reader-text"><span>Thumbnails</span></legend>
										<?php $name = 'casawp_single_thumbnail_ideal_width'; ?>
										<?php $text = 'Ideale Breite der Thumbnails'; ?>
										<label>
											<input name="<?php echo $name ?>" type="number" value="<?php echo get_option($name); ?>" class="tog small-text"> <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php echo $table_start; ?>
						<?php /* ?>
						<h3>Standard Daten</h3>
						<p>Nachfolgend können Sie Standardwerte für die Firma, Kontaktperson und Kontaktemail definieren.</p>
						<?php echo $table_start; ?>
							<?php $name = 'casawp_inquiryfallback_person_email'; ?>
							<?php $text = 'E-Mail Adresse für Anfragen'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><b><?php echo $text ?></b></label></th>
								<td>
									<input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text">
									<br><span class="description">Diese E-Mail Adresse wird beim Kontaktformular verwendet.</span>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scrope="row"><b>Organisation</b></th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>Organisation</span></legend>
										<?php $name = 'casawp_sellerfallback_show_organization'; ?>
										<?php $text = 'Organisation anzeigen, wenn beim Objekt keine vorhanden ist.'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
							<?php $name = 'casawp_sellerfallback_legalname'; ?>
							<?php $text = 'Organisation Name'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casawp_sellerfallback_address_street'; ?>
							<?php $text = 'Strasse'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casawp_sellerfallback_address_postalcode'; ?>
							<?php $text = 'PLZ'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casawp_sellerfallback_address_locality'; ?>
							<?php $text = 'Ort'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casawp_sellerfallback_address_region'; ?>
							<?php $text = 'Kanton'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casawp_sellerfallback_address_country'; ?>
							<?php $text = 'Land'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td>
									<select name="<?php echo $name ?>" id="<?php echo $name ?>">
										<option <?php echo (get_option($name) == '' ? 'selected="selected"' : ''); ?> value="">-</option>
										<option <?php echo (get_option($name) == 'CH' ? 'selected="selected"' : ''); ?> value="CH">Schweiz</option>
										<option <?php echo (get_option($name) == 'DE' ? 'selected="selected"' : ''); ?> value="DE">Deutschland</option>
										<option <?php echo (get_option($name) == 'FR' ? 'selected="selected"' : ''); ?> value="FR">Frankreich</option>
										<option <?php echo (get_option($name) == 'AT' ? 'selected="selected"' : ''); ?> value="AT">Österreich</option>
										<option <?php echo (get_option($name) == 'FL' ? 'selected="selected"' : ''); ?> value="FL">Fürstenthum Liechtenstein</option>
									</select>
								</td>
							</tr>
							<?php $name = 'casawp_sellerfallback_email'; ?>
							<?php $text = 'E-Mail Adresse'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casawp_sellerfallback_phone_central'; ?>
							<?php $text = 'Telefon Geschäft'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casawp_sellerfallback_fax'; ?>
							<?php $text = 'Fax'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
						<?php echo $table_end; ?>
						<?php echo $table_start; ?>
							<tr>
								<td></td>
							</tr>
							<tr valign="top">
								<th scrope="row"><b>Kontaktperson</b></th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>Kontaktperson</span></legend>
										<?php $name = 'casawp_sellerfallback_show_person_view'; ?>
										<?php $text = 'Kontaktperson anzeigen, wenn beim Objekt keine vorhanden ist.'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
							<?php $name = 'casawp_salesperson_fallback_gender'; ?>
							<?php $text = 'Geschlecht'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td>
									<select name="<?php echo $name ?>" id="<?php echo $name ?>">
										<option <?php echo (get_option($name) == 'F' ? 'selected="selected"' : ''); ?> value="F">Frau</option>
										<option <?php echo (get_option($name) == 'M' ? 'selected="selected"' : ''); ?> value="M">Herr</option>
									</select>
								</td>
							</tr>
							<?php $name = 'casawp_salesperson_fallback_givenname'; ?>
							<?php $text = 'Vorname'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casawp_salesperson_fallback_familyname'; ?>
							<?php $text = 'Nachname'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casawp_salesperson_fallback_function'; ?>
							<?php $text = 'Funktion'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casawp_salesperson_fallback_email'; ?>
							<?php $text = 'E-Mail Adresse'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casawp_salesperson_fallback_phone_direct'; ?>
							<?php $text = 'Direktwahl'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casawp_salesperson_fallback_phone_mobile'; ?>
							<?php $text = 'Mobile'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
						<?php echo $table_end; ?>
						<?php */ ?>
					<?php
					break;
				case 'archiveview':
					?>
					<?php echo $table_start; ?>
						<tr valign="top">
							<th scope="row">Sortierung</th>
							<td>


								<?php $name = 'casawp_archive_orderby'; ?>
								<?php $text = 'Sortierung'; ?>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<option <?php echo (get_option($name)  == 'date' ? 'selected="selected"' : ''); ?> value="date">Datum (Erfassung)</option>
                 					<option <?php echo (get_option($name)  == 'modified' ? 'selected="selected"' : ''); ?> value="modified">Datum (Bearbeitung)</option>
									<option <?php echo (get_option($name)  == 'menu_order' ? 'selected="selected"' : ''); ?> value="menu_order">Eigene Reihenfolge</option>
									<option <?php echo (get_option($name)  == 'location' ? 'selected="selected"' : ''); ?> value="location">Ort</option>
									<option <?php echo (get_option($name)  == 'price' ? 'selected="selected"' : ''); ?> value="price">Preis</option>
									<option <?php echo (get_option($name)  == 'casawp_referenceId' ? 'selected="selected"' : ''); ?> value="casawp_referenceId">Referenz-ID</option>
									<option <?php echo (get_option($name)  == 'title' ? 'selected="selected"' : ''); ?> value="title">Titel</option>
									<option <?php echo (get_option($name)  == 'start' ? 'selected="selected"' : ''); ?> value="start">Verfügbar ab</option>
								</select>
								<?php $name = 'casawp_archive_order'; ?>
								<?php $text = 'Sortierung'; ?>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<option <?php echo (get_option($name)  == 'DESC' ? 'selected="selected"' : ''); ?> value="DESC">Absteigend</option>
									<option <?php echo (get_option($name)  == 'ASC' ? 'selected="selected"' : ''); ?> value="ASC">Aufsteigend</option>
								</select>
							</td>
						</tr>
					<?php echo $table_end; ?>
					<?php echo $table_start; ?>
						<tr valign="top" style="opacity: 0; position: absolute; left: -9999px; top: -9999px; max-height: 0; overflow: hidden;">
							<th scope="row">Oben gehaltene Objekte</th>
							<td class="front-static-pages">
								<fieldset>
									<legend class="screen-reader-text"><span></span></legend>
									<?php $name = 'casawp_show_sticky_properties'; ?>
									<?php $text = 'Speziell ausgewiesen'; ?>
									<p><label>
										<?php
											$url = get_admin_url('', 'admin.php?page=casawp');
											$manually = $url . '&do_import=true';
											$forced = $manually . '&force_all_properties=true&force_last_import=true';
										?>
										<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
									</label></p>
								</fieldset>
								<fieldset>
									<legend class="screen-reader-text"><span></span></legend>
									<?php $name = 'casawp_hide_sticky_properties_in_main'; ?>
									<?php $text = 'in der Hauptliste verstecken'; ?>
									<p><label>
										<?php
											$url = get_admin_url('', 'admin.php?page=casawp');
											$manually = $url . '&do_import=true';
											$forced = $manually . '&force_all_properties=true&force_last_import=true';
										?>
										<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
									</label></p>
								</fieldset>
							</td>
						</tr>
					<?php echo $table_end; ?>
					<?php echo $table_start; ?>
						<tr valign="top">
							<th scope="row">Thumbnail</th>
							<td>
								<?php $name = 'casawp_archive_show_thumbnail_size_w'; ?>
								<?php $text = 'Breite'; ?>
								<div style="margin-bottom: 10px;">
									Breite x Höhe
								</div>
								<!-- <label for="<?php echo $name; ?>"><?php echo $text; ?></label> -->
								<input name="<?php echo $name; ?>" name="<?php echo $name; ?>" type="number" step="1" min="0" value="<?php echo get_option($name); ?>" class="small-text">
								<?php $name = 'casawp_archive_show_thumbnail_size_h'; ?>
								<?php $text = 'Höhe'; ?>
								<!-- <label for="<?php echo $name; ?>"><?php echo $text; ?></label> -->x
								<input name="<?php echo $name; ?>" id="<?php echo $name; ?>" type="number" step="1" min="0" value="<?php echo get_option($name); ?>" class="small-text"><br>
								<?php $name = 'casawp_archive_show_thumbnail_size_crop'; ?>
								<?php $text = 'Zuschneiden'; ?>
								<div style="margin-top: 15px;">
									<input name="<?php echo $name; ?>" name="<?php echo $name; ?>" type="checkbox" value="1" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?>>
									<label for="<?php echo $name; ?>"><?php echo $text; ?></label>
								</div>
							</td>
						</tr>
					<?php echo $table_end; ?>

					<?php echo $table_start; ?>
						<tr valign="top">
							<th scope="row">Filter</th>
							<td id="front-static-padges">
								<fieldset>
									<?php $name = 'casawp_filter_hide'; ?>
									<?php $text = 'Filter ausblenden'; ?>
									<label>
										<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
									</label>
								</fieldset>
								<fieldset>
									<?php $name = 'casawp_ajaxify_archive'; ?>
									<?php $text = 'Live-Filter aktivieren'; ?>
									<label>
										<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
									</label>
								</fieldset>
							</td>
						</tr>
            			<tr valign="top">
							<?php $name = 'casawp_filter_salestypes_elementtype'; ?>
							<?php $text = 'Miete / Kauf'; ?>
							<?php $options = ['multiselect' => 'Mehrfach-Filter', 'singleselect' => 'Einfache Auswahl', 'multicheckbox' => 'Checkboxes', 'radio' => 'Radio', 'hidden' => 'Ausblenden' ]; ?>
							<th></th>
							<td>
								<label class="block-label" for="<?php echo $name; ?>"><?php echo $text ?></label>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
            			<tr valign="top">
							<?php $name = 'casawp_filter_utilities_elementtype'; ?>
							<?php $text = 'Nutzung'; ?>
							<?php $options = ['hidden' => 'Ausblenden', 'multiselect' => 'Mehrfach-Filter', 'singleselect' => 'Einfache Auswahl', 'multicheckbox' => 'Checkboxes', 'radio' => 'Radio']; ?>
							<th></th>
							<td>
								<label class="block-label" for="<?php echo $name; ?>"><?php echo $text ?></label>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr valign="top">
							<?php $name = 'casawp_filter_categories_elementtype'; ?>
							<?php $text = 'Kategorie'; ?>
							<?php $options = ['multiselect' => 'Mehrfach-Filter', 'singleselect' => 'Einfache Auswahl', 'multicheckbox' => 'Checkboxes', 'radio' => 'Radio', 'hidden' => 'Ausblenden' ]; ?>
							<th></th>
							<td>
								<label class="block-label" for="<?php echo $name; ?>"><?php echo $text ?></label>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr  valign="top">
							<?php $name = 'casawp_filter_locations_elementtype'; ?>
							<?php $text = 'Ortschaft'; ?>
							<?php $options = ['multiselect' => 'Mehrfach-Filter', 'singleselect' => 'Einfache Auswahl', 'multicheckbox' => 'Checkboxes', 'radio' => 'Radio', 'hidden' => 'Ausblenden' ]; ?>
							<th></th>
							<td>
								<label class="block-label" for="<?php echo $name; ?>"><?php echo $text ?></label>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr  valign="top">
							<?php $name = 'casawp_filter_regions_elementtype'; ?>
							<?php $text = 'Region'; ?>
							<?php $options = ['hidden' => 'Ausblenden', 'singleselect' => 'Einfache Auswahl', 'multiselect' => 'Mehrfach-Filter',  'radio' => 'Radio' ]; ?>
							<th></th>
							<td>
								<label class="block-label" for="<?php echo $name; ?>"><?php echo $text ?></label>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr  valign="top">
							<?php $name = 'casawp_filter_countries_elementtype'; ?>
							<?php $text = 'Land'; ?>
							<?php $options = ['hidden' => 'Ausblenden', 'multiselect' => 'Mehrfach-Filter', 'singleselect' => 'Einfache Auswahl', 'multicheckbox' => 'Checkboxes', 'radio' => 'Radio' ]; ?>
							<th></th>
							<td>
								<label class="block-label" for="<?php echo $name; ?>"><?php echo $text ?></label>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr  valign="top">
							<?php $name = 'casawp_filter_features_elementtype'; ?>
							<?php $text = 'Eigenschaften'; ?>
							<?php $options = ['hidden' => 'Ausblenden', 'singleselect' => 'Einfache Auswahl', 'multiselect' => 'Mehrfach-Filter', 'multicheckbox' => 'Checkboxes', 'radio' => 'Radio' ]; ?>
							<th></th>
							<td>
								<label class="block-label" for="<?php echo $name; ?>"><?php echo $text ?></label>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
           				<tr  valign="top">
							<?php $name = 'casawp_filter_price_from_elementtype'; ?>
							<?php $text = 'Preis (von — bis)'; ?>
							<?php $options = ['hidden' => 'Ausblenden', 'singleselect' => 'Einfache Auswahl', 'radio' => 'Radio']; ?>
							<th></th>
							<td>
								<label class="block-label" for="<?php echo $name; ?>"><?php echo $text ?></label>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
								<?php $name = 'casawp_filter_price_to_elementtype'; ?>
								<?php $text = 'Preis-Filter zu'; ?>
								<?php $options = ['hidden' => 'Ausblenden', 'singleselect' => 'Einfache Auswahl', 'radio' => 'Radio' ]; ?>
								— <select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr  valign="top">
							<?php $name = 'casawp_filter_rooms_from_elementtype'; ?>
							<?php $text = 'Zimmer (von — bis)'; ?>
							<?php $options = ['hidden' => 'Ausblenden', 'singleselect' => 'Einfache Auswahl', 'radio' => 'Radio' ]; ?>
							<th></th>
							<td>
								<label class="block-label" for="<?php echo $name; ?>"><?php echo $text ?></label>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
								<?php $name = 'casawp_filter_rooms_to_elementtype'; ?>
								<?php $options = ['hidden' => 'Ausblenden', 'singleselect' => 'Einfache Auswahl', 'radio' => 'Radio' ]; ?>
								—
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr  valign="top">
							<?php $name = 'casawp_filter_areas_from_elementtype'; ?>
							<?php $text = 'Fläche (von — bis)'; ?>
							<?php $options = ['hidden' => 'Ausblenden', 'singleselect' => 'Einfache Auswahl', 'radio' => 'Radio' ]; ?>
							<th></th>
							<td>
								<label class="block-label" for="<?php echo $name; ?>"><?php echo $text ?></label>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
								<?php $name = 'casawp_filter_areas_to_elementtype'; ?>
								<?php $text = 'Fläche zu'; ?>
								<?php $options = ['hidden' => 'Ausblenden', 'singleselect' => 'Einfache Auswahl', 'radio' => 'Radio' ]; ?>
								— <select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<?php foreach ($options as $key => $value) : ?>
										<option <?php echo (get_option($name)  == $key ? 'selected="selected"' : ''); ?> value="<?= $key ?>"><?= $value ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr valign="top">
							<th></th>
							<td>
								<?php $name = 'casawp_archive_rooms_min'; ?>
								<?php $text = 'Zimmer min'; ?>
								<label class="block-label" for="<?php echo $name; ?>">Zimmer (Range manuell festlegen)</label>
								min. <input name="<?php echo $name; ?>" name="<?php echo $name; ?>" type="number" step="0.5" min="0" value="<?php echo get_option($name); ?>" class="small-text">
								<?php $name = 'casawp_archive_rooms_max'; ?>
								<?php $text = 'Zimmer max'; ?> — 
								<input name="<?php echo $name; ?>" id="<?php echo $name; ?>" type="number" step="0.5" min="0" value="<?php echo get_option($name); ?>" class="small-text"> max.
							</td>
						</tr>
						<tr valign="top">
							<th></th>
							<td>
								<?php $name = 'casawp_archive_area_min'; ?>
								<?php $text = 'Fläche min'; ?>
								<label class="block-label" for="<?php echo $name; ?>">Fläche (Range manuell festlegen)</label>
								min. <input name="<?php echo $name; ?>" name="<?php echo $name; ?>" type="number" step="1" min="0" value="<?php echo get_option($name); ?>" class="small-text">
								<?php $name = 'casawp_archive_area_max'; ?>
								<?php $text = 'Fläche max'; ?> — 
								<input name="<?php echo $name; ?>" id="<?php echo $name; ?>" type="number" step="1" min="0" value="<?php echo get_option($name); ?>" class="small-text"> max.
							</td>
						</tr>
					<?php echo $table_end; ?>


						<?php echo $table_start; ?>
							<tr valign="top">
								
								<th scope="row">Dynamische Felder</th>
								<td id="front-static-padges">

									 <?php 
									 	$dynamicFields = array(
									 		'street' => array(
									 			'field' => 'casawp_archive_show_street_and_number',
									 			'order' => 'casawp_archive_show_street_and_number_order',
									 			'label' => 'Strasse + Nr'
									 		),
									 		'location' => array(
									 			'field' => 'casawp_archive_show_location',
									 			'order' => 'casawp_archive_show_location_order',
									 			'label' => 'Ort'
									 		),
									 		'number_of_rooms' => array(
									 			'field' => 'casawp_archive_show_number_of_rooms',
									 			'order' => 'casawp_archive_show_number_of_rooms_order',
									 			'label' => 'Anzahl Zimmer'
									 		),
									 		'area_sia_nf' => array(
									 			'field' => 'casawp_archive_show_area_sia_nf',
									 			'order' => 'casawp_archive_show_area_sia_nf_order',
									 			'label' => 'Nutzfläche'
									 		),
									 		'area_sia_gf' => array(
									 			'field' => 'casawp_archive_show_area_sia_gf',
									 			'order' => 'casawp_archive_show_area_sia_gf_order',
									 			'label' => 'Bruttogeschossfläche'
									 		),
									 		'area_bwf' => array(
									 			'field' => 'casawp_archive_show_area_bwf',
									 			'order' => 'casawp_archive_show_area_bwf_order',
									 			'label' => 'Wohnfläche'
									 		),
									 		'area_nwf' => array(
									 			'field' => 'casawp_archive_show_area_nwf',
									 			'order' => 'casawp_archive_show_area_nwf_order',
									 			'label' => 'Nettwowohnfläche'
									 		),
									 		'surface_property' => array(
									 			'field' => 'casawp_archive_show_surface_property',
									 			'order' => 'casawp_archive_show_surface_property_order',
									 			'label' => 'Grundstücksfläche'
									 		),
									 		'floor' => array(
									 			'field' => 'casawp_archive_show_floor',
									 			'order' => 'casawp_archive_show_floor_order',
									 			'label' => 'Etage'
									 		),
									 		'number_of_floors' => array(
									 			'field' => 'casawp_archive_show_number_of_floors',
									 			'order' => 'casawp_archive_show_number_of_floors_order',
									 			'label' => 'Anzahl Etagen'
									 		),
									 		'year_built' => array(
									 			'field' => 'casawp_archive_show_year_built',
									 			'order' => 'casawp_archive_show_year_built_order',
									 			'label' => 'Baujahr'
									 		),
									 		'year_renovated' => array(
									 			'field' => 'casawp_archive_show_year_renovated',
									 			'order' => 'casawp_archive_show_year_renovated_order',
									 			'label' => 'Letzte Renovation'
									 		),
									 		'availability' => array(
									 			'field' => 'casawp_archive_show_availability',
									 			'order' => 'casawp_archive_show_availability_order',
									 			'label' => 'Verfügbarkeit'
									 		),
									 		'price' => array(
									 			'field' => 'casawp_archive_show_price',
									 			'order' => 'casawp_archive_show_price_order',
									 			'label' => 'Preis'
									 		),
									 		'excerpt' => array(
									 			'field' => 'casawp_archive_show_excerpt',
									 			'order' => 'casawp_archive_show_excerpt_order',
									 			'label' => 'Auszug'
									 		),
									 	);
									  ?>

									<script>
										jQuery(document).ready( function($) {
											$('#draggableList').children().each(function(idx, val){
												$(val).find('.small-text').val(idx);
											})
											$( "#draggableList" ).sortable({
											    stop: function(event, ui) {
													var itemOrder = $('#draggableList').sortable("toArray");
													for (var i = 0; i < itemOrder.length; i++) {
														$('#' + itemOrder[i]).find('.small-text').val(i);
													}
											    }
										  	});
										});
									</script>
									<fieldset id="draggableList">

										<?php 
											$finalArray = [];
											$shouldSort = false;
										 ?>

										<?php foreach ($dynamicFields as $field): ?>
											<?php $finalArray[$field['field']]['field'] = $field['field'] ?>
											<?php $finalArray[$field['field']]['order'] = get_option($field['order']) ?>
											<?php $finalArray[$field['field']]['label'] = $field['label'] ?>
											<?php if (get_option($field['order'])): ?>
												<?php $shouldSort = true; ?>
											<?php endif; ?>
										<?php endforeach ?>

										<?php if ($shouldSort): ?>
											<?php usort($finalArray, function ($a, $b) { return $a['order'] - $b['order']; }); ?>
										<?php endif; ?>

										<?php foreach ($finalArray as $field): ?>
											<div class="draggable-list-item" id="<?php echo $field['field'] ?>">
												<svg width="21px" height="20px" viewBox="0 0 21 20" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
												    <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
												        <g id="Group-2" transform="translate(1.766234, 0.000000)" fill="#333333">
												            <polygon id="Line" fill-rule="nonzero" points="18.2926829 5.85365854 18.2926829 7.31707317 -0.503642699 7.31707317 -0.503642699 5.85365854"></polygon>
												            <polygon id="Line-Copy" fill-rule="nonzero" points="18.2926829 9.26829268 18.2926829 10.7317073 -0.503642699 10.7317073 -0.503642699 9.26829268"></polygon>
												            <polygon id="Line-Copy-2" fill-rule="nonzero" points="18.2926829 12.6829268 18.2926829 14.1463415 -0.503642699 14.1463415 -0.503642699 12.6829268"></polygon>
												            <polygon id="Triangle-Copy" points="8.89452011 20 13.7725689 16.097561 4.01647133 16.097561"></polygon>
												            <polygon id="Triangle-Copy-2" points="8.89452011 0 13.7725689 3.90243902 4.01647133 3.90243902"></polygon>
												        </g>
												    </g>
												</svg>
												<label>
													<input name="<?php echo $field['field'] ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($field['field']) ? 'checked="checked"' : ''); ?> > <?php echo $field['label'] ?>
												</label>
												<label>
													<input name="<?php echo $field['field'] ?>_order" type="hidden" value="<?php echo get_option($field['order']); ?>" class="small-text">
												</label>
											</div> 
										<?php endforeach ?>

									</fieldset>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"></th>
								<td id="front-static-padges">
									<fieldset>
										<?php $name = 'casawp_prefer_extracost_segmentation'; ?>
										<?php $text = 'Nettomiete anzeigen'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
									</fieldset>
									<fieldset>
								</td>
							</tr>

						<?php echo $table_end; ?>
						<?php
					break;
				case 'contactform':
					?>
						<?php /******* Kontaktformular *******/ ?>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row"><label><input name="casawp_inquiry_method" type="hidden" value="casamail"> <strong>CASA</strong><span style="font-weight:100">MAIL</span></label></th>
								<td class="front-static-pages contactform-tab">
									<label class="block-label" for="<?php echo $name; ?>">Provider Slug</label>
									<input name="casawp_customerid" type="text" value="<?= get_option('casawp_customerid') ?>" class="regular-text">
									
									<label class="block-label block-label--intd" for="<?php echo $name; ?>">Publisher Slug</label>
									<input name="casawp_publisherid" type="text" value="<?= get_option('casawp_publisherid') ?>" class="regular-text">

									<label class="block-label block-label--intd" for="<?php echo $name; ?>">Google reCAPTCHA Key</label>
									<input name="casawp_recaptcha" type="text" value="<?= get_option('casawp_recaptcha') ?>" class="regular-text">

									<label class="block-label block-label--intd" for="<?php echo $name; ?>">Google reCAPTCHA Secret</label>
									<input name="casawp_recaptcha_secret" type="text" value="<?= get_option('casawp_recaptcha_secret') ?>" class="regular-text">
									<fieldset class="margin-top">
										<label>
											<input type="checkbox" name="casawp_casamail_direct_recipient" value="1" <?php echo (get_option('casawp_casamail_direct_recipient') == '1' ? 'checked="checked"' : ''); ?>> Objekt-Anfragen als E-Mail senden
										</label>
									</fieldset>
									<fieldset class="margin-top">
										<label>
											<input type="checkbox" name="casawp_form_gender_neutral" value="1" <?php echo (get_option('casawp_form_gender_neutral') == '1' ? 'checked="checked"' : ''); ?>> Neutrale Anrede aktivieren
										</label>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">Kontaktangaben</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>E-Mail des Anbieters anzeigen</span></legend>
										<?php $name = 'casawp_show_email_organisation'; ?>
										<?php $text = 'E-Mail des Anbieters anzeigen'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
									</fieldset>
									<fieldset>
										<legend class="screen-reader-text"><span>E-Mail der Kontaktperson anzeigen</span></legend>
										<?php $name = 'casawp_show_email_person_view'; ?>
										<?php $text = 'E-Mail der Kontaktperson anzeigen'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">Pflichtfelder</th>
								<td id="front-static-padges">
									<fieldset>
										<legend class="screen-reader-text"><span>Vorname</span></legend>
										<?php $name = 'casawp_form_firstname_required'; ?>
										<?php $text = 'Vorname'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>Nachname</span></legend>
										<?php $name = 'casawp_form_lastname_required'; ?>
										<?php $text = 'Nachname'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>Strasse</span></legend>
										<?php $name = 'casawp_form_street_required'; ?>
										<?php $text = 'Strasse'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>PLZ</span></legend>
										<?php $name = 'casawp_form_postalcode_required'; ?>
										<?php $text = 'PLZ'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>Ort</span></legend>
										<?php $name = 'casawp_form_locality_required'; ?>
										<?php $text = 'Ort'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>Telefon</span></legend>
										<?php $name = 'casawp_form_phone_required'; ?>
										<?php $text = 'Telefon'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>E-Mail</span></legend>
										<?php $name = 'casawp_form_email_required'; ?>
										<?php $text = 'E-Mail'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>Nachricht</span></legend>
										<?php $name = 'casawp_form_message_required'; ?>
										<?php $text = 'Nachricht'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">Event-Tracking</th>
								<td id="front-static-padges">
									<legend class="screen-reader-text"><span>Event-Tracking</span></legend>
									<?php $name = 'casawp_form_event_tracking'; ?>
									<?php $text = 'Aktiv'; ?>
									<label class="block-label" for="<?php echo $name; ?>">JavaScript Tracking-Event angeben</label>
									<label>
										<input name="<?php echo $name ?>" type="text" value="<?php echo stripslashes(get_option($name)); ?>" class="regular-text">
										<br><span class="description">Beispiel: _gaq.push(['_trackEvent', '%casawp_id%', 'CASAWP Anfrage'])</span>
									</label>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php
					break;
				case 'logs':
					?>

					<?php echo $table_start; ?>
						<tr valign="top">
							<th scope="row"><label>Verfügbare Logs</label></th>
							<td class="front-static-pages contactform-tab">
								<label class="block-label"></label>
								<?php
								$dir = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/logs';

								$log = '/wp-content/uploads/casawp/logs'."/".date('Ym').'.log';

								echo '<a href="'.$log.'" target="_blank">'.$log.'</a><br>';

								for ($i = 1; $i <= 6; $i++) 
								{
								   $months[] = date("Ym%", strtotime( date( 'Y-m-01' )." -$i months"));

								   $log = '/wp-content/uploads/casawp/logs'."/".date("Ym", strtotime( date( 'Y-m-01' )." -$i months")).'.log';

								   if (file_exists(ABSPATH . $log)) {
								   	echo '<a href="'.$log.'" target="_blank">'.$log.'</a><br>';
								   }

								}
								?>
								
							</td>
						</tr>
					<?php echo $table_end; ?>

					

					<?php



					/*echo "<h3>" . date('Y M') . "</h3>";
					echo "<dl>";
				    if (is_file($log)) {
				    	$file_handle = fopen($log, "r");
						while (!feof($file_handle)) {
							$line = fgets($file_handle);
							$arr = json_decode($line, true);
							if ($arr) {
						   		foreach ($arr as $datestamp => $properties) {
						   			echo '<dt>'.str_replace(' ', "T", $datestamp) .'</dt><dd><pre style="margin-top:0px;padding-left:10px;">';
						   				if (is_array($properties)) {
						   					foreach ($properties as $slug => $property) {
							   					echo "\n" . $slug . ': ' . htmlentities(json_encode($property));
							   				}
						   				} else {
						   					echo $properties;
						   				}

						   			echo '</pre></dd>';
						   		}
						  	} else {
								echo '<dt></dt><dd><pre style="margin-top:0px;padding-left:10px;">'.$line.'</pre></dd>';
							}

						}
						fclose($file_handle);
				    }
				    echo "</dl>";*/

					break;
				case 'private':
					?>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">Anmelde Seite</th>
								<td>
									<fieldset>
										<?php $name = 'casawp_private_loginpage'; ?>
										<?php $args = array(
											 'selected'              => get_option($name),
											 'echo'                  => 1,
											 'name'                  => $name,
											 'show_option_none'      => 'Auswählen',
											 'option_none_value'     => null,
											);
											wp_dropdown_pages( $args );
										?>
									</fieldset>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Abmelde Seite</th>
								<td>
									<fieldset>
										<?php $name = 'casawp_private_logoutpage'; ?>
										<?php $args = array(
											 'selected'              => get_option($name),
											 'echo'                  => 1,
											 'name'                  => $name,
											 'show_option_none'      => 'Auswählen',
											 'option_none_value'     => null,
											);
											wp_dropdown_pages( $args );
										?>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>

					<?php
					break;
				case 'general':
				default:
					?>
						<?php /******* General *******/ ?>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scrope="row">HTML</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>Vor dem Plugin</span></legend>
										<?php $name = 'casawp_before_content'; ?>
										<?php $text = 'Vor dem Inhalt'; ?>
										<p><?php echo $text; ?></p>
										<p><label>
											<textarea placeholder='<div id="content">' name="<?php echo $name ?>" id="<?php echo $name; ?>" class="large-text code" rows="2" cols="50"><?php echo stripslashes(get_option($name)); ?></textarea>
										</label></p>
									</fieldset>
									<fieldset>
										<legend class="screen-reader-text"><span>Nach dem Plugin</span></legend>
										<?php $name = 'casawp_after_content'; ?>
										<?php $text = 'Nach dem Inhalt'; ?>
										<p><?php echo $text; ?></p>
										<p><label>
											<textarea placeholder="</div>" name="<?php echo $name ?>" id="<?php echo $name; ?>" class="large-text code" rows="2" cols="50"><?php echo stripslashes(get_option($name)); ?></textarea>
										</label></p>
										<p class="description" id="tagline-description">Erlaubt ist nur der div-Tag mit den Attributen id und class.</p>
									</fieldset>
									<legend class="screen-reader-text"><span>Custom Slug</span></legend>
									<?php $name = 'casawp_custom_slug'; ?>
									<?php $text = 'Custom Slug'; ?>
									<label class="block-label block-label--intd" for="<?php echo $name; ?>"><?php echo $text; ?></label>
									<input type="text" placeholder="Custom Slug definieren" name="<?php echo $name ?>" value="<?= get_option($name) ?>" id="<?php echo $name; ?>" class="regular-text"  />

								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Import</th>
								<td class="front-static-pages">

									<fieldset>
										<legend class="screen-reader-text"><span><a href="https://casasoft.ch/produkte/schnittstellenmanager" target="_blank">CASAGATEWAY</a> als CDN für Bilder verwenden.</span></legend>
										<?php $name = 'casawp_use_casagateway_cdn'; ?>
										<?php $text = 'Bilder direkt von Gateway CDN darstellen.'; ?>
										<p><label>
											<input id="ckCDN" name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> onClick="ckChange()" ><a href="https://casasoft.ch/produkte/schnittstellenmanager" target="_blank">CASAGATEWAY</a> als CDN für Bilder verwenden.
										</label></p>
									</fieldset>

									<fieldset>
										<legend class="screen-reader-text"><span>Max. 1 Bild für Referenz-Objekte importieren (nicht möglich mit <a href="https://casasoft.ch/produkte/schnittstellenmanager" target="_blank">CASAGATEWAY</a> CDN).</span></legend>
										<?php $name = 'casawp_limit_reference_images'; ?>
										<?php $text = 'Max. 1 Bild für Referenzobjekte importieren (kann nicht mit Gateway CDN kombiniert werden).'; ?>
										<p><label>
											<input id="ckRef" name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> onClick="ckChange()">Max. 1 Bild für Referenz-Objekte importieren (nicht möglich mit <a href="https://casasoft.ch/produkte/schnittstellenmanager" target="_blank">CASAGATEWAY</a> CDN).
										</label></p>
									</fieldset>

									<fieldset>
										<legend class="screen-reader-text"><span>Zu löschende Objekte direkt löschen (Papierkorb überspringen).</span></legend>
										<?php $name = 'casawp_permanently_delete_properties'; ?>
										<?php $text = 'Zu löschende Objekte direkt löschen (Papierkorb überspringen).'; ?>
										<p><label>
											<input id="ckDel" name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> onClick="ckChange()">Zu löschende Objekte direkt löschen (Papierkorb überspringen).
										</label></p>
									</fieldset>

									<fieldset>
										<legend class="screen-reader-text"><span>Objekte mit dynamischem Inhalt übersetzen (falls keine Übersetzung vorhanden).</span></legend>
										<?php $name = 'casawp_auto_translate_properties'; ?>
										<?php $text = 'Objekte mit dynamischem Inhalt übersetzen (falls keine Übersetzung vorhanden).'; ?>
										<p><label>
											<input id="ckTrans" name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> onClick="ckChange()">Objekte mit dynamischem Inhalt übersetzen (falls keine Übersetzung vorhanden).
										</label></p>
									</fieldset>

									<script>
									function ckChange(){
									    var ckCDN = document.getElementById('ckCDN');
									    var ckRef = document.getElementById('ckRef');

									    if (ckRef.checked) {
									    	ckCDN.disabled = true; 
									    } else {
									    	ckCDN.disabled = false; 
									    } 

									    if (ckCDN.checked) {
									    	ckRef.disabled = true; 
									    } else {
									    	ckRef.disabled = false; 
									    } 
									}

									window.onload = function() {
										ckChange();
									};
									</script>

									<fieldset style="opacity: 0; position: absolute; left: -9999px; top: -9999px; max-height: 0; overflow: hidden;">
										<legend class="screen-reader-text"><span>Synchronisation mit Exporter/Marklersoftware</span></legend>
										<?php $name = 'casawp_live_import'; ?>
										<?php $text = 'Datei <code>/wp-content/uploads/casawp/import/data.xml</code> automatisch bei jedem Seitenaufruf überprüfen und importieren.'; ?>
										<p><label>
											<?php
												$url = get_admin_url('', 'admin.php?page=casawp');
												$manually = $url . '&do_import=true';
												$force_last = $manually . '&force_last_import=true';
												$forced = $manually . '&force_all_properties=true&force_last_import=true';
											?>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label></p>
									</fieldset>



									<fieldset>
										<table>
											<tr>
												<?php $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data.xml'; if (file_exists($file)) : ?>
													<td><code>data.xml</code></td>
												<?php else: ?>
													<td><strike><code>data.xml</code></strike></td>
												<?php endif ?>
												<td><a class="button-primary" href="<?php echo $manually  ?>">Import ausführen</a></td>
											</tr>
											<tr>
												<?php $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data-done.xml'; if (file_exists($file)) : ?>
													<td><code>data-done.xml</code></td>
												<?php else: ?>
													<td><strike><code>data-done.xml</code></strike></td>
												<?php endif ?>
												<td><a class="button-primary" href="<?php echo $force_last  ?>">Letzer Import erneut ausführen</a></td>
											</tr>
											<tr>
												<?php $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data-done.xml'; if (file_exists($file)) : ?>
													<td><code>data-done.xml</code></td>
												<?php else: ?>
													<td><strike><code>data-done.xml</code></strike></td>
												<?php endif ?>
												<td><a class="button-primary" href="<?php echo $forced  ?>">Importierte Objekte überschreiben</a></td>
											</tr>
											<tr>
												<?php if (get_option('casawp_api_key') && get_option('casawp_private_key')): ?>
													<td><code><strong>CASA</strong><span style="font-weight:100">GATEWAY</span></code></td>
												<?php else: ?>
													<td><strike><code><strong>CASA</strong><span style="font-weight:100">GATEWAY</span></code></strike></td>
												<?php endif ?>
												<td><a class="button-primary" href="<?php echo  get_admin_url('', 'admin.php?page=casawp&gatewayupdate=1'); ?>">Daten von CASAGATEWAY beziehen</a></td>
											</tr>
										</table>
									</fieldset>
									<legend class="screen-reader-text"><span>API-Key</span></legend>
									<?php $name = 'casawp_api_key'; ?>
									<?php $text = 'API-Key'; ?>
									<label class="block-label block-label--intd" for="<?php echo $name; ?>"><?php echo $text; ?></label>
									<input type="text" placeholder="CASAGATEWAY API-Key einfügen" name="<?php echo $name ?>" value="<?= get_option($name) ?>" id="<?php echo $name; ?>" class="regular-text"  />

									<legend class="screen-reader-text"><span>Privater-Key</span></legend>
									<?php $name = 'casawp_private_key'; ?>
									<?php $text = 'Private-Key'; ?>
									<label class="block-label block-label--intd" for="<?php echo $name; ?>"><?php echo $text; ?></label>
									<input type="text" placeholder="CASAGATEWAY Private-Key einfügen" name="<?php echo $name ?>" value="<?= get_option($name) ?>" id="<?php echo $name; ?>" class="regular-text" />
								</td>
							</tr>
							<!-- <tr valign="top">
								<th scope="row">Legacy import</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>Altes Importscript für casaXML "draft" aktivieren.</span></legend>
										<?php $name = 'casawp_legacy'; ?>
										<?php $text = 'Altes Importscript für casaXML "draft" aktivieren.'; ?>
										<p><label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label></p>
									</fieldset>
								</td>
							</tr> -->

							<?php
								$all_categories = get_categories(array('taxonomy' => 'casawp_category'));
								$custom_categories = array();
								$i=0;
								foreach ($all_categories as $key => $category) {
									if ( substr($category->slug, 0, 7) == 'custom_') {
										$custom_categories[$i]['name'] = $category->slug;
										$custom_categories[$i]['term_id'] = $category->term_id;
										$i++;
									}
								}
								if (function_exists('icl_get_home_url')) {
									$all_languages = icl_get_languages('skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str');
								} else {
                  $cur_locale = get_locale();
                  $cur_lang = substr($cur_locale, 0, 2);
									$all_languages = array($cur_lang => array('translated_name' => 'Label'));
								}
							?>

							<?php if (!empty($custom_categories)) : ?>
								<tr valign="top">
									<th scrope="row">Eigene Kategorien</th>
									<td class="front-static-pages">
										<fieldset>
											<legend class="screen-reader-text"><span>Eigene Kategorien</span></legend>
												<table class="form-table">
													<tbody>
														<tr>
															<td><strong>ID</strong></td>
															<?php
																foreach ($all_languages as $key => $value) {
																	echo '<td><strong>' . $value['translated_name'] . '</strong></td>';
																}
															?>
															<td><strong>Auf Website anzeigen</strong></td>
														</tr>
														<?php
															foreach ($custom_categories as $key => $value) {
																echo '<tr>';
																echo '<td>' . $value['name'] . '</td>';

																$get_saved_custom_categories = get_option('casawp_custom_category_translations');
																foreach ($all_languages as $k => $v) {
																	$name = $value['name'] . '/' . $k;

																	$translated_name = '';
																	if (is_array($get_saved_custom_categories) && count($get_saved_custom_categories) > 0) {
																		if (   array_key_exists($value['name'], $get_saved_custom_categories)
																			&& array_key_exists($k, $get_saved_custom_categories[$value['name']])) {
																			$translated_name = $get_saved_custom_categories[$value['name']][$k];
																		}
																	}
																	echo '<td><input name="' . $name . '" type="text" value="' . $translated_name . '"></td>';
																}

																$name = $value['name'] . '/show';
																$checked = false;
																if (is_array($get_saved_custom_categories) && count($get_saved_custom_categories) > 0) {
																	if (   array_key_exists($value['name'], $get_saved_custom_categories)
																		&& array_key_exists('show', $get_saved_custom_categories[$value['name']]) && $get_saved_custom_categories[$value['name']]['show'] != false) {
																		$checked = 'checked=checked';
																	}
																}
																echo '<td><input name="' . $name . '" value="1" class="tog" type="checkbox" ' . $checked . '></td>';
																echo '</tr>';
															}
														?>
													</tbody>
												</table>
										</fieldset>
									</td>
								</tr>
							<?php endif; ?>
						<?php echo $table_end; ?>
					<?php
					break;
			}
		?>
		<p class="submit"><input type="submit" name="casawp_submit" id="submit" class="button button-primary" value="Änderungen übernehmen"></p>
	</form>
