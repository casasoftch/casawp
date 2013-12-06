<?php $template = new CasaSync\Templateable(); ?>
<?php $archive = new CasaSync\Archive(); ?>

<?php get_header(); ?>
	<?php echo stripslashes(get_option('casasync_before_content')); ?>
	<?php if ($template->setTemplate('archive', $archive)): ?>
		<?php echo $template->render(); ?>
	<?php else: ?>	
		<div class="casasync-archive entry-content">
			<aside class="casasync-archive-aside">
				<?php echo $archive->getFilterForm(); ?>
			</aside>
			<?php if ( have_posts() ): ?>
				<div class="casasync-archive-content">
					<?php while ( have_posts() ) : the_post(); ?>
						<?php $single = new CasaSync\Single($post);?>
						<?php if ($template->setTemplate('archive_single', $single)): ?>
							<?php echo $template->render(); ?>
						<?php else: ?>	
							<div class="casasync-property">
								<div class="casasync-thumbnail-wrapper">
									<?php //echo $single->getAvailability(); ?>
									<?php echo ($single->getFeaturedImage() ? $single->getFeaturedImage() : '<div class="casasync-missing-gallery">' . __('No image', 'casasync') . '</div>'); ?>
								</div>
								<div class="casasync-text">
									<h3><a href="<?php echo $single->getPermalink() ?>"><?php echo $single->getTitle(); ?></a></h3>
									<?php echo $single->getQuickInfosTable(); ?>
								</div>
							</div>
							<hr class="soften">
						<?php endif ?>
					<?php endwhile; ?>
					<?php echo $archive->getPagination() ?>
				</div>
			<?php else: ?>
				<div class="casasync-archive-content casasync-no-posts-found">
					<h1 class="entry-title"><?php _e( 'Nothing Found', 'casasync' ); ?></h1>
					<p><?php _e( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'casasync' ); ?></p>
				</div>
			<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php wp_reset_query(); ?>
	<?php echo stripslashes(get_option('casasync_after_content')); ?>
<?php get_footer(); ?>
