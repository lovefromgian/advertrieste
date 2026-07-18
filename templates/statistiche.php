<?php
/**
 * Template della dashboard statistiche (shortcode `[advtr_statistiche]`).
 *
 * Variabili disponibili (da StatsDashboard::shortcode):
 *   $schede  array  lista {id, titolo} selezionabili
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="advtr-stats" data-advtr-stats="1">
	<?php if ( count( $schede ) > 1 ) : ?>
		<p class="advtr-stats-selettore">
			<label for="advtr-stats-scheda"><?php esc_html_e( 'Scheda:', 'advertrieste' ); ?></label>
			<select id="advtr-stats-scheda" data-advtr-stats-select="1">
				<?php foreach ( $schede as $advtr_scheda ) : ?>
					<option value="<?php echo esc_attr( $advtr_scheda['id'] ); ?>">
						<?php echo esc_html( $advtr_scheda['titolo'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
	<?php else : ?>
		<input type="hidden" data-advtr-stats-single="<?php echo esc_attr( $schede[0]['id'] ); ?>" />
	<?php endif; ?>

	<div class="advtr-stats-panel" data-advtr-stats-panel="1"></div>
</div>
