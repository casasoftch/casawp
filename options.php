<?php
	if(isset($_POST['casasync_submit'])) {
		$saved_custom_categories = array();
		foreach ($_POST AS $key => $value) {
			if (substr($key, 0, 7) == 'custom_') {
				$parts = explode('/', $key);
				$saved_custom_categories[$parts[0]][$parts[1]] = $value;
				if (!array_key_exists('show', $saved_custom_categories[$parts[0]])) {
					$saved_custom_categories[$parts[0]]['show'] = '0';
				}
			}
			if (substr($key, 0, 8) == 'casasync') {
				update_option( $key, $value );
			}
		}

		if (count($saved_custom_categories) > 0) {
			update_option('casasync_custom_category_translations', $saved_custom_categories);
		}

		$current = isset($_GET['tab']) ? $_GET['tab'] : 'general';
		switch ($current) {
			case 'appearance':
				$checkbox_traps = array(
					'casasync_load_css',
					'casasync_load_bootstrap_scripts',
					'casasync_load_fancybox',
					'casasync_load_chosen',
					'casasync_load_googlemaps'
				);
				break;
			case 'singleview':
				$checkbox_traps = array(
					'casasync_share_facebook',
					'casasync_share_googleplus',
					'casasync_share_twitter',
					'casasync_single_show_number_of_rooms',
			        'casasync_single_show_surface_usable',
			        'casasync_single_show_surface_living',
			        'casasync_single_show_surface_property',
			        'casasync_single_show_floor',
			        'casasync_single_show_number_of_floors',
			        'casasync_single_show_year_built',
			        'casasync_single_show_year_renovated',
			        'casasync_single_show_carousel_indicators',
			        'casasync_single_show_availability',
			        'casasync_sellerfallback_show_organization',
					'casasync_sellerfallback_show_person_view',
				);
				break;
			case 'archiveview':
				$checkbox_traps = array(
					'casasync_show_sticky_properties',
					'casasync_hide_sticky_properties_in_main',
					'casasync_archive_show_street_and_number',
					'casasync_archive_show_zip',
					'casasync_archive_show_location',
					'casasync_archive_show_number_of_rooms',
			        'casasync_archive_show_surface_usable',
			        'casasync_archive_show_surface_living',
			        'casasync_archive_show_surface_property',
			        'casasync_archive_show_floor',
			        'casasync_archive_show_number_of_floors',
			        'casasync_archive_show_year_built',
			        'casasync_archive_show_year_renovated',
			        'casasync_archive_show_price',
			        'casasync_archive_show_excerpt',
			        'casasync_archive_show_availability',
			        'casasync_archive_show_thumbnail_size_crop'
				);
				break;
			case 'contactform':
				$checkbox_traps = array(
					'casasync_show_email_organisation',
					'casasync_show_email_person_view',
					'casasync_form_firstname_required',
					'casasync_form_lastname_required',
					'casasync_form_street_required',
					'casasync_form_postalcode_required',
					'casasync_form_locality_required',
					'casasync_form_phone_required',
					'casasync_form_email_required',
					'casasync_form_message_required'
				);
				break;
			case 'general':
			default:
				$checkbox_traps = array(
					'casasync_live_import',
					'casasync_sellerfallback_email_use',
					'casasync_remCat',
					'casasync_remCat_email',
					'casasync_before_content',
					'casasync_after_content'
				);
				break;
		}

		//reset
		if(get_option('casasync_request_per_remcat') == false) {
			update_option('casasync_remCat_email', '');
		}
		if(get_option('casasync_request_per_mail_fallback') == false) {
			update_option('casasync_request_per_mail_fallback_value', '');
		}

		foreach ($checkbox_traps as $trap) {
			if (!isset($_POST[$trap])) {
				update_option( $trap, '0' );
			}
		}
		echo '<div class="updated"><p><strong>' . __('Einstellungen gespeichert..', 'casasync' ) . '</strong></p></div>';
	}


	if (isset($_GET['do_import']) && !isset($_POST['casasync_submit'])) {
		if (get_option( 'casasync_live_import') == 0) {
			?> <div class="updated"><p><strong><?php _e('Daten wurden importiert..', 'casasync' ); ?></strong></p></div> <?php
		}
	}
