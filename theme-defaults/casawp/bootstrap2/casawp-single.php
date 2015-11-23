<?php get_header() ?>

<?php echo stripslashes(get_option('casawp_before_content')); ?>

<div class="container content">
	<div class="casawp-single">
		<?php while ( have_posts() ) : the_post(); ?>
			<?= $casawp->renderSingle($post); ?>
		<?php endwhile; ?>
	</div>
</div>

<?php echo stripslashes(get_option('casawp_after_content')); ?>

<?php get_footer() ?>