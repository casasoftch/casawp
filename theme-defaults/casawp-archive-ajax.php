<div id="ajaxTarget">
	<?php if ( have_posts() ): ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<?= $casawp->renderArchiveSingle($post); ?>
		<?php endwhile; ?>
		<?= $casawp->renderArchivePagination() ?>
		<?php wp_reset_query(); ?>
	<?php endif; ?>
</div>
