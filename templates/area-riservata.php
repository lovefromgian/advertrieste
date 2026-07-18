<?php
/**
 * Template dell'area riservata clienti (shortcode `[advtr_area_riservata]`).
 *
 * Variabili disponibili (da ReservedArea::shortcode):
 *   $user    WP_User  utente corrente
 *   $puo_qr  bool     può vedere la mappa dei punti QR
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="advtr-area-riservata">
	<p class="advtr-benvenuto">
		<?php
		printf(
			/* translators: %s: nome utente */
			esc_html__( 'Ciao %s, benvenuto nell\'area riservata.', 'advertrieste' ),
			esc_html( $user->display_name )
		);
		?>
	</p>

	<?php if ( $puo_qr ) : ?>
		<section class="advtr-qr-section">
			<h3><?php esc_html_e( 'Mappa dei punti QR', 'advertrieste' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'La rete di espositori e QR code sul territorio. Contenuto riservato ai clienti.', 'advertrieste' ); ?>
			</p>
			<div id="advtr-qr-map" class="advtr-map" style="height: 500px;" data-advtr-qr-map="1"></div>
		</section>
	<?php else : ?>
		<p><?php esc_html_e( 'Il tuo profilo non ha accesso alla mappa dei punti QR.', 'advertrieste' ); ?></p>
	<?php endif; ?>
</div>
