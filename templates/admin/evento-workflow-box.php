<?php
/**
 * Template del meta box "Workflow revisione".
 *
 * Variabili (da EventoMeta::render_workflow):
 *   $post       WP_Post
 *   $stato      string   stato workflow corrente
 *   $pubblicata bool     esiste una versione pubblica approvata
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_etichette = array(
	'bozza'        => __( 'Bozza', 'advertrieste' ),
	'in_revisione' => __( 'In revisione', 'advertrieste' ),
	'pubblicato'   => __( 'Pubblicato', 'advertrieste' ),
);
?>
<div class="advtr-wf" data-advtr-wf="<?php echo esc_attr( $post->ID ); ?>">
	<p>
		<strong><?php esc_html_e( 'Stato:', 'advertrieste' ); ?></strong>
		<span class="advtr-wf-stato"><?php echo esc_html( $advtr_etichette[ $stato ] ?? $stato ); ?></span>
	</p>
	<p class="description">
		<?php
		if ( $pubblicata ) {
			esc_html_e( 'Il pubblico vede l\'ultima versione approvata. Le modifiche vanno ri-approvate.', 'advertrieste' );
		} else {
			esc_html_e( 'Non ancora pubblicato: invisibile al pubblico finché non approvato.', 'advertrieste' );
		}
		?>
	</p>
	<p>
		<button type="button" class="button" data-advtr-wf-submit="1"><?php esc_html_e( 'Invia in revisione', 'advertrieste' ); ?></button>
		<button type="button" class="button button-primary" data-advtr-wf-approve="1"><?php esc_html_e( 'Approva e pubblica', 'advertrieste' ); ?></button>
	</p>
	<p class="advtr-wf-esito" role="status"></p>
	<p class="description"><?php esc_html_e( 'Salva le modifiche prima di inviare/approvare.', 'advertrieste' ); ?></p>
</div>
