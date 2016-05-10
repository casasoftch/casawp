<?php 
/*
Template Name: Project list
*/
__( 'Project list', 'casawp' );
?>
<?php get_header() ?>

<?php echo stripslashes(get_option('casawp_before_content')); ?>

<div class="casawp-project-archive">
	<?php if ( have_posts() ): ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<?= $casawp->renderProjectArchiveSingle($post); ?>
		<?php endwhile; ?>
		<?= $casawp->renderArchivePagination() ?>
		<?php wp_reset_query(); ?>
	<?php endif; ?>
</div>

<?php echo stripslashes(get_option('casawp_after_content')); ?>

<?php get_footer() ?>