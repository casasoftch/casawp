<?php get_header() ?>

<?php echo stripslashes(get_option('casasync_before_content')); ?>

<div class="casasync-single">
	<?php while ( have_posts() ) : the_post(); ?>
		<?= $casasync->renderSingle($post); ?>
	<?php endwhile; ?>
</div>

<?php echo stripslashes(get_option('casasync_after_content')); ?>

<?php get_footer() ?>