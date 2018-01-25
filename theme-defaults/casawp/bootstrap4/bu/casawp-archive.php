<?php 
/*
Template Name: Property list
*/
__( 'Property list', 'casawp' );
?>
<?php get_header() ?>

<?php echo stripslashes(get_option('casawp_before_content')); ?>

<div class="container">
	<div class="casawp-archive row">
		<?php if ( have_posts() ): ?>
			<div class="casawp-archive-list col-md-8">
				<?php while ( have_posts() ) : the_post(); ?>
					<?= $casawp->renderArchiveSingle($post); ?>
				<?php endwhile; ?>
				<?= $casawp->renderArchivePagination() ?>
				<?php wp_reset_query(); ?>
			</div>
		<?php endif; ?>
		<div class="casawp-archive-filter col-md-4">
			<?php echo $casawp->renderArchiveFilter() ?>
			<?php //wp_tag_cloud(array('taxonomy' => 'casawp_feature')); ?>
		</div>
	</div>
</div>
<?php echo stripslashes(get_option('casawp_after_content')); ?>

<?php get_footer() ?>