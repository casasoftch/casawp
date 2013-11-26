<?php
//$happy_face_image_url_saved = get_option('$happy_face_image_url_op');

if(isset($_POST['casasync_submit']))  {
       //$happy_face_image_url_saved = $_POST["happy_face_image_url"];
        //update_option( '$happy_face_image_url_op', $happy_face_image_url_saved );
	foreach ($_POST AS $key => $value) {
		if (substr($key, 0, 8) == 'casasync') {
			update_option( $key, $value );
		}
	}

	//checkbox traps (busters)
	$checkbox_traps = array(
		'casasync_sellerfallback_show',
		'casasync_sellerfallback_update',
		'casasync_seller_show',
		'casasync_feedback_update',
		'casasync_feedback_creations',
		'casasync_feedback_edits',
		'casasync_feedback_inquiries',
		'casasync_live_import',

		'casasync_load_bootstrap',
		'casasync_load_bootstrap_css',
		'casasync_load_multiselector',
		'casasync_load_scripts',
		'casasync_load_fancybox',
		'casasync_load_stylesheet',
		'casasync_load_fontawesome',
		'casasync_load_jquery',

		'casasync_share_facebook',

		'casasync_remCat'


	);
	foreach ($checkbox_traps as $trap) {
		if (!isset($_POST[$trap])) {
			update_option( $trap, '0' );
		}
	}
?>

<div class="updated"><p><strong><?php _e('Einstellungen gespeichert..', 'casasync' ); ?></strong></p></div>

<?php  }  ?>

<?php
	if (isset($_GET['do_import']) && !isset($_POST['casasync_submit'])) {
		if (get_option( 'casasync_live_import') == 0) {
			//casasync_import(); //is being done by plubg when that get param exists
			?> <div class="updated"><p><strong><?php _e('Daten wurden importiert..', 'casasync' ); ?></strong></p></div> <?php
		}
	}
?>

