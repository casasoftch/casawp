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
				$parts = explode('/', $key);
				$saved_custom_categories[$parts[0]][$parts[1]] = $value;
				if (!array_key_exists('show', $saved_custom_categories[$parts[0]])) {
					$saved_custom_categories[$parts[0]]['show'] = '0';
				}
			}
			if (substr($key, 0, 6) == 'casawp') {
				update_option( $key, $value );
			}
		}

		if (count($saved_custom_categories) > 0) {
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
					'casawp_load_chosen',
					'casawp_load_googlemaps'
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
					'casawp_archive_show_area_sia_nf',
					'casawp_archive_show_surface_property',
					'casawp_archive_show_floor',
					'casawp_archive_show_number_of_floors',
					'casawp_archive_show_year_built',
					'casawp_archive_show_year_renovated',
					'casawp_archive_show_price',
					'casawp_archive_show_excerpt',
					'casawp_archive_show_availability',
					'casawp_archive_show_thumbnail_size_crop'
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
					'casawp_casamail_direct_recipient'
				);
				break;
			case 'general':
			default:
				$checkbox_traps = array(
					'casawp_limit_reference_images',
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
			'general'     => 'Generell',
			'appearance'  => 'Design',
			'singleview'  => 'Einzelansicht',
			'archiveview' => 'Archivansicht',
			'contactform' => 'Kontaktformular',
			'logs' => 'Logs'
		); 
	    echo screen_icon('options-general');
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
								<th scope="row">Theme</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>Template</span></legend>
										<?php $name = 'casawp_viewgroup'; ?>
										<?php $text = 'Darstellungs-Template auswählen'; ?>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="bootstrap2" <?php echo (get_option($name) == 'bootstrap2' ? 'checked="checked"' : ''); ?>> Twitter Bootstrap Version 2
										</label>
										<br>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="bootstrap3" <?php echo (get_option($name) == 'bootstrap3' ? 'checked="checked"' : ''); ?>> Twitter Bootstrap Version 3
										</label>
										<br>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="bootstrap4" <?php echo (get_option($name) == 'bootstrap4' ? 'checked="checked"' : ''); ?>> Twitter Bootstrap Version 4 (coming soon)
										</label>
										<br>
									</fieldset>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Scripts</th>
								<td class="front-static-pages">
									<fieldset>
										<?php $name = 'casawp_load_css'; ?>
										<?php $text = 'CSS laden'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<?php $name = 'casawp_load_scripts'; ?>
										<?php $text = 'JS laden'; ?>
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
										<?php $text = 'Feather Light (Lightbox)'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<?php $name = 'casawp_load_chosen'; ?>
										<?php $text = 'Chosen'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<?php $name = 'casawp_load_googlemaps'; ?>
										<?php $text = 'Google Maps'; ?>
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
						<h3>Social Media</h3>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">Folgende Social Media Plattformen anzeigen</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>Folgende Social Media Plattformen anzeigen</span></legend>
										<?php $name = 'casawp_share_facebook'; ?>
										<?php $text = 'Facebook'; ?>
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
						<h3>Dynamische Felder</h3>
						<?php echo $table_start; ?>
							<tr valign="top">
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
						<h3>Karte</h3>
						<?php echo $table_start; ?>
							<?php $name = 'casawp_single_use_zoomlevel'; ?>
							<?php $text = 'Zoomstufe'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td>
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
						<h3>Galerie</h3>
						<?php echo $table_start; ?>
							<tr valign="top">
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
							<tr>
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
									<option <?php echo (get_option($name)  == 'date' ? 'selected="selected"' : ''); ?> value="date">Datum</option>
									<option <?php echo (get_option($name)  == 'title' ? 'selected="selected"' : ''); ?> value="title">Titel</option>
									<option <?php echo (get_option($name)  == 'price' ? 'selected="selected"' : ''); ?> value="price">Preis</option>
									<option <?php echo (get_option($name)  == 'location' ? 'selected="selected"' : ''); ?> value="location">Ort</option>
									<option <?php echo (get_option($name)  == 'casawp_referenceId' ? 'selected="selected"' : ''); ?> value="casawp_referenceId">Referenz-ID</option>
									<option <?php echo (get_option($name)  == 'menu_order' ? 'selected="selected"' : ''); ?> value="menu_order">Eigene Reihenfolge</option>
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
						<tr valign="top">
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
							<th scope="row">Bildgrösse</th>
							<td>
								<?php $name = 'casawp_archive_show_thumbnail_size_w'; ?>
								<?php $text = 'Breite'; ?>
								<label for="<?php echo $name; ?>"><?php echo $text; ?></label>
								<input name="<?php echo $name; ?>" name="<?php echo $name; ?>" type="number" step="1" min="0" value="<?php echo get_option($name); ?>" class="small-text">
								<?php $name = 'casawp_archive_show_thumbnail_size_h'; ?>
								<?php $text = 'Höhe'; ?>
								<label for="<?php echo $name; ?>"><?php echo $text; ?></label>
								<input name="<?php echo $name; ?>" id="<?php echo $name; ?>" type="number" step="1" min="0" value="<?php echo get_option($name); ?>" class="small-text"><br>
								<?php $name = 'casawp_archive_show_thumbnail_size_crop'; ?>
								<?php $text = 'Beschneide das Miniaturbild auf die exakte Größe (Miniaturbilder sind normalerweise proportional)'; ?>
								<input name="<?php echo $name; ?>" name="<?php echo $name; ?>" type="checkbox" value="1" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?>>
								<label for="<?php echo $name; ?>"><?php echo $text; ?></label>
							</td>
						</tr>
					<?php echo $table_end; ?>
					<h3>Dynamische Felder</h3>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">Welche Werte sollen angezeigt werden? Das 2. Feld bestimmt die Ordnung der Darstellung.</th>
								<td id="front-static-padges">
									<fieldset>
										<legend class="screen-reader-text"><span>Welche Werte sollen angezeigt werden? Das 2. Feld bestimmt die Ordnung der Darstellung.</span></legend>
										<?php $name = 'casawp_archive_show_street_and_number'; ?>
										<?php $text = 'Strasse + Nr'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_street_and_number_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_archive_show_location'; ?>
										<?php $text = 'Ort'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_location_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_archive_show_number_of_rooms'; ?>
										<?php $text = 'Anzahl Zimmer'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_number_of_rooms_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_archive_show_area_sia_nf'; ?>
										<?php $text = 'Nutzfläche'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_area_sia_nf_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_archive_show_area_bwf'; ?>
										<?php $text = 'Wohnfläche'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_area_bwf_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_archive_show_surface_property'; ?>
										<?php $text = 'Grundstücksfläche'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_surface_property_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_archive_show_floor'; ?>
										<?php $text = 'Etage'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_floor_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_archive_show_number_of_floors'; ?>
										<?php $text = 'Anzahl Etage'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_number_of_floors_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_archive_show_year_built'; ?>
										<?php $text = 'Baujahr'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_year_built_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_archive_show_year_renovated'; ?>
										<?php $text = 'Letzte Renovation'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_year_renovated_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_archive_show_availability'; ?>
										<?php $text = 'Verfügbarkeit'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_availability_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_archive_show_price'; ?>
										<?php $text = 'Preis'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_price_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casawp_archive_show_excerpt'; ?>
										<?php $text = 'Auszug'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casawp_archive_show_excerpt_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php
					break;
				case 'contactform':
				default:
					?>
						<?php /******* Kontaktformular *******/ ?>
						<h3>Anfrage-Variante</h3>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row"><label><input name="casawp_inquiry_method" type="radio" value="casamail" <?php echo (get_option('casawp_inquiry_method') == 'casamail' ? 'checked="checked"' : ''); ?>> <strong>CASA</strong><span style="font-weight:100">MAIL</span></label></th>
								<td class="front-static-pages contactform-tab">
									<fieldset>
										<input style="width:100%" name="casawp_publisherid" type="text" placeholder="PUBLISHER ID" value="<?= get_option('casawp_publisherid') ?>" class="regular-text"> 
										<input style="width:100%" name="casawp_customerid" type="text" placeholder="CUSTOMER ID" value="<?= get_option('casawp_customerid') ?>" class="regular-text"> 
										<small>(Objekte mit deklarierten CUSTOMER IDs werden bevorzugt)</small>
										<br><br>
										<input type="checkbox" name="casawp_casamail_direct_recipient" value="1" <?php echo (get_option('casawp_casamail_direct_recipient') == '1' ? 'checked="checked"' : ''); ?>> <strong>CASA</strong>MAIL soll direkte E-Mails an angegebene <code>inquiryPerson</code> E-Mails versenden.
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row"><label><input name="casawp_inquiry_method" type="radio" value="email" <?php echo (get_option('casawp_inquiry_method') == 'email' ? 'checked="checked"' : ''); ?>> E-Mail</label></th>
								<td class="front-static-pages contactform-tab">
									<fieldset>
										<input style="width:100%" name="casawp_email_fallback" type="text" placeholder="EMAIL" value=""> 
										<small>(Objekte mit deklarierten Anfrage-Emails werden bevorzugt)</small>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<h3>Adressblock</h3>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">E-Mail des Anbieters anzeigen</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>E-Mail des Anbieters anzeigen</span></legend>
										<?php $name = 'casawp_show_email_organisation'; ?>
										<?php $text = 'Ja'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">E-Mail der Kontaktperson anzeigen</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>E-Mail der Kontaktperson anzeigen</span></legend>
										<?php $name = 'casawp_show_email_person_view'; ?>
										<?php $text = 'Ja'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<h3>Formular Pflichtfelder</h3>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">Formular Pflichtfelder</th>
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
						<h3>Event Tracking</h3>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">Event-Tracking</th>
								<td id="front-static-padges">
									<fieldset>
										<legend class="screen-reader-text"><span>Aktiv</span></legend>
										<?php $name = 'casawp_form_event_tracking'; ?>
										<?php $text = 'Aktiv'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo stripslashes(get_option($name)); ?>" class="regular-text">
											<br><span class="description">Beispiel: _gaq.push(['_trackEvent', '%casawp_id%', 'casawp Kontaktanfrage'])</span>
											<br><span class="description">Erlaubte Variablen: casawp_id</span>
										</label>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php
					break;
				case 'logs':
					$dir = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/logs';
					$log = $dir."/".date('Y M').'.log';

					echo "<h3>" . date('Y M') . "</h3>";
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
				    echo "</dl>";

					break;
				case 'general':
				default:
					?>
						<?php /******* General *******/ ?>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scrope="row">HTML einfügen</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>Vor dem Plugin</span></legend>
										<?php $name = 'casawp_before_content'; ?>
										<?php $text = 'Vor dem Inhalt'; ?>
										<p><?php echo $text; ?></p>
										<p><label>
											<textarea placeholder="<div id='content'>" name="<?php echo $name ?>" id="<?php echo $name; ?>" class="large-text code" rows="2" cols="50"><?php echo stripslashes(get_option($name)); ?></textarea> 
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
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Import Einstellungen<br></th>
								<td class="front-static-pages">
									
									<fieldset>
										<legend class="screen-reader-text"><span>Bilderimport bei Referenzen beschränken. Wird bei vielen Referenz-Objekten empfohlen.</span></legend>
										<?php $name = 'casawp_limit_reference_images'; ?>
										<?php $text = 'Bilderimport bei Referenzen beschränken. Wird bei vielen Referenz-Objekten empfohlen.'; ?>
										<p><label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label></p>
									</fieldset>

									<fieldset>
										<legend class="screen-reader-text"><span>Synchronisation mit Exporter/Marklersoftware</span></legend>
										<?php $name = 'casawp_live_import'; ?>
										<?php $text = 'Datei <code>/wp-content/uploads/casawp/import/data.xml</code> automatisch bei jedem Seiten-Aufruf überprüfen und importieren.'; ?>
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
												<td><a href="<?php echo $manually  ?>">Import Manuel anstossen</a></td>	
											</tr>
											<tr>
												<?php $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data-done.xml'; if (file_exists($file)) : ?>
													<td><code>data-done.xml</code></td>
												<?php else: ?>
													<td><strike><code>data-done.xml</code></strike></td>
												<?php endif ?>
												<td><a href="<?php echo $force_last  ?>">Letzer erfolgreicher Import erneut anstossen</a></td>
											</tr>
											<tr>
												<?php $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data-done.xml'; if (file_exists($file)) : ?>
													<td><code>data-done.xml</code></td>
												<?php else: ?>
													<td><strike><code>data-done.xml</code></strike></td>
												<?php endif ?>
												<td><a href="<?php echo $forced  ?>">Letzer erfolgreicher Import erneut anstossen und alle Objekte zwingendermasse durchtesten</a></td>
											</tr>
											<tr>
												<?php if (get_option('casawp_api_key') && get_option('casawp_private_key')): ?>
													<td><code><strong>CASA</strong><span style="font-weight:100">GATEWAY</span></code></td>
												<?php else: ?>
													<td><strike><code><strong>CASA</strong><span style="font-weight:100">GATEWAY</span></code></strike></td>
												<?php endif ?>
												<td><a href="<?php echo  get_admin_url('', 'admin.php?page=casawp&gatewayupdate=1'); ?>">Import Ausführen</a></td>
											</tr>
										</table>
									</fieldset>
									<hr>

									<fieldset>
										<legend class="screen-reader-text"><span><strong>CASA</strong><span style="font-weight:100">GATEWAY</span> API Schlüssel</span></legend>
										<?php $name = 'casawp_api_key'; ?>
										<?php $text = '<strong>CASA</strong><span style="font-weight:100">GATEWAY</span> • API Key'; ?>
										<p><?php echo $text; ?></p>
										<p>
											<input type="text" placeholder="Deaktiviert" name="<?php echo $name ?>" value="<?= get_option($name) ?>" id="<?php echo $name; ?>" class="large-text code" rows="2" cols="50"  />
										</p>
									</fieldset>
									<fieldset>
										<legend class="screen-reader-text"><span><strong>CASA</strong><span style="font-weight:100">GATEWAY</span> Privater Schlüssel</span></legend>
										<?php $name = 'casawp_private_key'; ?>
										<?php $text = '<strong>CASA</strong><span style="font-weight:100">GATEWAY</span> • Private Key'; ?>
										<p><?php echo $text; ?></p>
										<p>
											<input type="text" placeholder="Deaktiviert" name="<?php echo $name ?>" value="<?= get_option($name) ?>" id="<?php echo $name; ?>" class="large-text code" rows="2" cols="50"  />
										</p>
									</fieldset>
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
									$all_languages = array('de' => array('translated_name' => 'Deutsch'));
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