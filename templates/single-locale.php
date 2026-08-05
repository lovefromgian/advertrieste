<?php
/**
 * Scheda attività — schermata 07 della proposta (§1.3).
 *
 * Renderizzata dentro il guscio pubblico del plugin (vedi Frontend\Pubblico):
 * fascia d'apertura sfumata con il logo a riquadro, titolo e azioni, poi due
 * colonne — contenuti a sinistra, offerta e informazioni a destra.
 *
 * Conserva gli agganci che la scheda aveva già: `data-advtr-sezione` per le
 * sezioni più viste, `data-advtr-contact` per i click sui contatti,
 * `data-advtr-reviews` per le recensioni Google e `data-advtr-scheda-map` per
 * la mini-mappa.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Cliente\Evidenza;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

while ( have_posts() ) :
	the_post();
	$advtr_id = get_the_ID();

	$advtr_logo_id  = (int) get_post_meta( $advtr_id, 'advtr_logo_id', true );
	$advtr_logo     = $advtr_logo_id ? wp_get_attachment_image_url( $advtr_logo_id, 'medium' ) : get_the_post_thumbnail_url( $advtr_id, 'medium' );
	$advtr_servizi  = get_post_meta( $advtr_id, 'advtr_servizi', true );
	$advtr_servizi  = is_array( $advtr_servizi ) ? $advtr_servizi : array();
	$advtr_galleria = get_post_meta( $advtr_id, 'advtr_galleria_ids', true );
	$advtr_galleria = is_array( $advtr_galleria ) ? array_map( 'absint', $advtr_galleria ) : array();
	$advtr_tel      = (string) get_post_meta( $advtr_id, 'advtr_telefono', true );
	$advtr_email    = (string) get_post_meta( $advtr_id, 'advtr_email', true );
	$advtr_sito     = (string) get_post_meta( $advtr_id, 'advtr_sito', true );
	$advtr_indir    = (string) get_post_meta( $advtr_id, 'advtr_indirizzo', true );
	$advtr_orari    = (string) get_post_meta( $advtr_id, 'advtr_orari', true );
	$advtr_place    = (string) get_post_meta( $advtr_id, 'advtr_place_id', true );
	$advtr_terms    = get_the_terms( $advtr_id, 'categoria' );
	$advtr_in_evid  = Evidenza::attiva( $advtr_id );
	$advtr_novita   = \AdverTrieste\Stats\Stats::is_novita( $advtr_id );
	$advtr_lat      = get_post_meta( $advtr_id, 'advtr_lat', true );
	$advtr_lng      = get_post_meta( $advtr_id, 'advtr_lng', true );
	$advtr_has_geo  = ( '' !== $advtr_lat && '' !== $advtr_lng );

	$advtr_sito_host  = $advtr_sito ? wp_parse_url( $advtr_sito, PHP_URL_HOST ) : '';
	$advtr_sito_label = $advtr_sito_host ? $advtr_sito_host : $advtr_sito;

	// Iniziali per il riquadro quando manca il logo: nel mockup c'è "TB".
	$advtr_sigla = \AdverTrieste\Console\Console::sigla( get_the_title() );

	// Offerta attiva del locale, per il riquadro coupon.
	$advtr_offerta = null;
	foreach ( get_posts(
		array(
			'post_type'      => 'offerta',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'advtr_locale_id',
					'value' => $advtr_id,
				),
			),
		)
	) as $advtr_o ) {
		if ( \AdverTrieste\Coupon\Coupon::is_offer_active( $advtr_o->ID ) ) {
			$advtr_offerta = $advtr_o;
			break;
		}
	}
	?>
	<article class="as-scheda">

		<header class="as-eroe<?php echo $advtr_in_evid ? ' evidenza' : ''; ?>">
			<?php if ( $advtr_in_evid ) : ?>
				<span class="as-eroe-badge">★ <?php esc_html_e( 'In evidenza', 'advertrieste' ); ?></span>
			<?php elseif ( $advtr_novita ) : ?>
				<span class="as-eroe-badge nov"><?php esc_html_e( 'Novità', 'advertrieste' ); ?></span>
			<?php endif; ?>
		</header>

		<div class="as-intestazione">
			<span class="as-logo">
				<?php if ( $advtr_logo ) : ?>
					<img src="<?php echo esc_url( $advtr_logo ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" />
				<?php else : ?>
					<span class="as-logo-sigla"><?php echo esc_html( $advtr_sigla ); ?></span>
				<?php endif; ?>
			</span>

			<div class="as-intestazione-testo">
				<h1 class="as-titolo"><?php the_title(); ?></h1>
				<p class="as-meta">
					<?php if ( $advtr_terms && ! is_wp_error( $advtr_terms ) ) : ?>
						<span class="as-cat"><?php echo esc_html( implode( ' · ', wp_list_pluck( $advtr_terms, 'name' ) ) ); ?></span>
					<?php endif; ?>
					<span class="as-voto" data-advtr-voto hidden></span>
				</p>
			</div>

			<div class="as-azioni">
				<?php if ( $advtr_tel ) : ?>
					<a class="as-btn as-btn-neutro" data-advtr-contact="tel" href="tel:<?php echo esc_attr( rawurlencode( $advtr_tel ) ); ?>">
						📞 <?php esc_html_e( 'Chiama', 'advertrieste' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $advtr_has_geo ) : ?>
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
						<h2><?php esc_html_e( 'Chi siamo', 'advertrieste' ); ?></h2>
						<div class="as-testo"><?php the_content(); ?></div>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $advtr_servizi ) ) : ?>
					<section class="as-sez" data-advtr-sezione="servizi">
						<h2><?php esc_html_e( 'Servizi offerti', 'advertrieste' ); ?></h2>
						<ul class="as-servizi">
							<?php foreach ( $advtr_servizi as $advtr_srv ) : ?>
								<li><?php echo esc_html( $advtr_srv ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $advtr_galleria ) ) : ?>
					<section class="as-sez" data-advtr-sezione="galleria">
						<h2><?php esc_html_e( 'Galleria foto', 'advertrieste' ); ?></h2>
						<div class="as-galleria">
							<?php foreach ( $advtr_galleria as $advtr_att ) : ?>
								<?php $advtr_src = wp_get_attachment_image_url( $advtr_att, 'large' ); ?>
								<?php if ( $advtr_src ) : ?>
									<a href="<?php echo esc_url( $advtr_src ); ?>" target="_blank" rel="noopener">
										<?php echo wp_get_attachment_image( $advtr_att, 'medium', false, array( 'loading' => 'lazy' ) ); ?>
									</a>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<section class="as-sez advtr-recensioni-sez" data-advtr-reviews="1" hidden></section>
			</div>

			<aside class="as-lato">

				<?php if ( $advtr_offerta ) : ?>
					<?php
					$advtr_codice   = (string) get_post_meta( $advtr_offerta->ID, 'advtr_codice', true );
					$advtr_scadenza = (string) get_post_meta( $advtr_offerta->ID, 'advtr_data_scadenza', true );
					?>
					<section class="as-offerta" data-advtr-sezione="offerte">
						<p class="as-offerta-occhiello">🎟 <?php esc_html_e( 'Offerta a tempo', 'advertrieste' ); ?></p>
						<h2 class="as-offerta-titolo"><?php echo esc_html( get_the_title( $advtr_offerta ) ); ?></h2>

						<?php if ( $advtr_scadenza ) : ?>
							<p class="as-offerta-scade">
								<?php esc_html_e( 'Scade tra', 'advertrieste' ); ?>
								<span data-advtr-countdown="<?php echo esc_attr( $advtr_scadenza ); ?>">—</span>
							</p>
						<?php endif; ?>

						<?php if ( '' !== trim( $advtr_offerta->post_content ) ) : ?>
							<p class="as-offerta-testo"><?php echo esc_html( wp_strip_all_tags( $advtr_offerta->post_content ) ); ?></p>
						<?php endif; ?>

						<?php if ( $advtr_codice ) : ?>
							<div class="as-coupon">
								<span class="as-coupon-eti"><?php esc_html_e( 'Mostra il coupon', 'advertrieste' ); ?></span>
								<span class="as-coupon-codice"><?php echo esc_html( $advtr_codice ); ?></span>
								<span class="as-coupon-nota"><?php esc_html_e( 'Presentalo all\'esercente', 'advertrieste' ); ?></span>
							</div>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<section class="as-info" data-advtr-sezione="contatti">
					<h2><?php esc_html_e( 'Informazioni', 'advertrieste' ); ?></h2>
					<ul class="as-info-lista">
						<?php if ( $advtr_indir ) : ?>
							<li><span class="as-ico" aria-hidden="true">📍</span><span><?php echo esc_html( $advtr_indir ); ?></span></li>
						<?php endif; ?>

						<?php if ( '' !== $advtr_orari ) : ?>
							<li>
								<span class="as-ico" aria-hidden="true">🕐</span>
								<span class="as-orari">
									<?php foreach ( preg_split( '/\r\n|\r|\n/', $advtr_orari ) as $advtr_riga ) : ?>
										<?php if ( '' !== trim( $advtr_riga ) ) : ?>
											<span><?php echo esc_html( trim( $advtr_riga ) ); ?></span>
										<?php endif; ?>
									<?php endforeach; ?>
								</span>
							</li>
						<?php endif; ?>

						<?php if ( $advtr_tel ) : ?>
							<li><span class="as-ico" aria-hidden="true">📞</span>
								<a class="advtr-contact-link" data-advtr-contact="tel" href="tel:<?php echo esc_attr( rawurlencode( $advtr_tel ) ); ?>"><?php echo esc_html( $advtr_tel ); ?></a></li>
						<?php endif; ?>

						<?php if ( $advtr_email ) : ?>
							<li><span class="as-ico" aria-hidden="true">✉️</span>
								<a class="advtr-contact-link" data-advtr-contact="email" href="mailto:<?php echo esc_attr( $advtr_email ); ?>"><?php echo esc_html( $advtr_email ); ?></a></li>
						<?php endif; ?>

						<?php if ( $advtr_sito ) : ?>
							<li><span class="as-ico" aria-hidden="true">🌐</span>
								<a class="advtr-contact-link" data-advtr-contact="sito" href="<?php echo esc_url( $advtr_sito ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $advtr_sito_label ); ?></a></li>
						<?php endif; ?>
					</ul>

					<?php if ( $advtr_place ) : ?>
						<a class="as-recensione" href="<?php echo esc_url( 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $advtr_place ) ); ?>" target="_blank" rel="noopener nofollow">
							<?php esc_html_e( 'Scrivi una recensione su Google', 'advertrieste' ); ?>
						</a>
					<?php endif; ?>
				</section>

				<?php if ( $advtr_has_geo ) : ?>
					<section class="as-info" data-advtr-sezione="mappa">
						<h2><?php esc_html_e( 'Dove siamo', 'advertrieste' ); ?></h2>
						<div id="advtr-scheda-map" class="advtr-scheda-map" data-advtr-scheda-map="1"></div>
					</section>
				<?php endif; ?>

			</aside>
		</div>
	</article>
	<?php
endwhile;
