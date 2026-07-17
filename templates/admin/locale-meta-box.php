<?php
/**
 * Template del meta box "Dati locale".
 *
 * Variabili disponibili (fornite da LocaleMeta::render):
 *   $lat, $lng, $zoom_min, $data_inizio, $data_fine, $in_evidenza,
 *   $evidenza_inizio, $evidenza_fine, $evidenza_priorita, $servizi_text,
 *   $place_id, $logo_id, $logo_url, $galleria_csv
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<style>
	.advtr-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 20px; margin-top: 8px; }
	.advtr-field { display: flex; flex-direction: column; }
	.advtr-field.full { grid-column: 1 / -1; }
	.advtr-field label { font-weight: 600; margin-bottom: 4px; }
	.advtr-field .description { margin-top: 4px; }
	.advtr-media-preview img { max-width: 80px; height: auto; display: block; margin: 6px 0; border: 1px solid #dcdcde; }
</style>

<div class="advtr-grid">
	<div class="advtr-field">
		<label for="advtr_lat"><?php esc_html_e( 'Latitudine', 'advertrieste' ); ?></label>
		<input type="number" step="any" id="advtr_lat" name="advtr_lat" value="<?php echo esc_attr( $lat ); ?>" />
	</div>
	<div class="advtr-field">
		<label for="advtr_lng"><?php esc_html_e( 'Longitudine', 'advertrieste' ); ?></label>
		<input type="number" step="any" id="advtr_lng" name="advtr_lng" value="<?php echo esc_attr( $lng ); ?>" />
	</div>

	<div class="advtr-field">
		<label for="advtr_zoom_min"><?php esc_html_e( 'Zoom minimo (visibilità sulla mappa)', 'advertrieste' ); ?></label>
		<input type="number" step="1" min="0" max="22" id="advtr_zoom_min" name="advtr_zoom_min" value="<?php echo esc_attr( $zoom_min ); ?>" />
		<p class="description"><?php esc_html_e( 'Soglia di zoom da cui il marker diventa visibile.', 'advertrieste' ); ?></p>
	</div>
	<div class="advtr-field">
		<label for="advtr_place_id"><?php esc_html_e( 'Google Place ID (recensioni)', 'advertrieste' ); ?></label>
		<input type="text" id="advtr_place_id" name="advtr_place_id" value="<?php echo esc_attr( $place_id ); ?>" />
	</div>

	<div class="advtr-field">
		<label for="advtr_data_inizio"><?php esc_html_e( 'Validità — inizio', 'advertrieste' ); ?></label>
		<input type="date" id="advtr_data_inizio" name="advtr_data_inizio" value="<?php echo esc_attr( $data_inizio ); ?>" />
	</div>
	<div class="advtr-field">
		<label for="advtr_data_fine"><?php esc_html_e( 'Validità — fine', 'advertrieste' ); ?></label>
		<input type="date" id="advtr_data_fine" name="advtr_data_fine" value="<?php echo esc_attr( $data_fine ); ?>" />
	</div>

	<div class="advtr-field full">
		<label>
			<input type="checkbox" name="advtr_in_evidenza" value="1" <?php checked( $in_evidenza ); ?> />
			<?php esc_html_e( 'In evidenza (marker dorato, priorità nei risultati)', 'advertrieste' ); ?>
		</label>
	</div>

	<div class="advtr-field">
		<label for="advtr_evidenza_inizio"><?php esc_html_e( 'Evidenza — inizio', 'advertrieste' ); ?></label>
		<input type="date" id="advtr_evidenza_inizio" name="advtr_evidenza_inizio" value="<?php echo esc_attr( $evidenza_inizio ); ?>" />
	</div>
	<div class="advtr-field">
		<label for="advtr_evidenza_fine"><?php esc_html_e( 'Evidenza — fine', 'advertrieste' ); ?></label>
		<input type="date" id="advtr_evidenza_fine" name="advtr_evidenza_fine" value="<?php echo esc_attr( $evidenza_fine ); ?>" />
	</div>

	<div class="advtr-field">
		<label for="advtr_evidenza_priorita"><?php esc_html_e( 'Priorità evidenza', 'advertrieste' ); ?></label>
		<input type="number" step="1" min="0" id="advtr_evidenza_priorita" name="advtr_evidenza_priorita" value="<?php echo esc_attr( $evidenza_priorita ); ?>" />
	</div>

	<div class="advtr-field full">
		<label for="advtr_servizi"><?php esc_html_e( 'Servizi (uno per riga)', 'advertrieste' ); ?></label>
		<textarea id="advtr_servizi" name="advtr_servizi" rows="4"><?php echo esc_textarea( $servizi_text ); ?></textarea>
	</div>

	<div class="advtr-field">
		<label><?php esc_html_e( 'Logo', 'advertrieste' ); ?></label>
		<div class="advtr-media-preview" id="advtr_logo_preview">
			<?php if ( $logo_url ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
			<?php endif; ?>
		</div>
		<input type="hidden" id="advtr_logo_id" name="advtr_logo_id" value="<?php echo esc_attr( $logo_id ); ?>" />
		<p>
			<button type="button" class="button" id="advtr_logo_select"><?php esc_html_e( 'Scegli logo', 'advertrieste' ); ?></button>
			<button type="button" class="button-link" id="advtr_logo_remove"><?php esc_html_e( 'Rimuovi', 'advertrieste' ); ?></button>
		</p>
	</div>

	<div class="advtr-field">
		<label><?php esc_html_e( 'Galleria', 'advertrieste' ); ?></label>
		<div class="advtr-media-preview" id="advtr_galleria_preview"></div>
		<input type="hidden" id="advtr_galleria_ids" name="advtr_galleria_ids" value="<?php echo esc_attr( $galleria_csv ); ?>" />
		<p>
			<button type="button" class="button" id="advtr_galleria_select"><?php esc_html_e( 'Gestisci galleria', 'advertrieste' ); ?></button>
			<button type="button" class="button-link" id="advtr_galleria_clear"><?php esc_html_e( 'Svuota', 'advertrieste' ); ?></button>
		</p>
	</div>
</div>