<div class="wrap">
    <?php screen_icon('options-general'); ?>
    <h2>CasaSync Optionen</h2>

	<form action="" method="post" id="options_form" name="options_form">

	<h3>Generell</h3>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row">Synchronisation</th>
				<td id="front-static-pages">
					<fieldset>
						<legend class="screen-reader-text"><span>Synchronisation mit Exporter/Marklersoftware</span></legend>
						<?php $name = 'casasync_live_import'; ?>
						<?php $text = 'Änderungen automatisch bei jedem Aufruff überprüffen und updaten.'; ?>
						<p><label>
							<?php
								$url = get_admin_url('', 'admin.php?page=casasync');
								$manually = $url . '&do_import=true';
								$forced = $manually . '&force_all_properties=true&force_last_import=true';
							?>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?> <a href="<?php echo $manually  ?>">manueller Import</a> ∙ <a href="<?php echo $forced  ?>">erzwungener Import</a>
						</label></p>

					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Objektseite</th>
				<td id="front-static-pages">
					<fieldset>
						<legend class="screen-reader-text"><span>Objektseite</span></legend>
						<?php $name = 'casasync_seller_show'; ?>
						<?php $text = 'Verkäufer bei den Objekten anzeigen (Anbieter informationen)'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Verkäufer/Anbieter</th>
				<td id="front-static-pages">
					<fieldset>
						<legend class="screen-reader-text"><span>Verkäufer/Anbieter</span></legend>
						<?php $name = 'casasync_sellerfallback_show'; ?>
						<?php $text = 'Verkäufer/Anbieter (untere angeben) anzeigen wenn beim Objekt kein Verkäufer vorhanden ist'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>

						<?php $name = 'casasync_sellerfallback_update'; ?>
						<?php $text = 'Verkäufer/Anbieter mit dem Exporter/Maklersoftware synchronisieren'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<?php $name = 'casasync_sellerfallback_email_use'; ?>
				<?php $text = 'Wann sollen Anfragen an den Hauptverkäufer/Anbieter versendet werden?'; ?>
				<th scope="row"><?php echo $text ?></th>
				<td id="front-static-pages">
					<fieldset>
						<legend class="screen-reader-text"><span><?php echo $text ?></span></legend>
						<p><label>
								<input name="<?php echo $name ?>" type="radio" value="never" <?php echo (get_option($name) == 'never' ? 'checked="checked"' : ''); ?>> Niemals
							</label></p>
						<p><label>
								<input name="<?php echo $name ?>" type="radio" value="fallback" <?php echo (get_option($name) == 'fallback' ? 'checked="checked"' : ''); ?>> Falls keine Verkäufer E-Mail Adresse beim Objekt angegeben wurde
							</label></p>
						<p><label>
								<input name="<?php echo $name ?>" type="radio" value="always" <?php echo (get_option($name) == 'always' ? 'checked="checked"' : ''); ?>> Immer
							</label></p>

					</fieldset>
				</td>
			</tr>
			<tr valign="top">

				<th scope="row">RemCat ist eine Tranfertechnology die Anfragen per E-Mail mittels standart versendet</th>
				<td id="front-static-pages">
					<fieldset>
						<legend class="screen-reader-text"><span>RemCat ist eine Tranfertechnology die Anfragen per E-Mail mittels standart versendet</span></legend>
						<?php $name = 'casasync_remCat'; ?>
						<?php $text = 'RemCat aktivieren'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>

						<?php $name = 'casasync_remCat_email'; ?>
						<?php $text = 'RemCat Email Adresse'; ?>
						<p>
							<input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span>
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Social</th>
				<td id="front-static-pages">
					<fieldset>
						<legend class="screen-reader-text"><span>Social</span></legend>
						<?php $name = 'casasync_share_facebook'; ?>
						<?php $text = 'Facebook Empfehlen Anzeigen'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>

					</fieldset>
				</td>
			</tr>
			<?php $name = 'casasync_before_content'; ?>
			<?php $text = 'Vor dem Inhalt (html)'; ?>
			<tr>
				<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
				<td><textarea placeholder="<div id='content'>" name="<?php echo $name ?>" id="<?php echo $name; ?>" class="large-text code" rows="2" cols="50"><?php echo stripslashes(get_option($name)); ?></textarea> <span class="description"></span></td>
			</tr>
			<?php $name = 'casasync_after_content'; ?>
			<?php $text = 'Nach dem Inhalt (html)'; ?>
			<tr>
				<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
				<td><textarea placeholder="</div>" name="<?php echo $name ?>" id="<?php echo $name; ?>" class="large-text code" rows="2" cols="50"><?php echo stripslashes(get_option($name)); ?></textarea> <span class="description"></span></td>
			</tr>




		</tbody>
	</table>


	<h3>Verkäufer/Anbieter Adresse</h3>
	<table class="form-table">
		<tbody>



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
		</tbody>
	</table>


	<h3>Verkäufer/Anbieter Angaben</h3>
	<table class="form-table">
		<tbody>


			<?php $name = 'casasync_sellerfallback_legalname'; ?>
			<?php $text = 'Firma Name'; ?>
			<tr>
				<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
				<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
			</tr>

			<?php $name = 'casasync_sellerfallback_email'; ?>
			<?php $text = '<strong>E-Mail Adresse</strong>'; ?>
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

			<?php $name = 'casasync_sellerfallback_phone_direct'; ?>
			<?php $text = 'Direktwahl'; ?>
			<tr>
				<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
				<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
			</tr>

			<?php $name = 'casasync_sellerfallback_phone_central'; ?>
			<?php $text = 'Firma Telefon'; ?>
			<tr>
				<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
				<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
			</tr>

			<?php $name = 'casasync_sellerfallback_phone_mobile'; ?>
			<?php $text = 'Mobile'; ?>
			<tr>
				<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
				<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
			</tr>
		</tbody>
	</table>


	<h3>Technische Feedback Adresse</h3>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row">Optionen</th>
				<td id="front-static-pages">
					<fieldset>
						<legend class="screen-reader-text"><span>Optionen</span></legend>
						<?php $name = 'casasync_feedback_update'; ?>
						<?php $text = 'Mit dem Exporter/Maklersoftware synchronisieren'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>

						<?php $name = 'casasync_feedback_creations'; ?>
						<?php $text = '<strong>Erstellungs-Rückmeldungen</strong> aktivieren'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>

						<?php $name = 'casasync_feedback_edits'; ?>
						<?php $text = '<strong>Berabeitungs-Rückmeldungen</strong> aktivieren'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>

						<?php $name = 'casasync_feedback_inquiries'; ?>
						<?php $text = 'Kopie von allen Anfragen hierehin versenden'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>
					</fieldset>
				</td>
			</tr>

			<?php $name = 'casasync_feedback_gender'; ?>
			<?php $text = 'Titel'; ?>
			<tr>
				<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
				<td>
					<select name="<?php echo $name ?>" id="<?php echo $name ?>">
						<option <?php echo (get_option($name) == 'M' ? 'selected="selected"' : ''); ?> value="M">Herr</option>
						<option <?php echo (get_option($name) == 'F' ? 'selected="selected"' : ''); ?> value="F">Frau</option>
					</select>
				</td>
			</tr>

			<?php $name = 'casasync_feedback_given_name'; ?>
			<?php $text = 'Vorname'; ?>
			<tr>
				<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
				<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
			</tr>

			<?php $name = 'casasync_feedback_family_name'; ?>
			<?php $text = 'Nachname'; ?>
			<tr>
				<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
				<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
			</tr>

			<?php $name = 'casasync_feedback_email'; ?>
			<?php $text = '<strong>E-Mail Adresse</strong>'; ?>
			<tr>
				<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
				<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
			</tr>

			<?php $name = 'casasync_feedback_telephone'; ?>
			<?php $text = 'Telefon'; ?>
			<tr>
				<th><label for="<?php echo $name; ?>"><?php echo $text ?></label></th>
				<td><input name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" value="<?php echo get_option($name); ?>" class="regular-text"> <span class="description"></span></td>
			</tr>

		</tbody>
	</table>

	<?php $name = 'casasync_single_template'; ?>
	<!-- <h3><label for="<?php echo $name; ?>">Vorlage von Objekt-Einzelansicht</h3></label>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th>
							<code><abbr title="id='post-###'">{ids}</abbr></code>
							<br><code><abbr title="class='post-###'">{classes}</abbr></code>
							<br><code><abbr title="Titel von dem Objekt">{title}</abbr></code>
							<br><code><abbr title="Gallerieansicht von allen Fotos">{gallery}</abbr></code>
							<br><code><abbr title="Tab-Fenster mit Grunddaten, Beschreingen, Datenblätter, Pläne usw.">{content}</abbr></code>
							<br><code><abbr title="'Call to Action' Links wie 'Jetzt kontaktieren'">{if_cta}{cta}{end_if_cta}</abbr></code>
							<br><code><abbr title="Falls ein Verkäufer angegeben ist diese hier darstellen">{if_seller}{seller}{end_if_seller}</abbr></code>
							<br><code><abbr title="">{if_share}{share}{end_if_share}</abbr></code>
							<br><code><abbr title="Falls Anfrage E-Mails angegeben sind wird hier ein Kontktformular dargestellt">{if_contactform}{contactform}{end_if_contactform}</abbr></code>
							<br><code><abbr title="Falls eine Kontaktperson angegeben ist wird diese hier dargestellt">{if_salesperson}{salesperson}{end_if_salesperson}</abbr></code>
				</th>
				<td>
				<span class="description">Wenn das Feld leer ist werden die Standarteinstellungen wieder hergestellt</span><textarea name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" class="large-text code" rows="30" cols="50"><?php echo (get_option($name) ? stripslashes(get_option($name)) : file_get_contents(CASASYNC_PLUGIN_DIR . '/single-template-default.txt')); ?></textarea><span class="description"></span></td>
			</tr>
		</tbody>
	</table> -->

	<?php $name = 'casasync_archive_template'; ?>
	<!-- <h3><label for="<?php echo $name; ?>">Vorlage für Objekt-Übersicht</h3></label>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th>
					<dl>
							<dt><code>{paginationTop}</code></dt><dd>Seitenavigation oben</dd>
							<dt><code>{properties}</code></dt><dd>Objektschlaufe mit allen Resultaten</dd>
							<dt><code>{paginationBottom}</code></dt><dd>Seitenavigation unten</dd>
							<dt><code>{filterform}</code></dt><dd>Suchformular um Objektresultate zu filtern</dd>
							<dt><code>{microFilterform}</code></dt><dd></dd>
					</dl>
				</th>
				<td>
				<span class="description">Wenn das Feld leer ist werden die Standarteinstellungen wieder hergestellt</span><textarea name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" class="large-text code" rows="15" cols="50"><?php echo (get_option($name) ? stripslashes(get_option($name)) : file_get_contents(CASASYNC_PLUGIN_DIR . '/archive-template-default.txt')); ?></textarea><span class="description"></span></td>
			</tr>
		</tbody>
	</table> -->
	<?php /* ?>
	<h3><label for="<?php echo $name; ?>">Vorlage für {properties}</h3></label>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row">Größe der Miniaturbilder</th>
				<td>
					<?php $name = 'casasync_archive_thumb_w'; ?>
					<label for="<?php echo $name; ?>">Breite</label>
					<input name="<?php echo $name; ?>" type="number" step="1" min="0" id="thumbnail_size_w" value="<?php echo get_option($name); ?>" class="small-text">
					<?php $name = 'casasync_archive_thumb_h'; ?>
					<label for="<?php echo $name; ?>">Höhe</label>
					<input name="<?php echo $name; ?>" type="number" step="1" min="0" id="thumbnail_size_h" value="<?php echo get_option($name); ?>" class="small-text"><br>
					<?php $name = 'thumbnail_crop'; ?>
					<!-- <input name="<?php echo $name; ?>" type="checkbox" id="thumbnail_crop" value="<?php echo get_option($name); ?>" checked="checked">
					<label for="<?php echo $name; ?>">Beschneide das Miniaturbild auf die exakte Größe (Miniaturbilder sind normalerweise proportional)</label> -->
				</td>
			</tr>
			<tr valign="top">
				<?php $name = 'casasync_archive_single_template'; ?>
				<th>
					<code><abbr title="">{classes}</abbr></code>
					<br><code><abbr title="">{ids}</abbr></code>
					<br><code><abbr title="">{classes}</abbr></code>
					<br><code><abbr title="">{permalink}</abbr></code>
					<br><code><abbr title="">{title}</abbr></code>
					<br><code><abbr title="">{datatable}</abbr></code>
					<br><code><abbr title="">{quicktags}</abbr></code>
					<br><code><abbr title="">{locality}</abbr></code>
					<br><code><abbr title="">{if_thumbnail}{thumbnail}{end_if_thumbnail}</abbr></code>
					<br><code><abbr title="">{!if_thumbnail}{!end_if_thumbnail}</abbr></code>

				</th>
				<td>
				<span class="description">Wenn das Feld leer ist werden die Standarteinstellungen wieder hergestellt</span><textarea name="<?php echo $name ?>" id="<?php echo $name; ?>" type="text" class="large-text code" rows="15" cols="50"><?php echo (get_option($name) ? stripslashes(get_option($name)) : file_get_contents(CASASYNC_PLUGIN_DIR . '/archive-template-single-default.txt')); ?></textarea><span class="description"></span></td>
			</tr>
			<tr valign="top">
				<th scope="row">Template-Vorlagen</th>
				<td>
					<ul>
						<li><a target="blank" href="<?php echo CASASYNC_PLUGIN_URL . '/archive-template-single-default.txt'; ?>">Standart-Ansicht</a></li>
						<li><a target="blank" href="<?php echo CASASYNC_PLUGIN_URL . '/archive-template-single-gallery.txt'; ?>">Galerie-Ansicht</a></li>
					</ul>
				</td>
			</td>
		</tbody>
	</table>
	<?php */ ?>




	<h3>Scripts und Stylesheets</h3>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row">Welche solle geladen werden?</th>
				<td id="front-static-pages">
					<fieldset>
						<legend class="screen-reader-text"><span>Welche solle geladen werden?</span></legend>

						<?php $name = 'casasync_load_jquery'; ?>
						<?php $text = 'casasync_load_jquery'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name, 1 ) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>


						<?php $name = 'casasync_load_bootstrap'; ?>
						<?php $text = 'casasync_load_bootstrap'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name, 1 ) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>

						<?php $name = 'casasync_load_bootstrap_css'; ?>
						<?php $text = 'casasync_load_bootstrap_css'; ?>
						<!-- <p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name, 1 ) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p> -->

						<?php $name = 'casasync_load_multiselector'; ?>
						<?php $text = 'casasync_load_multiselector'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name, 1 ) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>

						<?php $name = 'casasync_load_scripts'; ?>
						<?php $text = 'casasync_load_scripts'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name, 1 ) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>

						<?php $name = 'casasync_load_fancybox'; ?>
						<?php $text = 'casasync_load_fancybox'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name, 1 ) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>

						<?php $name = 'casasync_load_stylesheet'; ?>
						<?php $text = 'casasync_load_stylesheet'; ?>
						<!-- <p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name, 1 ) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p> -->

						<?php $name = 'casasync_load_fontawesome'; ?>
						<?php $text = 'casasync_load_fontawesome'; ?>
						<p><label>
							<input name="<?php echo $name ?>" type="checkbox" value="1" class="tog" <?php echo (get_option($name, 1 ) ? 'checked="checked"' : ''); ?> > <?php echo $text ?>
						</label></p>

					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>

	<p class="submit"><input type="submit" name="casasync_submit" id="submit" class="button button-primary" value="Änderungen übernehmen"></p>


	</form>

</div>