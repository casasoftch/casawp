<?php $template = new CasaSync\Templateable();?>

<?php get_header(); ?>
	<?php while ( have_posts() ) : the_post();?>
		<?php $single = new CasaSync\Single($post);?>
		<?php if ($template->setTemplate('single', $single)): ?>
			<?php echo $template->render(); ?>
		<?php else: ?>	
			<div class="casasync-single">
				<div class="casasync-single-content">
					<header class="entry-header">
						<h1 class="entry-title"><?php echo $single->getTitle(); ?></h1>
					</header>
					<div class="entry-content">
						<?php echo $single->getGallery(); ?>
						<br>
						<?php echo $single->getTabable(); ?>
					</div>
				</div>
				<aside class="casasync-single-aside">
					<?php echo $single->getPagination(); ?>
					<?php echo $single->getContactform(); ?>
				</aside>
			</div>
		<?php endif ?>
	<?php endwhile; ?>
	<?php wp_reset_query(); ?>
<?php get_footer(); ?>