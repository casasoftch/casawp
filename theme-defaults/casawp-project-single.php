<?php get_header() ?>

<?php echo stripslashes(get_option('casawp_before_content')); ?>

<div class="casawp-project-single">
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
			<section class="section text-image-section light">
				<div class="container">
					<div class="row">
						<div class="hidden-md hidden-lg col-sm-24 reveal animated fadeIn" style="">
							<div>
								<img src="http://dev.stalderstalder.ch/wp-content/uploads/2016/02/bannaebnisued_EgEUmWa.jpg.1000x999_q85.jpg" alt="" class="img-responsive">
							</div>
						</div>
						<div class="col-lg-8 col-md-10 col-sm-20 spacing-sm spacing-xs-small reveal animated fadeIn" data-delay="800">
							
							<h2 class="section-title limited" style="border-color: #fcc41e;">
								<?= get_the_title() ?>
							</h2>
							<p>Projektenwicklung und Vermarktung.</p>
							<h4>Ausgangslage:</h4>
							<p>75 000 m2&nbsp;Landwirtschaftsland mit Siedlungspotenzial sollen auf Wunsch der Eigentümerschaften eingezont und einer baulichen Nutzung zugeführt werden.</p>
							<h4>Unsere Dienstleistungen:</h4>
							<p>Erste&nbsp;Studien und Einbezug der Behörden,&nbsp;Quartiergestaltungsplan, Einzonung der ersten Etappe (Bannäbni Süd, ca. 35 000 m2), Bebauungsplan über diese erste Etappe, Haupt- und Detailerschliessung, Erreichen der Baureife und Marktreife.</p>
							<h4>Stand heute:</h4>
							<p>Vermarktung&nbsp;von 16 Baulandparzellen und 18 freistehenden Einfamilienhäusern.</p>
							<div class="arrow-link-wrapper">
								<em><a class="arrow-link-right" title="www.bannaebnisued.ch" href="http://bannaebnisued.ch/" target="_blank">www.bannaebnisued.ch</a></em>
							</div>
						</div>
						<div class="hidden-xs hidden-sm col-lg-16 col-md-14">
							<div class="reveal animated fadeIn">
								<img src="http://dev.stalderstalder.ch/wp-content/uploads/2016/02/bannaebnisued_EgEUmWa.jpg.1000x999_q85.jpg" alt="" class="img-responsive">
							</div>
						</div>
					</div>
				</div>
			</section>
		<?php endwhile; ?>
	<?php endif ?>
	<div class="casawp-archive project-property-list">
		<section class="section light">
			<div class="container">
				<div class="row">
					<div class="hidden-md hidden-lg col-sm-24 reveal animated fadeIn" style="">
						<div>
							<img src="http://dev.stalderstalder.ch/wp-content/uploads/2016/02/bannaebnisued_EgEUmWa.jpg.1000x999_q85.jpg" alt="" class="img-responsive">
						</div>
					</div>
					<div class="col-lg-8 col-md-10 col-sm-20 spacing-sm spacing-xs-small reveal animated fadeIn" data-delay="800">
						<h2 class="section-title limited" style="border-color: #fcc41e;">
							Objektliste
						</h2>
					</div>
				</div>
				<a class="casawp-property reveal animated fadeIn" href="/immobilien/18490de-baulandparzelle-c21-p/">
					<div class="realestate-text col-sm-8">
						<div class="info-column" style="border-color: #fcc41e;">
							<div class="table-wrap">
								<table class="table">
									
									<tbody>
										<tr><th>Objekt-Nr.</th><td>31</td></tr>
										<tr><th>Objektkategorie</th><td>Bauland, Grundstück</td></tr>
										<tr><th>Grundstückfläche</th><td></td></tr>
										<tr><th>Verkaufspreis</th><td>CHF 1'660'000.–</td></tr>
										
									</tbody>
								</table>
							</div>
							
						</div>
					</div>
					<div class="realestate-text col-sm-8">
						<div class="title-column">
							<h3>Baulandparzelle C21-P</h3>
							<div class="locality">
								CH-6340 Baar
							</div>
						</div>
					</div>
					<div class="realestate-thumbnail col-sm-8">
							<div class="availability-outerlabel">
								<div class="availability-label availability-label-active">
									Verfügbar	
								</div>
							</div>
							<img width="506" height="360" src="http://dev.stalderstalder.ch/wp-content/uploads/2016/02/poststrasse26zug.jpg.1000x999_q85-506x360.jpg" class="attachment-casawp-thumb size-casawp-thumb wp-post-image" alt="poststrasse26zug.jpg.1000x999_q85">
					</div>
				</a>
			</div>
		</section>
	</div>

<?php echo stripslashes(get_option('casawp_after_content')); ?>

<?php get_footer() ?>