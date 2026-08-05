<?php
/**
 * Console admin — modifica di una scheda, senza uscire dalla console.
 *
 * Contiene sia i campi di contenuto sia quelli commerciali, che nell'area
 * clienti non compaiono: proprietario, validità, evidenza, soglia di zoom e
 * stato di pubblicazione.
 *
 * Variabili disponibili: $id (int).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Cpt\Categoria;
use AdverTrieste\Cliente\Media;
use AdverTrieste\Access\Roles;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_post = get_post( $id );
if ( ! $advtr_post || 'locale' !== $advtr_post->post_type ) {
	echo '<div class="ac-vuoto"><p>' . esc_html__( 'Scheda non trovata.', 'advertrieste' ) . '</p></div>';
	return;
}

$advtr_url      = AdminConsole::url( 'locali', array( 'id' => $id ) );
$advtr_lat      = get_post_meta( $id, 'advtr_lat', true );
$advtr_lng      = get_post_meta( $id, 'advtr_lng', true );
$advtr_servizi  = get_post_meta( $id, 'advtr_servizi', true );
$advtr_servizi  = is_array( $advtr_servizi ) ? $advtr_servizi : array();
$advtr_scelte   = wp_get_post_terms( $id, Categoria::TAXONOMY, array( 'fields' => 'slugs' ) );
$advtr_scelte   = is_array( $advtr_scelte ) ? $advtr_scelte : array();
$advtr_termini  = get_terms(
	array(
		'taxonomy'   => Categoria::TAXONOMY,
		'hide_empty' => false,
	)
);
$advtr_logo_id  = (int) get_post_meta( $id, 'advtr_logo_id', true );
$advtr_galleria = Media::galleria( $id );
?>

<p style="margin:0 0 16px">
	<a class="ac-btn ac-btn-neutro" href="<?php echo esc_url( AdminConsole::url( 'locali' ) ); ?>">
		← <?php esc_html_e( 'Torna all\'elenco', 'advertrieste' ); ?>
	</a>
	<a class="ac-btn ac-btn-neutro" href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener">
		<?php esc_html_e( 'Vedi la pagina pubblica', 'advertrieste' ); ?>
	</a>
</p>

<form class="advtr-form" method="post" action="<?php echo esc_url( $advtr_url ); ?>">
	<?php wp_nonce_field( AdminConsole::NONCE ); ?>
	<input type="hidden" name="advtr_azione" value="scheda_salva" />
	<input type="hidden" name="advtr_locale_id" value="<?php echo esc_attr( $id ); ?>" />
	<input type="hidden" name="advtr_id" value="<?php echo esc_attr( $id ); ?>" />

	<div class="ac-griglia ac-griglia-3-2" style="align-items:start">

		<div class="ac-card">
			<h3 class="ac-card-titolo"><?php esc_html_e( 'Contenuti', 'advertrieste' ); ?></h3>
			<p class="ac-card-sottotitolo"><?php esc_html_e( 'Quello che il pubblico vede sulla scheda.', 'advertrieste' ); ?></p>

			<label for="ac-titolo"><?php esc_html_e( 'Nome dell\'attività', 'advertrieste' ); ?></label>
			<input type="text" id="ac-titolo" name="advtr_titolo" required value="<?php echo esc_attr( $advtr_post->post_title ); ?>" />

			<label for="ac-desc"><?php esc_html_e( 'Descrizione', 'advertrieste' ); ?></label>
			<textarea id="ac-desc" name="advtr_descrizione" rows="6"><?php echo esc_textarea( $advtr_post->post_content ); ?></textarea>

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

			<label for="ac-servizi"><?php esc_html_e( 'Servizi', 'advertrieste' ); ?></label>
			<textarea id="ac-servizi" name="advtr_servizi" rows="4"><?php echo esc_textarea( implode( "\n", $advtr_servizi ) ); ?></textarea>
			<p class="advtr-aiuto"><?php esc_html_e( 'Uno per riga.', 'advertrieste' ); ?></p>

			<fieldset class="advtr-fieldset">
				<legend><?php esc_html_e( 'Contatti', 'advertrieste' ); ?></legend>
				<div class="advtr-griglia-2">
					<div>
						<label for="ac-tel"><?php esc_html_e( 'Telefono', 'advertrieste' ); ?></label>
						<input type="text" id="ac-tel" name="advtr_telefono" value="<?php echo esc_attr( get_post_meta( $id, 'advtr_telefono', true ) ); ?>" />
					</div>
					<div>
						<label for="ac-mail"><?php esc_html_e( 'Email', 'advertrieste' ); ?></label>
						<input type="email" id="ac-mail" name="advtr_email" value="<?php echo esc_attr( get_post_meta( $id, 'advtr_email', true ) ); ?>" />
					</div>
					<div>
						<label for="ac-sito"><?php esc_html_e( 'Sito web', 'advertrieste' ); ?></label>
						<input type="url" id="ac-sito" name="advtr_sito" value="<?php echo esc_attr( get_post_meta( $id, 'advtr_sito', true ) ); ?>" />
					</div>
					<div>
						<label for="advtr-indirizzo"><?php esc_html_e( 'Indirizzo', 'advertrieste' ); ?></label>
						<input type="text" id="advtr-indirizzo" name="advtr_indirizzo" value="<?php echo esc_attr( get_post_meta( $id, 'advtr_indirizzo', true ) ); ?>" />
					</div>
				</div>
				<label for="ac-orari"><?php esc_html_e( 'Orari', 'advertrieste' ); ?></label>
				<textarea id="ac-orari" name="advtr_orari" rows="3"><?php echo esc_textarea( get_post_meta( $id, 'advtr_orari', true ) ); ?></textarea>
			</fieldset>

			<fieldset class="advtr-fieldset">
				<legend><?php esc_html_e( 'Posizione', 'advertrieste' ); ?></legend>
				<div class="advtr-geo-riga">
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
						<label for="ac-lat"><?php esc_html_e( 'Latitudine', 'advertrieste' ); ?></label>
						<input type="text" id="ac-lat" name="advtr_lat" data-advtr-lat value="<?php echo esc_attr( $advtr_lat ); ?>" />
					</div>
					<div>
						<label for="ac-lng"><?php esc_html_e( 'Longitudine', 'advertrieste' ); ?></label>
						<input type="text" id="ac-lng" name="advtr_lng" data-advtr-lng value="<?php echo esc_attr( $advtr_lng ); ?>" />
					</div>
				</div>
			</fieldset>
		</div>

		<div>
			<div class="ac-card" style="margin-bottom:16px">
				<h3 class="ac-card-titolo"><?php esc_html_e( 'Pubblicazione', 'advertrieste' ); ?></h3>

				<label for="ac-stato"><?php esc_html_e( 'Stato', 'advertrieste' ); ?></label>
				<select id="ac-stato" name="advtr_stato_post">
					<option value="publish" <?php selected( 'publish', $advtr_post->post_status ); ?>><?php esc_html_e( 'Online', 'advertrieste' ); ?></option>
					<option value="draft" <?php selected( 'publish' !== $advtr_post->post_status ); ?>><?php esc_html_e( 'Non pubblicata', 'advertrieste' ); ?></option>
				</select>

				<label for="ac-autore"><?php esc_html_e( 'Cliente proprietario', 'advertrieste' ); ?></label>
				<select id="ac-autore" name="advtr_autore">
					<?php
					$advtr_utenti = get_users(
						array(
							'role__in' => array( Roles::CLIENTE, 'administrator' ),
							'number'   => 200,
							'orderby'  => 'display_name',
						)
					);
					foreach ( $advtr_utenti as $advtr_u ) :
						?>
						<option value="<?php echo esc_attr( $advtr_u->ID ); ?>" <?php selected( (int) $advtr_post->post_author, $advtr_u->ID ); ?>>
							<?php echo esc_html( $advtr_u->display_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="advtr-aiuto"><?php esc_html_e( 'Determina chi gestisce la scheda dall\'area clienti e ne vede le statistiche.', 'advertrieste' ); ?></p>

				<label for="ac-zoom"><?php esc_html_e( 'Zoom minimo sulla mappa', 'advertrieste' ); ?></label>
				<input type="number" id="ac-zoom" name="advtr_zoom_min" min="0" max="22"
					value="<?php echo esc_attr( get_post_meta( $id, 'advtr_zoom_min', true ) ); ?>" />
				<p class="advtr-aiuto"><?php esc_html_e( 'Da quale livello di ingrandimento compare. I locali stanno di norma a 14.', 'advertrieste' ); ?></p>
			</div>

			<div class="ac-card" style="margin-bottom:16px">
				<h3 class="ac-card-titolo"><?php esc_html_e( 'Validità', 'advertrieste' ); ?></h3>
				<label for="ac-inizio"><?php esc_html_e( 'Dal', 'advertrieste' ); ?></label>
				<input type="date" id="ac-inizio" name="advtr_data_inizio" value="<?php echo esc_attr( get_post_meta( $id, 'advtr_data_inizio', true ) ); ?>" />
				<label for="ac-fine"><?php esc_html_e( 'Al', 'advertrieste' ); ?></label>
				<input type="date" id="ac-fine" name="advtr_data_fine" value="<?php echo esc_attr( get_post_meta( $id, 'advtr_data_fine', true ) ); ?>" />
				<p class="advtr-aiuto"><?php esc_html_e( 'Alla scadenza la scheda viene sospesa dal cron e il cliente riceve gli avvisi.', 'advertrieste' ); ?></p>
			</div>

			<div class="ac-card">
				<h3 class="ac-card-titolo"><?php esc_html_e( 'Piano In Evidenza', 'advertrieste' ); ?></h3>
				<label class="advtr-check" style="margin:8px 0">
					<input type="checkbox" name="advtr_in_evidenza" value="1" <?php checked( (bool) get_post_meta( $id, 'advtr_in_evidenza', true ) ); ?> />
					<?php esc_html_e( 'Attivo', 'advertrieste' ); ?>
				</label>
				<label for="ac-ev-inizio"><?php esc_html_e( 'Dal', 'advertrieste' ); ?></label>
				<input type="date" id="ac-ev-inizio" name="advtr_evidenza_inizio" value="<?php echo esc_attr( get_post_meta( $id, 'advtr_evidenza_inizio', true ) ); ?>" />
				<label for="ac-ev-fine"><?php esc_html_e( 'Al', 'advertrieste' ); ?></label>
				<input type="date" id="ac-ev-fine" name="advtr_evidenza_fine" value="<?php echo esc_attr( get_post_meta( $id, 'advtr_evidenza_fine', true ) ); ?>" />
				<label for="ac-pri"><?php esc_html_e( 'Priorità nei risultati', 'advertrieste' ); ?></label>
				<input type="number" id="ac-pri" name="advtr_evidenza_priorita" min="0"
					value="<?php echo esc_attr( get_post_meta( $id, 'advtr_evidenza_priorita', true ) ); ?>" />
				<p class="advtr-aiuto"><?php esc_html_e( 'Più alta, più in alto compare fra i risultati della mappa.', 'advertrieste' ); ?></p>
			</div>
		</div>
	</div>

	<div class="advtr-form-azioni">
		<button type="submit" class="ac-btn ac-btn-verde"><?php esc_html_e( 'Salva la scheda', 'advertrieste' ); ?></button>
	</div>
</form>

<div class="ac-card" style="margin-top:16px">
	<h3 class="ac-card-titolo"><?php esc_html_e( 'Logo e galleria', 'advertrieste' ); ?></h3>
	<p class="ac-card-sottotitolo">
		<?php
		printf(
			/* translators: %d: numero di foto */
			esc_html__( 'Logo: %1$s · Galleria: %2$d foto.', 'advertrieste' ),
			$advtr_logo_id ? esc_html__( 'presente', 'advertrieste' ) : esc_html__( 'assente', 'advertrieste' ),
			count( $advtr_galleria )
		);
		?>
	</p>
	<p style="margin:0">
		<a class="ac-btn ac-btn-neutro" href="<?php echo esc_url( \AdverTrieste\Frontend\ClientArea::url( 'immagini' ) ); ?>">
			<?php esc_html_e( 'Gestisci le immagini', 'advertrieste' ); ?>
		</a>
	</p>
	<p class="advtr-aiuto">
		<?php esc_html_e( 'Il caricamento delle immagini passa dall\'area clienti, dove l\'uploader mostra a ciascuno solo i propri file.', 'advertrieste' ); ?>
	</p>
</div>
