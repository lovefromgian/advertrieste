<?php
/**
 * Template del meta box "Dati offerta".
 *
 * Variabili (da OffertaMeta::render):
 *   $locale_id int      locale collegato
 *   $inizio    string   datetime-local
 *   $scadenza  string   datetime-local
 *   $tipo      string   tipo coupon (codice|qr)
 *   $codice    string   codice coupon
 *   $locali    WP_Post[] locali selezionabili
 *   $tipi      array    valore => etichetta
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p>
	<label for="advtr_locale_id"><strong><?php esc_html_e( 'Locale collegato', 'advertrieste' ); ?></strong></label><br />
	<select id="advtr_locale_id" name="advtr_locale_id">
		<option value="0"><?php esc_html_e( '— seleziona —', 'advertrieste' ); ?></option>
		<?php foreach ( $locali as $advtr_locale ) : ?>
			<option value="<?php echo esc_attr( $advtr_locale->ID ); ?>" <?php selected( $locale_id, $advtr_locale->ID ); ?>>
				<?php echo esc_html( get_the_title( $advtr_locale ) ); ?>
			</option>
		<?php endforeach; ?>
	</select>
</p>
<p>
	<label for="advtr_data_inizio"><strong><?php esc_html_e( 'Inizio', 'advertrieste' ); ?></strong></label><br />
	<input type="datetime-local" id="advtr_data_inizio" name="advtr_data_inizio" value="<?php echo esc_attr( $inizio ); ?>" />
</p>
<p>
	<label for="advtr_data_scadenza"><strong><?php esc_html_e( 'Scadenza', 'advertrieste' ); ?></strong></label><br />
	<input type="datetime-local" id="advtr_data_scadenza" name="advtr_data_scadenza" value="<?php echo esc_attr( $scadenza ); ?>" />
</p>
<p>
	<label for="advtr_tipo_coupon"><strong><?php esc_html_e( 'Tipo coupon', 'advertrieste' ); ?></strong></label><br />
	<select id="advtr_tipo_coupon" name="advtr_tipo_coupon">
		<?php foreach ( $tipi as $advtr_val => $advtr_label ) : ?>
			<option value="<?php echo esc_attr( $advtr_val ); ?>" <?php selected( $tipo, $advtr_val ); ?>>
				<?php echo esc_html( $advtr_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
</p>
<p>
	<label for="advtr_codice"><strong><?php esc_html_e( 'Codice coupon', 'advertrieste' ); ?></strong></label><br />
	<input type="text" id="advtr_codice" name="advtr_codice" value="<?php echo esc_attr( $codice ); ?>" />
	<span class="description"><?php esc_html_e( 'Codice che il cliente presenta all\'esercente.', 'advertrieste' ); ?></span>
</p>
