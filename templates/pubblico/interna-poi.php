<?php
/**
 * Punto d'interesse dentro il guscio pubblico.
 *
 * Stessa impostazione della scheda attività, ma con i soli campi che un POI ha
 * davvero: titolo, tipo, categorie, descrizione, immagine e posizione. Niente
 * contatti, offerte o recensioni — un museo non è un cliente, e riempire la
 * pagina di riquadri vuoti la farebbe solo sembrare incompleta.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Frontend\Home;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ap-sito">
	<?php require ADVTR_PATH . 'templates/pubblico/navbar.php'; ?>

	<main class="ap-contenuto ap-contenuto-scheda">
		<?php
		while ( have_posts() ) :
			the_post();
			$advtr_id = get_the_ID();

			$advtr_tipo  = (string) get_post_meta( $advtr_id, 'advtr_tipo', true );
			$advtr_lat   = get_post_meta( $advtr_id, 'advtr_lat', true );
			$advtr_lng   = get_post_meta( $advtr_id, 'advtr_lng', true );
			$advtr_geo   = ( '' !== $advtr_lat && '' !== $advtr_lng );
			$advtr_terms = get_the_terms( $advtr_id, 'categoria' );
			$advtr_imm   = get_the_post_thumbnail_url( $advtr_id, 'medium' );

			// Icona per tipo, con ripiego neutro: i tipi sono testo libero e
			// domani potrebbe comparirne uno non previsto.
			$advtr_icone = array(
				'museo'     => '🏛️',
				'castello'  => '🏰',
				'monumento' => '🗿',
				'grotta'    => '🕳️',
				'parco'     => '🌳',
				'chiesa'    => '⛪',
			);
			$advtr_icona = $advtr_icone[ strtolower( $advtr_tipo ) ] ?? '📍';
			?>
			<article class="as-scheda">

				<div class="as-intestazione">
					<span class="as-logo">
						<?php if ( $advtr_imm ) : ?>
							<img src="<?php echo esc_url( $advtr_imm ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" />
						<?php else : ?>
							<span class="as-logo-icona" aria-hidden="true"><?php echo esc_html( $advtr_icona ); ?></span>
						<?php endif; ?>
					</span>

					<div class="as-intestazione-testo">
						<h1 class="as-titolo"><?php the_title(); ?></h1>
						<?php if ( '' !== $advtr_tipo ) : ?>
							<p class="as-badge">
								<span class="as-pill verde"><?php echo esc_html( $advtr_tipo ); ?></span>
							</p>
						<?php endif; ?>
						<?php if ( $advtr_terms && ! is_wp_error( $advtr_terms ) ) : ?>
							<p class="as-meta">
								<span class="as-cat"><?php echo esc_html( implode( ' · ', wp_list_pluck( $advtr_terms, 'name' ) ) ); ?></span>
							</p>
						<?php endif; ?>
					</div>

					<div class="as-azioni">
						<a class="as-btn as-btn-neutro" href="<?php echo esc_url( Home::url_mappa() ); ?>">
							← <?php esc_html_e( 'Torna alla mappa', 'advertrieste' ); ?>
						</a>
						<?php if ( $advtr_geo ) : ?>
							<a class="as-btn as-btn-primario" target="_blank" rel="noopener"
								href="<?php echo esc_url( 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $advtr_lat . ',' . $advtr_lng ) ); ?>">
								<?php esc_html_e( 'Ottieni indicazioni', 'advertrieste' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>

				<div class="as-corpo">
					<div class="as-principale">
						<?php if ( '' !== trim( get_the_content() ) ) : ?>
							<section class="as-sez">
								<h2><?php esc_html_e( 'Il luogo', 'advertrieste' ); ?></h2>
								<div class="as-testo"><?php the_content(); ?></div>
							</section>
						<?php endif; ?>
					</div>

					<aside class="as-lato">
						<?php if ( $advtr_geo ) : ?>
							<section class="as-info">
								<h2><?php esc_html_e( 'Dove si trova', 'advertrieste' ); ?></h2>
								<div id="advtr-scheda-map" class="advtr-scheda-map" data-advtr-scheda-map="1"></div>
							</section>
						<?php endif; ?>
					</aside>
				</div>
			</article>
			<?php
		endwhile;
		?>
	</main>

	<?php require ADVTR_PATH . 'templates/pubblico/footer.php'; ?>
</div>
