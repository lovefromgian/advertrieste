<?php
/**
 * Console admin — eventi e stato del workflow di revisione.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Console\Tabella;
use AdverTrieste\Cpt\Evento;
use AdverTrieste\Evento\Workflow;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_url  = AdminConsole::url( 'eventi' );
$advtr_args = array(
	'post_type'      => Evento::POST_TYPE,
	'post_status'    => array( 'publish', 'pending', 'draft' ),
	'posts_per_page' => 200,
	'orderby'        => 'date',
	'order'          => 'DESC',
);
if ( '' !== $cerca ) {
	$advtr_args['s'] = $cerca;
}

$advtr_etichette = array(
	Workflow::STATO_BOZZA        => array( __( 'Bozza', 'advertrieste' ), '' ),
	Workflow::STATO_IN_REVISIONE => array( __( 'In revisione', 'advertrieste' ), 'attesa' ),
	Workflow::STATO_PUBBLICATO   => array( __( 'Pubblicato', 'advertrieste' ), 'ok' ),
);

$advtr_righe = array();
foreach ( get_posts( $advtr_args ) as $advtr_e ) {
	$advtr_stato = Workflow::stato( $advtr_e->ID );
	$advtr_eti   = $advtr_etichette[ $advtr_stato ] ?? array( $advtr_stato, '' );
	$advtr_org   = get_userdata( $advtr_e->post_author );
	$advtr_pub   = Workflow::public_version( $advtr_e->ID );

	$advtr_righe[] = array(
		'<strong>' . esc_html( $advtr_e->post_title ) . '</strong>',
		esc_html( (string) get_post_meta( $advtr_e->ID, 'advtr_tipo_evento', true ) ),
		esc_html( $advtr_org ? $advtr_org->display_name : '—' ),
		Tabella::pill( $advtr_eti[0], $advtr_eti[1] ),
		$advtr_pub
			? esc_html__( 'sì', 'advertrieste' )
			: '<span class="ac-cella-tenue">' . esc_html__( 'mai', 'advertrieste' ) . '</span>',
		'<span class="ac-azioni-cella">' .
			Tabella::azione(
				array(
					'azione'    => 'approva_evento',
					'etichetta' => $advtr_pub ? __( 'Ri-approva', 'advertrieste' ) : __( 'Approva', 'advertrieste' ),
					'url'       => $advtr_url,
					'nonce'     => AdminConsole::NONCE,
					'classe'    => Workflow::STATO_IN_REVISIONE === $advtr_stato ? 'ac-btn ac-btn-verde' : 'ac-btn ac-btn-neutro',
					'campi'     => array(
						'advtr_id'      => $advtr_e->ID,
						'advtr_sezione' => 'eventi',
					),
				)
			) .
			'<a class="ac-btn ac-btn-neutro" href="' . esc_url( AdminConsole::url( 'eventi', array( 'id' => $advtr_e->ID ) ) ) . '">' .
			esc_html__( 'Apri', 'advertrieste' ) . '</a>' .
		'</span>',
	);
}

$advtr_tabella = Tabella::rendi(
	array(
		'colonne' => array(
			__( 'Evento', 'advertrieste' ),
			__( 'Tipo', 'advertrieste' ),
			__( 'Organizzatore', 'advertrieste' ),
			__( 'Workflow', 'advertrieste' ),
			__( 'Già online', 'advertrieste' ),
			'',
		),
		'righe'   => $advtr_righe,
		'vuoto'   => __( 'Nessun evento.', 'advertrieste' ),
		'ricerca' => $cerca,
		'azione'  => AdminConsole::url(),
		'sezione' => 'eventi',
	)
);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tabella::rendi() escapa intestazioni e celle.
echo $advtr_tabella;
?>
<p class="ac-card-sottotitolo" style="margin-top:14px">
	<?php esc_html_e( 'Approvare copia lo stato attuale nella versione pubblica. Finché non lo fai, il pubblico vede l\'ultima versione approvata: una bozza non finisce mai online per errore.', 'advertrieste' ); ?>
</p>
