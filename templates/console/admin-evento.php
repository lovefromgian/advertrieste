<?php
/**
 * Console admin — modifica di un evento e stato del workflow.
 *
 * Variabili disponibili: $id (int).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Console\Tabella;
use AdverTrieste\Cpt\Locale;
use AdverTrieste\Evento\Workflow;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_post = get_post( $id );
if ( ! $advtr_post || 'evento' !== $advtr_post->post_type ) {
	echo '<div class="ac-vuoto"><p>' . esc_html__( 'Evento non trovato.', 'advertrieste' ) . '</p></div>';
	return;
}

$advtr_stato     = Workflow::stato( $id );
$advtr_pubblica  = Workflow::public_version( $id );
$advtr_tipo      = (string) get_post_meta( $id, 'advtr_tipo_evento', true );
$advtr_collegati = get_post_meta( $id, 'advtr_locali_collegati', true );
$advtr_collegati = is_array( $advtr_collegati ) ? array_map( 'absint', $advtr_collegati ) : array();

$advtr_eti = array(
	Workflow::STATO_BOZZA        => array( __( 'Bozza', 'advertrieste' ), '' ),
	Workflow::STATO_IN_REVISIONE => array( __( 'In revisione', 'advertrieste' ), 'attesa' ),
	Workflow::STATO_PUBBLICATO   => array( __( 'Pubblicato', 'advertrieste' ), 'ok' ),
);
$advtr_e   = $advtr_eti[ $advtr_stato ] ?? array( $advtr_stato, '' );
?>
<p style="margin:0 0 16px">
	<a class="ac-btn ac-btn-neutro" href="<?php echo esc_url( AdminConsole::url( 'eventi' ) ); ?>">
		← <?php esc_html_e( 'Torna all\'elenco', 'advertrieste' ); ?>
	</a>
</p>

<div class="ac-avviso <?php echo esc_attr( $advtr_e[1] ? $advtr_e[1] : '' ); ?>" style="margin-bottom:20px">
	<span class="ac-avviso-testo">
		<span class="ac-avviso-titolo">
			<?php esc_html_e( 'Stato del workflow:', 'advertrieste' ); ?> <?php echo esc_html( $advtr_e[0] ); ?>
		</span><br />
		<span class="ac-avviso-dett">
			<?php
			echo $advtr_pubblica
				? esc_html__( 'Il pubblico vede l\'ultima versione approvata. Le modifiche qui non vanno online finché non approvi.', 'advertrieste' )
				: esc_html__( 'Non è mai stato approvato: il pubblico non lo vede affatto.', 'advertrieste' );
			?>
		</span>
	</span>
	<?php
	$advtr_bottone = Tabella::azione(
		array(
			'azione'    => 'approva_evento',
			'etichetta' => $advtr_pubblica ? __( 'Approva le modifiche', 'advertrieste' ) : __( 'Approva e pubblica', 'advertrieste' ),
			'url'       => AdminConsole::url( 'eventi', array( 'id' => $id ) ),
			'nonce'     => AdminConsole::NONCE,
			'classe'    => 'ac-btn ac-btn-verde',
			'campi'     => array(
				'advtr_id'      => $id,
				'advtr_sezione' => 'eventi',
			),
		)
	);
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup del componente, già escapato.
	echo $advtr_bottone;
	?>
</div>

<form class="advtr-form" method="post" action="<?php echo esc_url( AdminConsole::url( 'eventi', array( 'id' => $id ) ) ); ?>">
	<?php wp_nonce_field( AdminConsole::NONCE ); ?>
	<input type="hidden" name="advtr_azione" value="evento_salva" />
	<input type="hidden" name="advtr_id" value="<?php echo esc_attr( $id ); ?>" />

	<div class="ac-griglia ac-griglia-3-2" style="align-items:start">
		<div class="ac-card">
			<h3 class="ac-card-titolo"><?php esc_html_e( 'Contenuti', 'advertrieste' ); ?></h3>

			<label for="ac-ev-titolo"><?php esc_html_e( 'Titolo', 'advertrieste' ); ?></label>
			<input type="text" id="ac-ev-titolo" name="advtr_titolo" required value="<?php echo esc_attr( $advtr_post->post_title ); ?>" />

			<label for="ac-ev-desc"><?php esc_html_e( 'Descrizione', 'advertrieste' ); ?></label>
			<textarea id="ac-ev-desc" name="advtr_descrizione" rows="7"><?php echo esc_textarea( $advtr_post->post_content ); ?></textarea>

			<fieldset class="advtr-fieldset">
				<legend><?php esc_html_e( 'Locali aderenti', 'advertrieste' ); ?></legend>
				<p class="advtr-aiuto" style="margin-top:0">
					<?php esc_html_e( 'Durante un grande evento in corso, i locali selezionati sono evidenziati sulla mappa.', 'advertrieste' ); ?>
				</p>
				<div class="advtr-check-griglia">
					<?php
					foreach ( get_posts(
						array(
							'post_type'      => Locale::POST_TYPE,
							'post_status'    => 'publish',
							'posts_per_page' => 200,
							'orderby'        => 'title',
							'order'          => 'ASC',
						)
					) as $advtr_l ) :
						?>
						<label class="advtr-check">
							<input type="checkbox" name="advtr_locali_collegati[]" value="<?php echo esc_attr( $advtr_l->ID ); ?>"
								<?php checked( in_array( $advtr_l->ID, $advtr_collegati, true ) ); ?> />
							<?php echo esc_html( $advtr_l->post_title ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		</div>

		<div class="ac-card">
			<h3 class="ac-card-titolo"><?php esc_html_e( 'Impostazioni', 'advertrieste' ); ?></h3>

			<label for="ac-ev-tipo"><?php esc_html_e( 'Tipo di evento', 'advertrieste' ); ?></label>
			<select id="ac-ev-tipo" name="advtr_tipo_evento">
				<option value="grande" <?php selected( 'grande', $advtr_tipo ); ?>><?php esc_html_e( 'Grande evento cittadino', 'advertrieste' ); ?></option>
				<option value="organizzatore" <?php selected( 'grande' !== $advtr_tipo ); ?>><?php esc_html_e( 'Evento di organizzatore', 'advertrieste' ); ?></option>
			</select>

			<label for="ac-ev-inizio"><?php esc_html_e( 'Data di inizio', 'advertrieste' ); ?></label>
			<input type="date" id="ac-ev-inizio" name="advtr_data_inizio"
				value="<?php echo esc_attr( get_post_meta( $id, 'advtr_data_inizio', true ) ); ?>" />

			<label for="ac-ev-fine"><?php esc_html_e( 'Data di fine', 'advertrieste' ); ?></label>
			<input type="date" id="ac-ev-fine" name="advtr_data_fine"
				value="<?php echo esc_attr( get_post_meta( $id, 'advtr_data_fine', true ) ); ?>" />

			<p class="advtr-aiuto" style="margin-top:14px">
				<?php esc_html_e( 'Salvare riporta l\'evento in bozza: è la stessa regola che vale per gli organizzatori, e impedisce che una modifica finisca online senza una decisione.', 'advertrieste' ); ?>
			</p>
		</div>
	</div>

	<div class="advtr-form-azioni">
		<button type="submit" class="ac-btn ac-btn-verde"><?php esc_html_e( 'Salva (senza pubblicare)', 'advertrieste' ); ?></button>
	</div>
</form>
