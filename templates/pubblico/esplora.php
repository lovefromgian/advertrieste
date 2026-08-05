<?php
/**
 * Esplora la mappa — schermata 06 della proposta.
 *
 * Barra strumenti con ricerca e chip di categoria, poi lista dei risultati a
 * sinistra e mappa a destra, sincronizzate. La lista è costruita dal JavaScript
 * a partire dagli stessi marker che disegnano la mappa: una sola richiesta, una
 * sola verità.
 *
 * Variabili disponibili: $categorie (array), $dom_id (string), $altezza (int).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Frontend\Home;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola navigazione pubblica.
$advtr_q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

$advtr_icone = array(
	'mangiare' => '🍴',
	'bere'     => '☕',
	'shopping' => '🛍️',
	'visitare' => '🏛️',
	'servizi'  => '💈',
);
?>
<div class="ae-esplora" data-advtr-esplora>

	<form class="ae-barra" method="get" action="<?php echo esc_url( Home::url_mappa() ); ?>" role="search">
		<div class="ae-cerca">
			<span class="ae-cerca-icona" aria-hidden="true">🔍</span>
			<label class="screen-reader-text" for="ae-q"><?php esc_html_e( 'Cerca un locale, una via, una zona', 'advertrieste' ); ?></label>
			<input type="search" id="ae-q" name="q" value="<?php echo esc_attr( $advtr_q ); ?>"
				placeholder="<?php esc_attr_e( 'Cerca un locale, una via, una zona…', 'advertrieste' ); ?>" />
			<?php if ( '' !== $advtr_q ) : ?>
				<a class="ae-cerca-azzera" href="<?php echo esc_url( Home::url_mappa() ); ?>"
					title="<?php esc_attr_e( 'Azzera la ricerca', 'advertrieste' ); ?>">&times;</a>
			<?php endif; ?>
		</div>

		<div class="ae-chip" data-advtr-chip>
			<button type="button" class="ae-chip-btn attivo" data-cat=""><?php esc_html_e( 'Tutti', 'advertrieste' ); ?></button>
			<?php foreach ( $categorie as $advtr_c ) : ?>
				<button type="button" class="ae-chip-btn" data-cat="<?php echo esc_attr( $advtr_c['slug'] ); ?>">
					<span aria-hidden="true"><?php echo esc_html( $advtr_icone[ $advtr_c['slug'] ] ?? '📍' ); ?></span>
					<?php echo esc_html( $advtr_c['name'] ); ?>
				</button>
			<?php endforeach; ?>
			<button type="button" class="ae-chip-btn ae-chip-offerte" data-solo-offerte="1">
				<span aria-hidden="true">🎟️</span><?php esc_html_e( 'Offerte', 'advertrieste' ); ?>
			</button>
		</div>
	</form>

	<div class="ae-corpo">
		<aside class="ae-lista">
			<div class="ae-lista-testa">
				<h2 class="ae-lista-titolo"><?php esc_html_e( 'Locali nella tua zona', 'advertrieste' ); ?></h2>
				<p class="ae-lista-conta" data-advtr-conta></p>
			</div>
			<div class="ae-lista-corpo" data-advtr-risultati></div>
		</aside>

		<div class="ae-mappa">
			<div id="<?php echo esc_attr( $dom_id ); ?>" class="advtr-map" data-advtr-map
				style="height: <?php echo esc_attr( $altezza ); ?>px;"></div>
		</div>
	</div>
</div>
