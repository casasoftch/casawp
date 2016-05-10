<?php get_header() ?>

<?php echo stripslashes(get_option('casawp_before_content')); ?>

<div class="casawp-single">
	<?php while ( have_posts() ) : the_post(); ?>
		SINGLE PROJECT HERE
	<?php endwhile; ?>
</div>

<?php echo stripslashes(get_option('casawp_after_content')); ?>

<?php get_footer() ?>