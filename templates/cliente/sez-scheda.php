<?php
/**
 * Sezione "La mia scheda": form di modifica dei contenuti.
 *
 * Variabili disponibili: $locale (WP_Post).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Frontend\ClientArea;
use AdverTrieste\Cpt\Categoria;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_lat      = get_post_meta( $locale->ID, 'advtr_lat', true );
$advtr_lng      = get_post_meta( $locale->ID, 'advtr_lng', true );
$advtr_servizi  = get_post_meta( $locale->ID, 'advtr_servizi', true );
$advtr_servizi  = is_array( $advtr_servizi ) ? $advtr_servizi : array();
$advtr_termini  = wp_get_post_terms( $locale->ID, Categoria::TAXONOMY, array( 'fields' => 'slugs' ) );
$advtr_termini  = is_array( $advtr_termini ) ? $advtr_termini : array();
$advtr_tutte    = get_terms(
	array(
		'taxonomy'   => Categoria::TAXONOMY,
		'hide_empty' => false,
	)
);
$advtr_pubblica = 'publish' === $locale->post_status;
?>
<form class="advtr-form" method="post" action="<?php echo esc_url( ClientArea::url( 'scheda' ) ); ?>">
	<?php wp_nonce_field( ClientArea::NONCE ); ?>
	<input type="hidden" name="advtr_azione" value="scheda_salva" />
	<input type="hidden" name="advtr_locale_id" value="<?php echo esc_attr( $locale->ID ); ?>" />

	<div class="advtr-form-stato">
		<?php if ( $advtr_pubblica ) : ?>
			<span class="advtr-pill ok"><?php esc_html_e( 'Online', 'advertrieste' ); ?></span>
			<a href="<?php echo esc_url( get_permalink( $locale ) ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'Vedi la scheda pubblica', 'advertrieste' ); ?>
			</a>
		<?php else : ?>
			<span class="advtr-pill attesa"><?php esc_html_e( 'In attesa di pubblicazione', 'advertrieste' ); ?></span>
			<span class="advtr-nota"><?php esc_html_e( 'Puoi già completarla: sarà visibile appena la redazione la attiva.', 'advertrieste' ); ?></span>
		<?php endif; ?>
	</div>

	<label for="advtr-titolo"><?php esc_html_e( 'Nome dell\'attività', 'advertrieste' ); ?></label>
	<input type="text" id="advtr-titolo" name="advtr_titolo" required
		value="<?php echo esc_attr( $locale->post_title ); ?>" />

	<label for="advtr-descrizione"><?php esc_html_e( 'Descrizione', 'advertrieste' ); ?></label>
	<textarea id="advtr-descrizione" name="advtr_descrizione" rows="6"><?php echo esc_textarea( $locale->post_content ); ?></textarea>
	<p class="advtr-aiuto"><?php esc_html_e( 'Racconta l\'attività in poche righe: è il testo che appare sulla scheda pubblica.', 'advertrieste' ); ?></p>

	<fieldset class="advtr-fieldset">
		<legend><?php esc_html_e( 'Categorie', 'advertrieste' ); ?></legend>
		<div class="advtr-check-griglia">
			<?php foreach ( (array) $advtr_tutte as $advtr_term ) : ?>
				<label class="advtr-check">
					<input type="checkbox" name="advtr_categorie[]"
						value="<?php echo esc_attr( $advtr_term->slug ); ?>"
						<?php checked( in_array( $advtr_term->slug, $advtr_termini, true ) ); ?> />
					<?php echo esc_html( $advtr_term->name ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<p class="advtr-aiuto"><?php esc_html_e( 'Determinano in quali filtri della mappa compare la tua attività.', 'advertrieste' ); ?></p>
	</fieldset>

	<label for="advtr-servizi"><?php esc_html_e( 'Servizi', 'advertrieste' ); ?></label>
	<textarea id="advtr-servizi" name="advtr_servizi" rows="5"><?php echo esc_textarea( implode( "\n", $advtr_servizi ) ); ?></textarea>
	<p class="advtr-aiuto"><?php esc_html_e( 'Uno per riga. Esempio: Wi-Fi gratuito, Dehors, Accessibile.', 'advertrieste' ); ?></p>

	<fieldset class="advtr-fieldset">
		<legend><?php esc_html_e( 'Contatti', 'advertrieste' ); ?></legend>

		<div class="advtr-griglia-2">
			<div>
				<label for="advtr-telefono"><?php esc_html_e( 'Telefono', 'advertrieste' ); ?></label>
				<input type="text" id="advtr-telefono" name="advtr_telefono"
					value="<?php echo esc_attr( get_post_meta( $locale->ID, 'advtr_telefono', true ) ); ?>" />
			</div>
			<div>
				<label for="advtr-email"><?php esc_html_e( 'Email', 'advertrieste' ); ?></label>
				<input type="email" id="advtr-email" name="advtr_email"
					value="<?php echo esc_attr( get_post_meta( $locale->ID, 'advtr_email', true ) ); ?>" />
			</div>
			<div>
				<label for="advtr-sito"><?php esc_html_e( 'Sito web', 'advertrieste' ); ?></label>
				<input type="url" id="advtr-sito" name="advtr_sito" placeholder="https://"
					value="<?php echo esc_attr( get_post_meta( $locale->ID, 'advtr_sito', true ) ); ?>" />
			</div>
			<div>
				<label for="advtr-indirizzo"><?php esc_html_e( 'Indirizzo', 'advertrieste' ); ?></label>
				<input type="text" id="advtr-indirizzo" name="advtr_indirizzo"
					value="<?php echo esc_attr( get_post_meta( $locale->ID, 'advtr_indirizzo', true ) ); ?>" />
			</div>
		</div>

		<label for="advtr-orari"><?php esc_html_e( 'Orari', 'advertrieste' ); ?></label>
		<textarea id="advtr-orari" name="advtr_orari" rows="4"><?php echo esc_textarea( get_post_meta( $locale->ID, 'advtr_orari', true ) ); ?></textarea>
		<p class="advtr-aiuto"><?php esc_html_e( 'Una riga per fascia. Esempio: Lun–Sab 8:00–20:00.', 'advertrieste' ); ?></p>
	</fieldset>

	<fieldset class="advtr-fieldset">
		<legend><?php esc_html_e( 'Posizione sulla mappa', 'advertrieste' ); ?></legend>
		<p class="advtr-aiuto"><?php esc_html_e( 'Trascina il segnaposto sulla posizione esatta dell\'ingresso.', 'advertrieste' ); ?></p>

		<div
			class="advtr-mappa-picker"
			data-advtr-picker
			data-lat="<?php echo esc_attr( '' !== $advtr_lat ? $advtr_lat : 45.6495 ); ?>"
			data-lng="<?php echo esc_attr( '' !== $advtr_lng ? $advtr_lng : 13.7768 ); ?>"
		></div>

		<div class="advtr-griglia-2">
			<div>
				<label for="advtr-lat"><?php esc_html_e( 'Latitudine', 'advertrieste' ); ?></label>
				<input type="text" id="advtr-lat" name="advtr_lat" data-advtr-lat
					value="<?php echo esc_attr( $advtr_lat ); ?>" />
			</div>
			<div>
				<label for="advtr-lng"><?php esc_html_e( 'Longitudine', 'advertrieste' ); ?></label>
				<input type="text" id="advtr-lng" name="advtr_lng" data-advtr-lng
					value="<?php echo esc_attr( $advtr_lng ); ?>" />
			</div>
		</div>
	</fieldset>

	<label for="advtr-place"><?php esc_html_e( 'Google Place ID (facoltativo)', 'advertrieste' ); ?></label>
	<input type="text" id="advtr-place" name="advtr_place_id"
		value="<?php echo esc_attr( get_post_meta( $locale->ID, 'advtr_place_id', true ) ); ?>" />
	<p class="advtr-aiuto"><?php esc_html_e( 'Serve a mostrare le recensioni Google sulla tua scheda. Se non sai cos\'è, lascia vuoto.', 'advertrieste' ); ?></p>

	<div class="advtr-form-azioni">
		<button type="submit" class="advtr-btn advtr-btn-primario"><?php esc_html_e( 'Salva modifiche', 'advertrieste' ); ?></button>
	</div>
</form>
