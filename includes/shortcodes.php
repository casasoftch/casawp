<?php
/**
 * Shortcode functions
 **/


//[casasync_featured ids="16452_288767, 16452_38858, 16452_288737"]
function casasync_featured_func( $atts ) {
	extract( shortcode_atts( array(
		'ids' => '',
		'max' => 3,
		'cols' => 3,
		'type' => false
	), $atts ) );
	$args = array(
		'post_type' => 'casasync_property',
		'posts_per_page' => $max
	);
	if ($ids) {
		$args['meta_query'] = array(
			array(
				'key' => 'casasync_id',
				'value' => explode(',', str_replace(' ', '', $ids)),
				'compare' => 'IN'
			),
		);
	} elseif ($type) {
		$args['casasync_salestype'] = $type;
	}
	$the_query = new WP_Query( $args );
	$archive_single_view = 'featured';
	$items = array();
	while ( $the_query->have_posts() ) :
		$the_query->the_post();
		ob_start();
		include(CASASYNC_PLUGIN_DIR.'archive_single.php');
		$items[] = ob_get_contents();ob_end_clean();
	endwhile;
	
	wp_reset_postdata();
	$i = 0;
	$return = "<div class='row-fluid'>";
	foreach ($items as $item) {
		if ($i % $cols == 0 && $i != 0) {
			$return .= "</div><div class='row-fluid'>";
		}
		$return .= '<div class="span' . ((int) (12/$cols)) . '">' . $item . '</div>';
		$i++;
	}
	$return .= "</div>";
	return $return;
}
add_shortcode( 'casasync_featured', 'casasync_featured_func' );
