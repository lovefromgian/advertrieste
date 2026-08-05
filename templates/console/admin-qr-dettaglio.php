<?php
/**
 * Console admin — modifica di un punto QR.
 *
 * Variabili disponibili: $id (int).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_post = get_post( $id );
if ( ! $advtr_post || 'punto_qr' !== $advtr_post->post_type ) {
	echo '<div class="ac-vuoto"><p>' . esc_html__( 'Punto QR non trovato.', 'advertrieste' ) . '</p></div>';
	return;
}

$advtr_lat   = get_post_meta( $id, 'advtr_lat', true );
$advtr_lng   = get_post_meta( $id, 'advtr_lng', true );
$advtr_stato = (string) get_post_meta( $id, 'advtr_stato', true );
?>
<p style="margin:0 0 16px">
	<a class="ac-btn ac-btn-neutro" href="<?php echo esc_url( AdminConsole::url( 'qr' ) ); ?>">
		← <?php esc_html_e( 'Torna all\'elenco', 'advertrieste' ); ?>
	</a>
</p>

<form class="advtr-form" method="post" action="<?php echo esc_url( AdminConsole::url( 'qr', array( 'id' => $id ) ) ); ?>">
	<?php wp_nonce_field( AdminConsole::NONCE ); ?>
	<input type="hidden" name="advtr_azione" value="qr_salva" />
	<input type="hidden" name="advtr_id" value="<?php echo esc_attr( $id ); ?>" />

	<div class="ac-card">
		<h3 class="ac-card-titolo"><?php esc_html_e( 'Espositore', 'advertrieste' ); ?></h3>

		<label for="ac-qr-titolo"><?php esc_html_e( 'Etichetta', 'advertrieste' ); ?></label>
		<input type="text" id="ac-qr-titolo" name="advtr_titolo" required value="<?php echo esc_attr( $advtr_post->post_title ); ?>" />
		<p class="advtr-aiuto"><?php esc_html_e( 'Come lo riconosci sul territorio. Esempio: "Totem Stazione Centrale".', 'advertrieste' ); ?></p>

		<div class="advtr-griglia-2">
			<div>
				<label for="ac-qr-stato"><?php esc_html_e( 'Stato', 'advertrieste' ); ?></label>
				<select id="ac-qr-stato" name="advtr_stato">
					<option value="attivo" <?php selected( 'inattivo' !== $advtr_stato ); ?>><?php esc_html_e( 'Attivo', 'advertrieste' ); ?></option>
					<option value="inattivo" <?php selected( 'inattivo', $advtr_stato ); ?>><?php esc_html_e( 'Inattivo', 'advertrieste' ); ?></option>
				</select>
			</div>
			<div>
				<label for="ac-qr-post"><?php esc_html_e( 'Pubblicazione', 'advertrieste' ); ?></label>
				<select id="ac-qr-post" name="advtr_stato_post">
					<option value="publish" <?php selected( 'publish', $advtr_post->post_status ); ?>><?php esc_html_e( 'Attivo in elenco', 'advertrieste' ); ?></option>
					<option value="draft" <?php selected( 'publish' !== $advtr_post->post_status ); ?>><?php esc_html_e( 'Archiviato', 'advertrieste' ); ?></option>
				</select>
			</div>
		</div>

		<fieldset class="advtr-fieldset">
			<legend><?php esc_html_e( 'Posizione', 'advertrieste' ); ?></legend>

			<label for="advtr-indirizzo"><?php esc_html_e( 'Indirizzo', 'advertrieste' ); ?></label>
			<input type="text" id="advtr-indirizzo" name="advtr_indirizzo"
				value="<?php echo esc_attr( get_post_meta( $id, 'advtr_indirizzo', true ) ); ?>" />

			<div class="advtr-geo-riga" style="margin-top:10px">
				<button type="button" class="ac-btn ac-btn-neutro" data-advtr-geocode>
					<?php esc_html_e( 'Trova dall\'indirizzo', 'advertrieste' ); ?>
				</button>
				<span class="advtr-geo-esito" data-advtr-geo-esito aria-live="polite"></span>
			</div>

			<div class="advtr-mappa-picker" data-advtr-picker
				data-lat="<?php echo esc_attr( '' !== $advtr_lat ? $advtr_lat : 45.6495 ); ?>"
				data-lng="<?php echo esc_attr( '' !== $advtr_lng ? $advtr_lng : 13.7768 ); ?>"></div>

			<div class="advtr-griglia-2">
				<div>
					<label for="ac-qr-lat"><?php esc_html_e( 'Latitudine', 'advertrieste' ); ?></label>
					<input type="text" id="ac-qr-lat" name="advtr_lat" data-advtr-lat value="<?php echo esc_attr( $advtr_lat ); ?>" />
				</div>
				<div>
					<label for="ac-qr-lng"><?php esc_html_e( 'Longitudine', 'advertrieste' ); ?></label>
					<input type="text" id="ac-qr-lng" name="advtr_lng" data-advtr-lng value="<?php echo esc_attr( $advtr_lng ); ?>" />
				</div>
			</div>
			<p class="advtr-aiuto"><?php esc_html_e( 'La ricerca porta all\'indirizzo; il segnaposto va trascinato sul punto esatto dell\'espositore.', 'advertrieste' ); ?></p>
		</fieldset>

		<div class="advtr-form-azioni">
			<button type="submit" class="ac-btn ac-btn-verde"><?php esc_html_e( 'Salva', 'advertrieste' ); ?></button>
		</div>
	</div>
</form>