?>


<hr>

<div class="wrap">
	<?php
		// Tabs
		$tabs = array(
			'general'     => 'Generell',
			'appearance'  => 'Design',
			'singleview'  => 'Einzelansicht',
			'archiveview' => 'Archivansicht',
			'contactform' => 'Kontaktformular'
		); 
	    echo screen_icon('options-general');
	    echo '<h2 class="nav-tab-wrapper">';
	    $current = isset($_GET['tab']) ? $_GET['tab'] : 'general';
	    foreach( $tabs as $tab => $name ){
	        $class = ( $tab == $current ) ? ' nav-tab-active' : '';
	        echo "<a class='nav-tab$class' href='?page=casasync&tab=$tab'>$name</a>";
	        
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
								<th scope="row">Stylesheet</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>Stylesheet</span></legend>
										<?php $name = 'casasync_load_css'; ?>
										<?php $text = 'Bootstrap Stylesheet auswählen'; ?>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="none" <?php echo (get_option($name) == 'none' ? 'checked="checked"' : ''); ?>> Kein Stylesheet
										</label>
										<br>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="bootstrapv2" <?php echo (get_option($name) == 'bootstrapv2' ? 'checked="checked"' : ''); ?>> Bootstrap Version 2
										</label>
										<br>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="bootstrapv3" <?php echo (get_option($name) == 'bootstrapv3' ? 'checked="checked"' : ''); ?>> Bootstrap Version 3
										</label>
										<br>
									</fieldset>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Scripts</th>
								<td class="front-static-pages">
									<fieldset>
										<?php $name = 'casasync_load_bootstrap_scripts'; ?>
										<?php $text = 'Bootstrap'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<?php $name = 'casasync_load_fancybox'; ?>
										<?php $text = 'Fancybox'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<?php $name = 'casasync_load_chosen'; ?>
										<?php $text = 'Chosen'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<?php $name = 'casasync_load_googlemaps'; ?>
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
										<?php $name = 'casasync_share_facebook'; ?>
										<?php $text = 'Facebook'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<?php $name = 'casasync_share_googleplus'; ?>
										<?php $text = 'Google+'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<?php $name = 'casasync_share_twitter'; ?>
										<?php $text = 'Twitter'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
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
										<?php $name = 'casasync_single_show_number_of_rooms'; ?>
										<?php $text = 'Anzahl Zimmer'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_single_show_number_of_rooms_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_single_show_surface_usable'; ?>
										<?php $text = 'Nutzfläche'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_single_show_surface_usable_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_single_show_surface_living'; ?>
										<?php $text = 'Wohnfläche'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_single_show_surface_living_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_single_show_surface_property'; ?>
										<?php $text = 'Grundstücksfläche'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_single_show_surface_property_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_single_show_floor'; ?>
										<?php $text = 'Etage'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_single_show_floor_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_single_show_number_of_floors'; ?>
										<?php $text = 'Anzahl Etage'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_single_show_number_of_floors_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_single_show_year_built'; ?>
										<?php $text = 'Baujahr'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_single_show_year_built_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_single_show_year_renovated'; ?>
										<?php $text = 'Letzte Renovation'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_single_show_year_renovated_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_single_show_availability'; ?>
										<?php $text = 'Verfügbarkeit'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_single_show_availability_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<h3>Karte</h3>
						<?php echo $table_start; ?>
							<?php $name = 'casasync_single_use_zoomlevel'; ?>
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
										<?php $name = 'casasync_single_show_carousel_indicators'; ?>
										<?php $text = 'Navigation mit Kreisen'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
						<?php echo $table_end; ?>
						<?php echo $table_start; ?>
						<h3>Standard Daten</h3>
						<p>Nachfolgend können Sie Standardwerte für die Firma, Kontaktperson und Kontaktemail definieren.</p>
						<?php echo $table_start; ?>
							<?php $name = 'casasync_inquiryfallback_person_email'; ?>
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
										<?php $name = 'casasync_sellerfallback_show_organization'; ?>
										<?php $text = 'Organisation anzeigen, wenn beim Objekt keine vorhanden ist.'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
							<?php $name = 'casasync_sellerfallback_legalname'; ?>
							<?php $text = 'Organisation Name'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casasync_sellerfallback_address_street'; ?>
							<?php $text = 'Strasse'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casasync_sellerfallback_address_postalcode'; ?>
							<?php $text = 'PLZ'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casasync_sellerfallback_address_locality'; ?>
							<?php $text = 'Ort'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casasync_sellerfallback_address_region'; ?>
							<?php $text = 'Kanton'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casasync_sellerfallback_address_country'; ?>
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
							<?php $name = 'casasync_sellerfallback_email'; ?>
							<?php $text = 'E-Mail Adresse'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casasync_sellerfallback_phone_central'; ?>
							<?php $text = 'Telefon Geschäft'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casasync_sellerfallback_fax'; ?>
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
										<?php $name = 'casasync_sellerfallback_show_person_view'; ?>
										<?php $text = 'Kontaktperson anzeigen, wenn beim Objekt keine vorhanden ist.'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
							<?php $name = 'casasync_salesperson_fallback_gender'; ?>
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
							<?php $name = 'casasync_salesperson_fallback_givenname'; ?>
							<?php $text = 'Vorname'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casasync_salesperson_fallback_familyname'; ?>
							<?php $text = 'Nachname'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casasync_salesperson_fallback_function'; ?>
							<?php $text = 'Funktion'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casasync_salesperson_fallback_email'; ?>
							<?php $text = 'E-Mail Adresse'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casasync_salesperson_fallback_phone_direct'; ?>
							<?php $text = 'Direktwahl'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
							<?php $name = 'casasync_salesperson_fallback_phone_mobile'; ?>
							<?php $text = 'Mobile'; ?>
							<tr>
								<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
								<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
							</tr>
						<?php echo $table_end; ?>
					<?php
					break;
				case 'archiveview':
					?>
					<?php echo $table_start; ?>
						<tr valign="top">
							<th scope="row">Sortierung</th>
							<td>


								<?php $name = 'casasync_archive_orderby'; ?>
								<?php $text = 'Sortierung'; ?>
								<select name="<?php echo $name ?>" id="<?php echo $name ?>">
									<option <?php echo (get_option($name)  == 'date' ? 'selected="selected"' : ''); ?> value="date">Datum</option>
									<option <?php echo (get_option($name)  == 'title' ? 'selected="selected"' : ''); ?> value="title">Titel</option>
									<option <?php echo (get_option($name)  == 'price' ? 'selected="selected"' : ''); ?> value="price">Preis</option>
									<option <?php echo (get_option($name)  == 'location' ? 'selected="selected"' : ''); ?> value="location">Ort</option>
									<option <?php echo (get_option($name)  == 'menu_order' ? 'selected="selected"' : ''); ?> value="menu_order">Eigene Reihenfolge</option>
								</select>
								<?php $name = 'casasync_archive_order'; ?>
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
										<?php $name = 'casasync_show_sticky_properties'; ?>
										<?php $text = 'Speziel ausgewiesen'; ?>
										<p><label>
											<?php
												$url = get_admin_url('', 'admin.php?page=casasync');
												$manually = $url . '&do_import=true';
												$forced = $manually . '&force_all_properties=true&force_last_import=true';
											?>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label></p>
									</fieldset>
									<fieldset>
										<legend class="screen-reader-text"><span></span></legend>
										<?php $name = 'casasync_hide_sticky_properties_in_main'; ?>
										<?php $text = 'in der Hauptliste verstecken'; ?>
										<p><label>
											<?php
												$url = get_admin_url('', 'admin.php?page=casasync');
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
								<?php $name = 'casasync_archive_show_thumbnail_size_w'; ?>
								<?php $text = 'Breite'; ?>
								<label for="<?php echo $name; ?>"><?php echo $text; ?></label>
								<input name="<?php echo $name; ?>" name="<?php echo $name; ?>" type="number" step="1" min="0" value="<?php echo get_option($name); ?>" class="small-text">
								<?php $name = 'casasync_archive_show_thumbnail_size_h'; ?>
								<?php $text = 'Höhe'; ?>
								<label for="<?php echo $name; ?>"><?php echo $text; ?></label>
								<input name="<?php echo $name; ?>" id="<?php echo $name; ?>" type="number" step="1" min="0" value="<?php echo get_option($name); ?>" class="small-text"><br>
								<?php $name = 'casasync_archive_show_thumbnail_size_crop'; ?>
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
										<?php $name = 'casasync_archive_show_street_and_number'; ?>
										<?php $text = 'Strasse + Nr'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_street_and_number_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_archive_show_zip'; ?>
										<?php $text = 'PLZ'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<br>
										<?php $name = 'casasync_archive_show_location'; ?>
										<?php $text = 'Ort'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_location_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_archive_show_number_of_rooms'; ?>
										<?php $text = 'Anzahl Zimmer'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_number_of_rooms_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_archive_show_surface_usable'; ?>
										<?php $text = 'Nutzfläche'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_surface_usable_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_archive_show_surface_living'; ?>
										<?php $text = 'Wohnfläche'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_surface_living_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_archive_show_surface_property'; ?>
										<?php $text = 'Grundstücksfläche'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_surface_property_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_archive_show_floor'; ?>
										<?php $text = 'Etage'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_floor_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_archive_show_number_of_floors'; ?>
										<?php $text = 'Anzahl Etage'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_number_of_floors_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_archive_show_year_built'; ?>
										<?php $text = 'Baujahr'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_year_built_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_archive_show_year_renovated'; ?>
										<?php $text = 'Letzte Renovation'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_year_renovated_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_archive_show_availability'; ?>
										<?php $text = 'Verfügbarkeit'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_availability_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_archive_show_price'; ?>
										<?php $text = 'Preis'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_price_order'; ?>
										<label>
											<input name="<?php echo $name ?>" type="text" value="<?php echo get_option($name); ?>" class="small-text">
										</label>
										<br>
										<?php $name = 'casasync_archive_show_excerpt'; ?>
										<?php $text = 'Auszug'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
										</label>
										<?php $name = 'casasync_archive_show_excerpt_order'; ?>
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
						<h3>Email</h3>
						<?php echo $table_start; ?>
							<tr valign="top">
								<th scope="row">Anfrage an Kontakt-E-Mail Adresse vom Objekt senden?</th>
								<td class="front-static-pages contactform-tab">
									<fieldset>
										<legend class="screen-reader-text"><span>Anfrage an Kontakt-E-Mail Adresse vom Objekt senden?</span></legend>
										<input name="casasync_request_per_mail_value" type="text" value="Vom Objekt definiert" class="regular-text" readonly="readonly" disabled="disabled"> 
										<?php $name = 'casasync_request_per_mail'; ?>
										<?php $text = 'Ja'; ?>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<?php $text = 'Nein'; ?>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="0" <?php echo (get_option($name) == '0' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Anfrage an REMCat E-Mail Adresse senden?</th>
								<td class="front-static-pages contactform-tab">
									<fieldset>
										<legend class="screen-reader-text"><span>Anfrage an REMCat E-Mail Adresse senden?</span></legend>
										<?php $name = 'casasync_remCat_email'; ?>
											<input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text">
										<?php $name = 'casasync_request_per_remcat'; ?>
										<?php $text = 'Ja'; ?>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<?php $text = 'Nein'; ?>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="0" <?php echo (get_option($name) == '0' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
									</fieldset>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Weitere Empfänger festlegen</th>
								<td class="front-static-pages contactform-tab">
									<fieldset>
										<legend class="screen-reader-text"><span>Weitere Empfänger festlegen</span></legend>
										<?php $name = 'casasync_request_per_mail_fallback_value'; ?>
											<input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text">
										<?php $name = 'casasync_request_per_mail_fallback'; ?>
										<?php $text = 'Immer'; ?>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="always" <?php echo (get_option($name) == 'always' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<?php $text = 'Nein'; ?>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="0" <?php echo (get_option($name) == '0' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<?php $text = 'Fallback'; ?>
										<label>
											<input name="<?php echo $name ?>" type="radio" value="fallback" <?php echo (get_option($name) == 'fallback' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>

										</label>
										<br>
										<p><span class="description">Falls Fallback ausgewählt ist, wird nur eine E-Mail gesendet, falls die Kontakt-E-Mail Adresse im Objekt nicht hinterlegt ist.</span><p>
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
										<?php $name = 'casasync_show_email_organisation'; ?>
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
										<?php $name = 'casasync_show_email_person_view'; ?>
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
										<?php $name = 'casasync_form_firstname_required'; ?>
										<?php $text = 'Vorname'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>Nachname</span></legend>
										<?php $name = 'casasync_form_lastname_required'; ?>
										<?php $text = 'Nachname'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>Strasse</span></legend>
										<?php $name = 'casasync_form_street_required'; ?>
										<?php $text = 'Strasse'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>PLZ</span></legend>
										<?php $name = 'casasync_form_postalcode_required'; ?>
										<?php $text = 'PLZ'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>Ort</span></legend>
										<?php $name = 'casasync_form_locality_required'; ?>
										<?php $text = 'Ort'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>Telefon</span></legend>
										<?php $name = 'casasync_form_phone_required'; ?>
										<?php $text = 'Telefon'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>E-Mail</span></legend>
										<?php $name = 'casasync_form_email_required'; ?>
										<?php $text = 'E-Mail'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
										<br>
										<legend class="screen-reader-text"><span>Nachricht</span></legend>
										<?php $name = 'casasync_form_message_required'; ?>
										<?php $text = 'Nachricht'; ?>
										<label>
											<input name="<?php echo $name ?>" type="checkbox" value="1" <?php echo (get_option($name) == '1' ? 'checked="checked"' : ''); ?>> <?php echo $text ?>
										</label>
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
								<th scope="row">Synchronisation mit Exporter/Marklersoftware</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>Synchronisation mit Exporter/Marklersoftware</span></legend>
										<?php $name = 'casasync_live_import'; ?>
										<?php $text = 'Änderungen automatisch bei jedem Aufruff überprüffen und updaten.'; ?>
										<p><label>
											<?php
												$url = get_admin_url('', 'admin.php?page=casasync');
												$manually = $url . '&do_import=true';
												$force_last = $manually . '&force_last_import=true';
												$forced = $manually . '&force_all_properties=true&force_last_import=true';
											?>
											<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?> <a href="<?php echo $manually  ?>">manueller Import</a> ∙ <a href="<?php echo $force_last  ?>">data-done.xml import</a> ∙ <a href="<?php echo $forced  ?>">erzwungener Import</a>
										</label></p>
									</fieldset>
								</td>
							</tr>
							<tr valign="top">
								<th scrope="row">HTML einfügen</th>
								<td class="front-static-pages">
									<fieldset>
										<legend class="screen-reader-text"><span>Vor dem Plugin</span></legend>
										<?php $name = 'casasync_before_content'; ?>
										<?php $text = 'Vor dem Inhalt'; ?>
										<p><?php echo $text; ?></p>
										<p><label>
											<textarea placeholder="<div id='content'>" name="<?php echo $name ?>" id="<?php echo $name; ?>" class="large-text code" rows="2" cols="50"><?php echo stripslashes(get_option($name)); ?></textarea> 
										</label></p>
									</fieldset>
									<fieldset>
										<legend class="screen-reader-text"><span>Nach dem Plugin</span></legend>
										<?php $name = 'casasync_after_content'; ?>
										<?php $text = 'Nach dem Inhalt'; ?>
										<p><?php echo $text; ?></p>
										<p><label>
											<textarea placeholder="</div>" name="<?php echo $name ?>" id="<?php echo $name; ?>" class="large-text code" rows="2" cols="50"><?php echo stripslashes(get_option($name)); ?></textarea> 
										</label></p>
									</fieldset>
								</td>
							</tr>

							<?php
								$all_categories = get_categories(array('taxonomy' => 'casasync_category'));
								$custom_categories = array();
								$i=0;
								foreach ($all_categories as $key => $category) {
									if ( substr($category->slug, 0, 7) == 'custom_') {
										$custom_categories[$i]['name'] = $category->name;
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

																$get_saved_custom_categories = get_option('casasync_custom_category_translations');
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
		<p class="submit"><input type="submit" name="casasync_submit" id="submit" class="button button-primary" value="Änderungen übernehmen"></p>
	</form>