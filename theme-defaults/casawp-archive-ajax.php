<div id="ajaxTarget" data-archivelink="<?= str_replace('&ajax=archive', '', $_SERVER['REQUEST_URI'])  ?>">
	<?php if ( have_posts() ): ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<?= $casawp->renderArchiveSingle($post); ?>
		<?php endwhile; ?>
		<?= $casawp->renderArchivePagination() ?>
		<?php wp_reset_query(); ?>
	<?php endif; ?>
</div>
