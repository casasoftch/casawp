<?php get_header() ?>

<?php echo stripslashes(get_option('casawp_before_content')); ?>
<div class="casawp-project-single">
	<?php if ( have_posts() ): ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<?php $project = $casawp->prepareProject($post) ?>
			<section role="main" class="section main-content-section">
				<div class="container">
					<div class="row">
						<div class="col-md-16 col-md-offset-8 col-sm-20 text-only reveal animated fadeIn">
							<header class="section-heading h1-grey limited">
								<h1>
									<?= get_the_title() ?>
								</h1>
								<?= $project->getContentParts()[0] ?>
							</header>
						</div>
					</div>
				</div>
			</section>
			<section class="section parallax-section" data-bleed="50" data-speed="0.7" data-parallax="scroll" data-image-src="http://dev.stalderstalder.ch/wp-content/uploads/2016/02/bannaebnisued_EgEUmWa.jpg.1000x999_q85.jpg"></section>
			
			<?php include(get_template_directory() . '/src/inc/acf-render.php') ?>
			<div class="casawp-archive project-property-list">
				
				<section class="section light">
					<div class="container">
						<div class="row">
							<div class="col-lg-8 col-md-10 col-sm-20 spacing-sm spacing-xs-small reveal animated fadeIn" data-delay="800">
								<h2 class="section-title limited" style="border-color: <?= get_field('color-code'); ?>;">
									Objektliste
								</h2>
							</div>
						</div>
						<?= $casawp->renderProjectSingle($post); ?>
					</div>
				</section>

			<?php endwhile; ?>
		<?php endif ?>
		
	</div>

<?php echo stripslashes(get_option('casawp_after_content')); ?>

<?php get_footer() ?>