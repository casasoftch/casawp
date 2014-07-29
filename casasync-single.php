<?php $template = new CasaSync\Templateable();?>

<?php get_header(); ?>
	<?php while ( have_posts() ) : the_post();?>
		<?php $single = new CasaSync\Single($post);?>
		<?php echo stripslashes(get_option('casasync_before_content')); ?>
		<?php if ($template->setTemplate('single', $single)): ?>
			<?php echo $template->render(); ?>
	<?php else: ?>	
			<div class="casasync-single">
				<div class="casasync-row">
					<nav class="casasync-single-pagination-top">
						<?php echo $single->getPagination(); ?>
					</nav>
				</div>
				<div class="casasync-row">
					<div class="casasync-single-content">
						<header class="casasync-single-header">
							<h1 class="casasync-single-title"><?php echo $single->getTitle(); ?></h1>
						</header>
						<div class="casasync-single-contentarea">
							<?php echo $single->getGallery(); ?>
							<br>
							<?php echo $single->getTabable(); ?>
							<?php echo $single->contactSellerByMailBox(); ?>
						</div>
					</div>
					<aside class="casasync-single-aside">
						<div class="casasync-single-aside-container">
							<?php echo $single->getPagination(); ?>
						</div>
						<div class="casasync-single-aside-container">
							<?php echo $single->getContactform(); ?>
						</div>
						<div class="casasync-single-aside-container">
							<?php echo $single->getSeller(); ?>
						</div>
						<div class="casasync-single-aside-container">
							<?php echo $single->getSalesPerson(); ?>
						</div>
						<div class="casasync-single-aside-container">
							<?php echo $single->getAllShareWidgets(); ?>
						</div>
					</aside>
				</div>
			</div>
		<?php endif ?>
		<?php echo stripslashes(get_option('casasync_after_content')); ?>
	<?php endwhile; ?>
	<?php wp_reset_query(); ?>
<?php get_footer(); ?>