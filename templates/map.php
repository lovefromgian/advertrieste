<?php
/**
 * Template front-end della mappa (shortcode `[advtr_map]`).
 *
 * Variabili disponibili (da Map::shortcode):
 *   $dom_id  string  ID del contenitore mappa
 *   $height  int     altezza in px
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="advtr-map-wrap">
	<div class="advtr-map-filtri" data-advtr-filtri="<?php echo esc_attr( $dom_id ); ?>"></div>
	<div
		id="<?php echo esc_attr( $dom_id ); ?>"
		class="advtr-map"
		style="height: <?php echo esc_attr( $height ); ?>px;"
		data-advtr-map="1"
	></div>
</div>
