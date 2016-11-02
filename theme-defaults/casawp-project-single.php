<?php get_header() ?>

<?php echo stripslashes(get_option('casawp_before_content')); ?>
<div class="casawp-project-single">
	<?php if ( have_posts() ): ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<?php $project = $casawp->prepareProject($post) ?>
			<div class="container">
				<header class="section-heading h1-grey limited">
					<h1>
						<?= get_the_title() ?>
					</h1>
					<?= $project->getContentParts()[0] ?>
				</header>
			</div>
			<div class="container">
				<?= $casawp->renderProjectSingle($post); ?>
			</div>
		<?php endwhile; ?>
	<?php endif ?>
</div>

<?php echo stripslashes(get_option('casawp_after_content')); ?>

<?php get_footer() ?>