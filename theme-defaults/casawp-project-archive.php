<?php 
/*
Template Name: Project list
*/
__( 'Project list', 'casawp' );
?>
<?php get_header() ?>

<?php echo stripslashes(get_option('casawp_before_content')); ?>

<div class="casawp-project-archive">
	<section role="main" class="section main-content-section">
		<div class="container">

			<div class="row">
				<div class="col-md-16 col-md-offset-8 col-sm-20 text-only reveal animated fadeIn">
					<header class="section-heading h1-grey limited">
						<h1>Projekte im Angebot</h1>
					</header>
				</div>
			</div>


		</div>
	</section>
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