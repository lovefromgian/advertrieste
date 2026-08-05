<?php
/**
 * Sezione "Mappa punti QR": la rete di espositori, riservata ai clienti.
 *
 * Le coordinate non sono incorporate qui: arrivano dall'endpoint autenticato
 * `GET advertrieste/v1/qr-map`, che verifica la capability lato server.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Frontend\ReservedArea;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

ReservedArea::enqueue_qr_assets();
?>
<section class="advtr-qr-section">
	<h3><?php esc_html_e( 'Mappa dei punti QR', 'advertrieste' ); ?></h3>
	<p class="advtr-aiuto">
		<?php esc_html_e( 'La rete di espositori e QR code sul territorio. Contenuto riservato ai clienti.', 'advertrieste' ); ?>
	</p>
	<div id="advtr-qr-map" class="advtr-map" style="height: 500px;" data-advtr-qr-map="1"></div>
</section>
