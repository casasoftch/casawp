<?php 
/*
Template Name: Property list
*/
__( 'Property list', 'casawp' );
?>
<?php get_header() ?>

<?php echo stripslashes(get_option('casawp_before_content')); ?>

<div class="container content">
	<div class="casawp-archive">
		<?php if ( have_posts() ): ?>
			<div class="span8 casawp-archive-content">
				<?php while ( have_posts() ) : the_post(); ?>
					<?= $casawp->renderArchiveSingle($post); ?>
				<?php endwhile; ?>
				<?= $casawp->renderArchivePagination() ?>
				<?php wp_reset_query(); ?>
			</div>
		<?php endif; ?>
		<div class="span4 casawp-archive-aside">
			<?php echo $casawp->renderArchiveFilter() ?>
			<?php //wp_tag_cloud(array('taxonomy' => 'casawp_feature')); ?>
		</div>
	</div>
</div>
<?php echo stripslashes(get_option('casawp_after_content')); ?>

<?php get_footer() ?>