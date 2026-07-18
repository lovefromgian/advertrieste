<?php
/**
 * Template del meta box "Dati evento".
 *
 * Variabili (da EventoMeta::render_dati):
 *   $tipo, $inizio, $fine string
 *   $collegati int[]   locali collegati selezionati
 *   $tipi      array    valore => etichetta tipo evento
 *   $locali    WP_Post[] locali selezionabili
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p>
	<label for="advtr_tipo_evento"><strong><?php esc_html_e( 'Tipo evento', 'advertrieste' ); ?></strong></label><br />
	<select id="advtr_tipo_evento" name="advtr_tipo_evento">
		<?php foreach ( $tipi as $advtr_val => $advtr_label ) : ?>
			<option value="<?php echo esc_attr( $advtr_val ); ?>" <?php selected( $tipo, $advtr_val ); ?>>
				<?php echo esc_html( $advtr_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
</p>
<p>
	<label for="advtr_data_inizio"><strong><?php esc_html_e( 'Inizio', 'advertrieste' ); ?></strong></label><br />
	<input type="datetime-local" id="advtr_data_inizio" name="advtr_data_inizio" value="<?php echo esc_attr( $inizio ); ?>" />
</p>
<p>
	<label for="advtr_data_fine"><strong><?php esc_html_e( 'Fine', 'advertrieste' ); ?></strong></label><br />
	<input type="datetime-local" id="advtr_data_fine" name="advtr_data_fine" value="<?php echo esc_attr( $fine ); ?>" />
</p>
<p>
	<label for="advtr_locali_collegati"><strong><?php esc_html_e( 'Locali collegati', 'advertrieste' ); ?></strong></label><br />
	<select id="advtr_locali_collegati" name="advtr_locali_collegati[]" multiple size="6" style="min-width:260px">
		<?php foreach ( $locali as $advtr_locale ) : ?>
			<option value="<?php echo esc_attr( $advtr_locale->ID ); ?>" <?php selected( in_array( (int) $advtr_locale->ID, $collegati, true ) ); ?>>
				<?php echo esc_html( get_the_title( $advtr_locale ) ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<br /><span class="description"><?php esc_html_e( 'Ctrl/Cmd per selezione multipla.', 'advertrieste' ); ?></span>
</p>
