<?php get_header(); ?>

		<?php /******************* {paginationTop}  *************/ ?>
		<?php $the_paginationTop = false; ?>
		<?php if ( $wp_query->max_num_pages > 1 && 0 == 1 ) : ?>
			<?php ob_start(); ?>
			<nav id="nav-above" class="navigation row-fluid">
				<div class="nav-previous span6"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older posts', 'hegglin' ) ); ?></div>
				<div class="nav-next span6"><?php previous_posts_link( __( 'Newer posts <span class="meta-nav">&rarr;</span>', 'hegglin' ) ); ?></div>
			</nav><!-- #nav-above -->
			<?php $the_paginationTop = ob_get_contents();ob_end_clean(); ?>
		<?php endif; ?>


		<?php /******************* {properties}  *************/ ?>
		<?php $the_properties = false; ?>
		<?php ob_start(); ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<?php include(CASASYNC_PLUGIN_DIR.'archive_single.php'); ?>
		<?php endwhile; // End the loop. Whew. ?>
		<?php $the_properties = ob_get_contents();ob_end_clean(); ?>


		<?php /* If there are no posts to display, such as an empty archive page */ ?>
		<?php if ( ! have_posts() ) : ?>
			<?php ob_start(); ?>
			<article id="post-0" class="post error404 not-found">
				<h1 class="entry-title"><?php _e( 'Not Found', 'hegglin' ); ?></h1>
				<div class="entry-content">
					<p><?php _e( 'Apologies, but no results were found for the requested archive. Perhaps searching will help find a related post.', 'hegglin' ); ?></p>
					<?php get_search_form(); ?>
				</div><!-- .entry-content -->
			</article><!-- #post-0 -->
			<?php $the_properties = ob_get_contents();ob_end_clean(); ?>
		<?php endif; ?>
		


		<?php /******************* {paginationBottom}  *************/ ?>
		<?php $the_paginationBottom = false; ?>
		<?php if (  $wp_query->max_num_pages > 1 ) : ?>
			<?php ob_start(); ?>
			<nav id="nav-below" class="casasync-nav-below navigation row-fluid">
				<div class="nav-next span6">
					<?php if (get_previous_posts_link()): ?>
						<div class="btn btn-small"><?php previous_posts_link( __( '&larr; Page back', 'casasync' ) ); ?></div>
					<?php endif ?>
				</div>
				<div class="nav-previous span6">
					<?php if (get_next_posts_link()): ?>
						<div class="btn btn-small"><?php next_posts_link( __( 'Page forward &rarr;', 'casasync' ) ); ?></div>
					<?php endif ?>
				</div>
			</nav><!-- #nav-below -->
			<?php $the_paginationBottom = ob_get_contents();ob_end_clean(); ?>
		<?php endif; ?>


		<?php /******************* {microFilterform}  *************/ ?>
		<?php ob_start(); ?>
		<form action="<?php echo get_post_type_archive_link( 'casasync_property' ); ?>" class="form form-horizontal casasync-micro-filterform">
			<?php 
				//if permalinks are off
				if ( get_option('permalink_structure') == '' ) {
					echo '<input type="hidden" name="post_type" value="casasync_property" />';
				}

			 ?>


			<?php 

				$categories = array();
				foreach ($wp_query->tax_query->queries as $tax_query) {
					if ($tax_query['taxonomy'] == 'casasync_category') {
						$categories = $tax_query['terms'];
					}
				}
			 ?>
			<select name="casasync_category_s[]" multiple class="multiselect" data-empty="<?php echo __('Choose category','casasync') ?>">
			<?php 
				$terms = get_terms('casasync_category');
				foreach ($terms as $term) {
					echo "<option value='" . $term->slug . "' " . (in_array($term->slug, $categories) ? 'SELECTED' : '') . ">" . casasync_convert_categoryKeyToLabel($term->name) . ' (' . $term->count . ')' . "</option>";
				}
			 ?>
			</select>
			<?php 
				if (isset($wp_query->query_vars['casasync_location'])) {
					$locations = explode(',', $wp_query->query_vars['casasync_location']);
				} else {
					$locations = array();
				}
			 ?>
			<select name="casasync_location_s[]" multiple class="multiselect" data-empty="<?php echo __('Choose locality','casasync') ?>">
			<?php 
				$locations = array();
				foreach ($wp_query->tax_query->queries as $tax_query) {
					if ($tax_query['taxonomy'] == 'casasync_location') {
						$locations = $tax_query['terms'];
					}
				}


				
				$terms_lvl1 = get_terms('casasync_location',array('parent'=>0));
				$no_child_lvl1 = '';
				$no_child_lvl2 = '';
				foreach ($terms_lvl1 as $term) {
					$terms_lvl1_has_children = false;
					
					
					$terms_lvl2 = get_terms('casasync_location',array('parent'=>$term->term_id));
					foreach ($terms_lvl2 as $term2) {
						$terms_lvl1_has_children = true;
						
						$terms_lvl3 = get_terms('casasync_location',array('parent' => $term2->term_id));
						$store = '';
						$terms_lvl2_has_children = false;
						foreach ($terms_lvl3 as $term3) {
							$terms_lvl2_has_children = true;
							$store .= "<option class='lvl3' value='" . $term3->slug . "' " . (in_array($term3->slug, $locations) ? 'SELECTED' : '') . ">" . '' . $term3->name . ' (' . $term3->count . ')' . "</option>";
						}
						if ($terms_lvl2_has_children) {
							echo "<optgroup label='" . $term2->name . "'>";
							echo $store;
							echo "</optgroup>";
						} else {
							//must be another country?
							$otherCountry[$term->name][] = $term2;
						}
					}

					//list all other countries in seperate optgroup
					foreach ( $otherCountry as $countryCode => $country ) {
						echo "<optgroup label='" . countrycode_to_countryname($countryCode)  . "''>";
						foreach ( $country as $location ) {
							echo "<option class='lvl2' value='" . $location->slug . "' " . (in_array($location->slug, $locations) ? 'SELECTED' : '') . ">" . '' . $location->name . ' (' . $location->count . ')' . "</option>";		
						}
						echo "</optgroup>";
					}	

					if (!$terms_lvl1_has_children) {
						$no_child_lvl1 .=  "<option value='" . $term->slug . "' " . (in_array($term->slug, $locations) ? 'SELECTED' : '') . ">" . $term->name . ' (' . $term->count . ')' . "</option>";
					}
				}
				if ($no_child_lvl1) {
					echo "<optgroup label='Sonstige Ortschaften'>";
					echo $no_child_lvl1;
					echo "</optgroup>";
				}
				
			 ?>
			</select>

			<?php 
				$casasync_salestype = get_terms('casasync_salestype');
				$salestype = '';
				$i = 0;
				$salestype = '';
				//echo "<label><input name='casasync_salestype' type='radio' group='salestype'  value='' /> Alle</label>";
				foreach ($casasync_salestype as $term) {
					$i++;
					
					if (isset($wp_query->query_vars['casasync_salestype'])) {
						$cur_basis = explode(',', $wp_query->query_vars['casasync_salestype'] );
					} else {
						$cur_basis = array();
					}
					if (in_array($term->slug, $cur_basis)) {
						echo '<input type="hidden" name="casasync_salestype_s[]" value="'.$term->slug.'" /> ';
					}
					//$salestype .= "<option group='salestype' " . (in_array($term->slug, $cur_basis) ? 'SELECTED' : '') . " value='" . $term->slug . "' /> " . $term->name . "</option>";
				}
				if ($i > 1) {
					//echo '<select name="casasync_salestype">' . $salestype . '</select>';
				} else {

				}




			 ?>

			 <?php 

				$salestypes = array();
				foreach ($wp_query->tax_query->queries as $tax_query) {
					if ($tax_query['taxonomy'] == 'casasync_salestype') {
						$salestypes = $tax_query['terms'];
					}
				}
			 ?>
			<?php 
				$terms = get_terms('casasync_salestype');
				if (count($terms) > 1) {
					echo '<select name="casasync_salestype_s[]" multiple class="multiselect" data-empty="Angebot wählen">';
					foreach ($terms as $term) {
						echo "<option value='" . $term->slug . "' " . (in_array($term->slug, $salestypes) ? 'SELECTED' : '') . ">" . __(ucfirst($term->name),'casasync') . ' (' . $term->count . ')' . "</option>";
					}
					echo "</select>";
				} elseif(count($terms) == 1){
					//echo '<input type="hidden" name="casasync_salestype_s[]" value="' . $terms[0]->slug . '" />';
				}
				
			 ?>


			<input class="btn btn-primary" type="submit" value="<?php echo __('Search','casasync') ?>" />
		</form>
		<?php $the_microFilterform = ob_get_contents();ob_end_clean(); ?>


		<?php /******************* {filterform}  *************/ ?>
		<?php ob_start(); ?>
		<form action="<?php echo get_post_type_archive_link( 'casasync_property' ); ?>" class="form form-horizontal">
			<?php 
				//if permalinks are off
				if ( get_option('permalink_structure') == '' ) {
					echo '<input type="hidden" name="post_type" value="casasync_property" />';
				}

			 ?>

			<?php 
				$casasync_salestype = get_terms('casasync_salestype');
				$salestype = '';
				$i = 0;
				$salestype = '';
				//echo "<label><input name='casasync_salestype' type='radio' group='salestype'  value='' /> Alle</label>";
				foreach ($casasync_salestype as $term) {
					$i++;
					
					if (isset($wp_query->query_vars['casasync_salestype'])) {
						$cur_basis = explode(',', $wp_query->query_vars['casasync_salestype'] );
					} else {
						$cur_basis = array();
					}
					
					$salestype .= "<label><input name='casasync_salestype' type='radio' group='salestype' " . (in_array($term->slug, $cur_basis) ? 'CHECKED' : '') . " value='" . $term->slug . "' /> " . __(ucfirst($term->name),'casasync') . "</label>";
				}
				if ($i > 1) {
					echo $salestype . '<hr class="soften" />';
				}
			 ?>
			
			
			<?php 

				$categories = array();
				foreach ($wp_query->tax_query->queries as $tax_query) {
					if ($tax_query['taxonomy'] == 'casasync_category') {
						$categories = $tax_query['terms'];
					}
				}
			 ?>
			<select name="casasync_category_s[]" multiple class="multiselect"  data-empty="<?php echo __('Choose category','casasync') ?>">
			<?php 
				$terms = get_terms('casasync_category');
				foreach ($terms as $term) {
					echo "<option value='" . $term->slug . "' " . (in_array($term->slug, $categories) ? 'SELECTED' : '') . ">" . casasync_convert_categoryKeyToLabel($term->name) . ' (' . $term->count . ')' . "</option>";
				}
			 ?>
			</select>
			<br>

			<?php 
				if (isset($wp_query->query_vars['casasync_location'])) {
					$locations = explode(',', $wp_query->query_vars['casasync_location']);
				} else {
					$locations = array();
				}
			 ?>
			<select name="casasync_location_s[]" multiple class="multiselect"  data-empty="<?php echo __('Choose locality','casasync') ?>">
			<?php 
				$locations = array();
				foreach ($wp_query->tax_query->queries as $tax_query) {
					if ($tax_query['taxonomy'] == 'casasync_location') {
						$locations = $tax_query['terms'];
					}
				}


				
				$terms_lvl1 = get_terms('casasync_location',array('parent'=>0));
				$no_child_lvl1 = '';
				$no_child_lvl2 = '';
				foreach ($terms_lvl1 as $term) {
					$terms_lvl1_has_children = false;
					
					
					$terms_lvl2 = get_terms('casasync_location',array('parent'=>$term->term_id));
					foreach ($terms_lvl2 as $term2) {
						$terms_lvl1_has_children = true;
						
						$terms_lvl3 = get_terms('casasync_location',array('parent' => $term2->term_id));
						$store = '';
						$terms_lvl2_has_children = false;
						foreach ($terms_lvl3 as $term3) {
							$terms_lvl2_has_children = true;
							$store .= "<option class='lvl3' value='" . $term3->slug . "' " . (in_array($term3->slug, $locations) ? 'SELECTED' : '') . ">" . '' . $term3->name . ' (' . $term3->count . ')' . "</option>";
						}
						if ($terms_lvl2_has_children) {
							echo "<optgroup label='" . $term2->name . "'>";
							echo $store;
							echo "</optgroup>";
						} else {
							echo "<option class='lvl2' value='" . $term2->slug . "' " . (in_array($term2->slug, $locations) ? 'SELECTED' : '') . ">" . '' . $term2->name . ' (' . $term2->count . ')' . "</option>";
						}
					}
					if (!$terms_lvl1_has_children) {
						$no_child_lvl1 .=  "<option value='" . $term->slug . "' " . (in_array($term->slug, $locations) ? 'SELECTED' : '') . ">" . $term->name . ' (' . $term->count . ')' . "</option>";
					}
				}
				if ($no_child_lvl1) {
					echo "<optgroup label='Sonstige Ortschaften'>";
					echo $no_child_lvl1;
					echo "</optgroup>";
				}
				
			 ?>
			</select>
			<br>

			<input class="btn btn-block" type="submit" value="<?php echo __('Search','casasync') ?>" />
		</form>
		<?php $the_filterform = ob_get_contents();ob_end_clean(); ?>



		<?php 
			$template = casasync_get_archive_template();
			$template = casasync_interpret_gettext($template);
			$template = str_replace('{paginationTop}', $the_paginationTop, $template);
			$template = str_replace('{properties}', $the_properties, $template);
			$template = str_replace('{paginationBottom}', $the_paginationBottom, $template);
			$template = str_replace('{filterform}', $the_filterform, $template);
			$template = str_replace('{microFilterform}', $the_microFilterform, $template);
			echo $template;
		?>

<?php get_footer(); ?>
