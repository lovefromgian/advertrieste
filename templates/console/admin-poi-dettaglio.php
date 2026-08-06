<?php
/**
 * Console admin — modifica di un punto d'interesse.
 *
 * Variabili disponibili: $id (int).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Cpt\Categoria;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_post = get_post( $id );
if ( ! $advtr_post || 'poi' !== $advtr_post->post_type ) {
	echo '<div class="ac-vuoto"><p>' . esc_html__( 'Punto d\'interesse non trovato.', 'advertrieste' ) . '</p></div>';
	return;
}

$advtr_lat     = get_post_meta( $id, 'advtr_lat', true );
$advtr_lng     = get_post_meta( $id, 'advtr_lng', true );
$advtr_scelte  = wp_get_post_terms( $id, Categoria::TAXONOMY, array( 'fields' => 'slugs' ) );
$advtr_scelte  = is_array( $advtr_scelte ) ? $advtr_scelte : array();
$advtr_termini = get_terms(
	array(
		'taxonomy'   => Categoria::TAXONOMY,
		'hide_empty' => false,
	)
);
?>
<p style="margin:0 0 16px">
	<a class="ac-btn ac-btn-neutro" href="<?php echo esc_url( AdminConsole::url( 'poi' ) ); ?>">
		← <?php esc_html_e( 'Torna all\'elenco', 'advertrieste' ); ?>
	</a>
	<a class="ac-btn ac-btn-neutro" href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener">
		<?php esc_html_e( 'Vedi la pagina pubblica', 'advertrieste' ); ?>
	</a>
</p>

<form class="advtr-form" method="post" action="<?php echo esc_url( AdminConsole::url( 'poi', array( 'id' => $id ) ) ); ?>">
	<?php wp_nonce_field( AdminConsole::NONCE ); ?>
	<input type="hidden" name="advtr_azione" value="poi_salva" />
	<input type="hidden" name="advtr_id" value="<?php echo esc_attr( $id ); ?>" />

	<div class="ac-griglia ac-griglia-3-2" style="align-items:start">
		<div class="ac-card">
			<h3 class="ac-card-titolo"><?php esc_html_e( 'Contenuti', 'advertrieste' ); ?></h3>

			<label for="ac-poi-titolo"><?php esc_html_e( 'Nome del luogo', 'advertrieste' ); ?></label>
			<input type="text" id="ac-poi-titolo" name="advtr_titolo" required value="<?php echo esc_attr( $advtr_post->post_title ); ?>" />

			<label for="ac-poi-desc"><?php esc_html_e( 'Descrizione', 'advertrieste' ); ?></label>
			<textarea id="ac-poi-desc" name="advtr_descrizione" rows="6"><?php echo esc_textarea( $advtr_post->post_content ); ?></textarea>

			<fieldset class="advtr-fieldset">
				<legend><?php esc_html_e( 'Categorie', 'advertrieste' ); ?></legend>
				<div class="advtr-check-griglia">
					<?php foreach ( (array) $advtr_termini as $advtr_t ) : ?>
						<label class="advtr-check">
							<input type="checkbox" name="advtr_categorie[]" value="<?php echo esc_attr( $advtr_t->slug ); ?>"
								<?php checked( in_array( $advtr_t->slug, $advtr_scelte, true ) ); ?> />
							<?php echo esc_html( $advtr_t->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<fieldset class="advtr-fieldset">
				<legend><?php esc_html_e( 'Posizione', 'advertrieste' ); ?></legend>
				<label for="advtr-indirizzo"><?php esc_html_e( 'Indirizzo o nome del luogo (solo per la ricerca)', 'advertrieste' ); ?></label>
				<input type="text" id="advtr-indirizzo" name="advtr_indirizzo"
					value="<?php echo esc_attr( $advtr_post->post_title ); ?>" />
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
						<label for="ac-poi-lat"><?php esc_html_e( 'Latitudine', 'advertrieste' ); ?></label>
						<input type="text" id="ac-poi-lat" name="advtr_lat" data-advtr-lat value="<?php echo esc_attr( $advtr_lat ); ?>" />
					</div>
					<div>
						<label for="ac-poi-lng"><?php esc_html_e( 'Longitudine', 'advertrieste' ); ?></label>
						<input type="text" id="ac-poi-lng" name="advtr_lng" data-advtr-lng value="<?php echo esc_attr( $advtr_lng ); ?>" />
					</div>
				</div>
			</fieldset>
		</div>

		<div class="ac-card">
			<h3 class="ac-card-titolo"><?php esc_html_e( 'Impostazioni', 'advertrieste' ); ?></h3>

			<label for="ac-poi-stato"><?php esc_html_e( 'Pubblicazione', 'advertrieste' ); ?></label>
			<select id="ac-poi-stato" name="advtr_stato_post">
				<option value="publish" <?php selected( 'publish', $advtr_post->post_status ); ?>><?php esc_html_e( 'Online', 'advertrieste' ); ?></option>
				<option value="draft" <?php selected( 'publish' !== $advtr_post->post_status ); ?>><?php esc_html_e( 'Non pubblicato', 'advertrieste' ); ?></option>
			</select>

			<label for="ac-poi-tipo"><?php esc_html_e( 'Tipo', 'advertrieste' ); ?></label>
			<input type="text" id="ac-poi-tipo" name="advtr_tipo" list="ac-tipi"
				value="<?php echo esc_attr( get_post_meta( $id, 'advtr_tipo', true ) ); ?>" />
			<datalist id="ac-tipi">
				<option value="museo"></option>
				<option value="castello"></option>
				<option value="monumento"></option>
				<option value="grotta"></option>
				<option value="parco"></option>
				<option value="chiesa"></option>
			</datalist>
			<p class="advtr-aiuto"><?php esc_html_e( 'Determina l\'icona sulla pagina pubblica.', 'advertrieste' ); ?></p>

			<label for="ac-poi-zoom"><?php esc_html_e( 'Zoom minimo', 'advertrieste' ); ?></label>
			<input type="number" id="ac-poi-zoom" name="advtr_zoom_min" min="0" max="22"
				value="<?php echo esc_attr( get_post_meta( $id, 'advtr_zoom_min', true ) ); ?>" />
			<p class="advtr-aiuto"><?php esc_html_e( 'I punti d\'interesse stanno di norma a 0: si vedono già da lontano.', 'advertrieste' ); ?></p>
		</div>
	</div>

	<div class="advtr-form-azioni">
		<button type="submit" class="ac-btn ac-btn-verde"><?php esc_html_e( 'Salva', 'advertrieste' ); ?></button>
	</div>
</form>
<?php
$advtr_zona = AdminConsole::zona_pericolosa( 'poi', $id );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup del componente, già escapato.
echo $advtr_zona;
