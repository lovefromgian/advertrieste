<?php
/**
 * Template della scheda attività (pagina singola del CPT `locale`, §1.3).
 *
 * Renderizzato al posto del single del tema (vedi Frontend\Scheda). Usa
 * l'header/footer del tema per integrarsi nel sito.
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

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
	$advtr_in_evid  = (bool) get_post_meta( $advtr_id, 'advtr_in_evidenza', true );
	$advtr_novita   = \AdverTrieste\Stats\Stats::is_novita( $advtr_id );
	$advtr_has_geo  = ( '' !== get_post_meta( $advtr_id, 'advtr_lat', true ) && '' !== get_post_meta( $advtr_id, 'advtr_lng', true ) );
	$advtr_sito_host = $advtr_sito ? wp_parse_url( $advtr_sito, PHP_URL_HOST ) : '';
	$advtr_sito_label = $advtr_sito_host ? $advtr_sito_host : $advtr_sito;
	?>
	<div class="advtr-scheda-wrap">
		<article class="advtr-scheda">

			<header class="advtr-scheda-head">
				<?php if ( $advtr_logo ) : ?>
					<img class="advtr-scheda-logo" src="<?php echo esc_url( $advtr_logo ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" />
				<?php endif; ?>
				<div class="advtr-scheda-headtext">
					<h1 class="advtr-scheda-titolo"><?php the_title(); ?></h1>
					<div class="advtr-scheda-badges">
						<?php if ( $advtr_in_evid ) : ?>
							<span class="advtr-badge advtr-badge-evid"><?php esc_html_e( 'In evidenza', 'advertrieste' ); ?></span>
						<?php endif; ?>
						<?php if ( $advtr_novita ) : ?>
							<span class="advtr-badge advtr-badge-nov"><?php esc_html_e( 'Novità', 'advertrieste' ); ?></span>
						<?php endif; ?>
						<?php if ( $advtr_terms && ! is_wp_error( $advtr_terms ) ) : ?>
							<?php foreach ( $advtr_terms as $advtr_term ) : ?>
								<span class="advtr-cat"><?php echo esc_html( $advtr_term->name ); ?></span>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			</header>

			<?php if ( trim( get_the_content() ) !== '' ) : ?>
				<section class="advtr-scheda-desc"><?php the_content(); ?></section>
			<?php endif; ?>

			<?php if ( ! empty( $advtr_servizi ) ) : ?>
				<section class="advtr-scheda-sez">
					<h2><?php esc_html_e( 'Servizi', 'advertrieste' ); ?></h2>
					<ul class="advtr-servizi">
						<?php foreach ( $advtr_servizi as $advtr_srv ) : ?>
							<li><?php echo esc_html( $advtr_srv ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $advtr_galleria ) ) : ?>
				<section class="advtr-scheda-sez">
					<h2><?php esc_html_e( 'Galleria', 'advertrieste' ); ?></h2>
					<div class="advtr-galleria">
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

			<?php if ( '' !== $advtr_orari ) : ?>
				<section class="advtr-scheda-sez">
					<h2><?php esc_html_e( 'Orari', 'advertrieste' ); ?></h2>
					<ul class="advtr-orari">
						<?php foreach ( preg_split( '/\r\n|\r|\n/', $advtr_orari ) as $advtr_riga ) : ?>
							<?php if ( '' !== trim( $advtr_riga ) ) : ?>
								<li><?php echo esc_html( $advtr_riga ); ?></li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</article>

		<aside class="advtr-scheda-side">
			<?php if ( $advtr_tel || $advtr_email || $advtr_sito || $advtr_indir ) : ?>
				<section class="advtr-scheda-box">
					<h2><?php esc_html_e( 'Contatti', 'advertrieste' ); ?></h2>
					<ul class="advtr-contatti">
						<?php if ( $advtr_indir ) : ?>
							<li><span class="advtr-c-label"><?php esc_html_e( 'Indirizzo', 'advertrieste' ); ?></span><span><?php echo esc_html( $advtr_indir ); ?></span></li>
						<?php endif; ?>
						<?php if ( $advtr_tel ) : ?>
							<li><span class="advtr-c-label"><?php esc_html_e( 'Telefono', 'advertrieste' ); ?></span>
								<a class="advtr-contact-link" data-advtr-contact="tel" href="tel:<?php echo esc_attr( rawurlencode( $advtr_tel ) ); ?>"><?php echo esc_html( $advtr_tel ); ?></a></li>
						<?php endif; ?>
						<?php if ( $advtr_email ) : ?>
							<li><span class="advtr-c-label"><?php esc_html_e( 'Email', 'advertrieste' ); ?></span>
								<a class="advtr-contact-link" data-advtr-contact="email" href="mailto:<?php echo esc_attr( $advtr_email ); ?>"><?php echo esc_html( $advtr_email ); ?></a></li>
						<?php endif; ?>
						<?php if ( $advtr_sito ) : ?>
							<li><span class="advtr-c-label"><?php esc_html_e( 'Sito web', 'advertrieste' ); ?></span>
								<a class="advtr-contact-link" data-advtr-contact="sito" href="<?php echo esc_url( $advtr_sito ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $advtr_sito_label ); ?></a></li>
						<?php endif; ?>
					</ul>
					<?php if ( $advtr_place ) : ?>
						<a class="advtr-recensione" href="<?php echo esc_url( 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $advtr_place ) ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Scrivi una recensione su Google', 'advertrieste' ); ?></a>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( $advtr_has_geo ) : ?>
				<section class="advtr-scheda-box">
					<h2><?php esc_html_e( 'Dove siamo', 'advertrieste' ); ?></h2>
					<div id="advtr-scheda-map" class="advtr-scheda-map" data-advtr-scheda-map="1"></div>
				</section>
			<?php endif; ?>
		</aside>
	</div>
	<?php
endwhile;

get_footer();
