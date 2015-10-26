<?php 
/*
Template Name: Property list
*/
__( 'Property list', 'casasync' );
?>
<?php get_header() ?>

<?php echo stripslashes(get_option('casasync_before_content')); ?>

<div class="casasync-archive">
	<?php if ( have_posts() ): ?>
		<div class="col-md-8 casasync-archive-list">
			<?php while ( have_posts() ) : the_post(); ?>
				<?= $casasync->renderArchiveSingle($post); ?>
			<?php endwhile; ?>
			<?= $casasync->renderArchivePagination() ?>
			<?php wp_reset_query(); ?>
		</div>
	<?php endif; ?>
	<div class="col-md-4 casasync-archive-filter">
		<?php echo $casasync->renderArchiveFilter() ?>
		<?php 
			$tags = wp_tag_cloud(array('taxonomy' => 'casasync_feature')); 
		?>
	</div>
</div>

<?php echo stripslashes(get_option('casasync_after_content')); ?>

<?php get_footer() ?>