<?php $template = new CasaSync\Templateable(); ?>
<?php $archive = new CasaSync\Archive(); ?>

<?php get_header(); ?>
	<?php if ($template->setTemplate('archive', $archive)): ?>
		<?php echo $template->render(); ?>
	<?php else: ?>	
		<div class="casasync-archive entry-content">
			<div class="casasync-archive-content">
				<?php echo $archive->getPaginationTop() ?>
				<?php while ( have_posts() ) : the_post(); ?>
					<?php $single = new CasaSync\Single($post);?>
					<?php if ($template->setTemplate('archive_single', $single)): ?>
						<?php echo $template->render(); ?>
					<?php else: ?>	
						<div class="casasync-property">
							<div class="casasync-gallery"><?php echo ($single->getGallery('small') ? $single->getGallery('small') : '<div class="casasync-missing-gallery">No image</div>'); ?></div>
							<div class="casasync-text"><h3><a href="<?php echo $single->getPermalink() ?>"><?php echo $single->getTitle(); ?></a></h3></div>
						</div>
					<?php endif ?>
				<?php endwhile; ?>
				<?php echo $archive->getPaginationBottom() ?>
			</div>
			<aside class="casasync-archive-aside">
				<?php echo $archive->getFilterForm(); ?>
			</aside>
		</div>
	<?php endif; ?>
	<?php wp_reset_query(); ?>
<?php get_footer(); ?>