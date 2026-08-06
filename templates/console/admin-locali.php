<?php
/**
 * Console admin — elenco dei locali con le azioni ricorrenti.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Console\Tabella;
use AdverTrieste\Cliente\Abbonamento;
use AdverTrieste\Cliente\Evidenza;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_url   = AdminConsole::url( 'locali' );
$advtr_righe = array();

$advtr_cestino = AdminConsole::mostra_cestino();

foreach ( AdminConsole::locali( $cerca ) as $advtr_p ) {
	$advtr_autore = get_userdata( $advtr_p->post_author );
	$advtr_stato  = Abbonamento::stato( $advtr_p->ID );
	$advtr_online = 'publish' === $advtr_p->post_status;
	$advtr_evid   = Evidenza::attiva( $advtr_p->ID );

	// Su una scheda cestinata pubblicare o mettere in evidenza non ha senso:
	// prima la si riporta in vita, poi si decide cosa farne.
	$advtr_extra = $advtr_cestino ? '' : (
		Tabella::azione(
			array(
				'azione'    => $advtr_online ? 'sospendi' : 'pubblica',
				'etichetta' => $advtr_online ? __( 'Sospendi', 'advertrieste' ) : __( 'Pubblica', 'advertrieste' ),
				'url'       => $advtr_url,
				'nonce'     => AdminConsole::NONCE,
				'classe'    => $advtr_online ? 'ac-btn ac-btn-fragile' : 'ac-btn ac-btn-verde',
				'conferma'  => $advtr_online ? __( 'Confermi?', 'advertrieste' ) : '',
				'campi'     => array(
					'advtr_id'      => $advtr_p->ID,
					'advtr_sezione' => 'locali',
				),
			)
		) .
		Tabella::azione(
			array(
				'azione'    => 'evidenza',
				'etichetta' => $advtr_evid ? __( 'Togli evidenza', 'advertrieste' ) : __( 'In evidenza', 'advertrieste' ),
				'url'       => $advtr_url,
				'nonce'     => AdminConsole::NONCE,
				'campi'     => array(
					'advtr_id'      => $advtr_p->ID,
					'advtr_sezione' => 'locali',
				),
			)
		)
	);

	$advtr_righe[] = array(
		'<strong>' . esc_html( $advtr_p->post_title ) . '</strong><br />' .
			'<span class="ac-cella-tenue">' . esc_html( (string) get_post_meta( $advtr_p->ID, 'advtr_indirizzo', true ) ) . '</span>',
		esc_html( $advtr_autore ? $advtr_autore->display_name : '—' ),
		$advtr_online
			? Tabella::pill( __( 'Online', 'advertrieste' ), 'ok' )
			: Tabella::pill( __( 'Non pubblicata', 'advertrieste' ), 'attesa' ),
		Tabella::pill( $advtr_stato['etichetta'], $advtr_stato['pill'] ) .
			( Abbonamento::data_scadenza( $advtr_p->ID ) ? '<br /><span class="ac-cella-tenue">' . esc_html( Abbonamento::data_scadenza( $advtr_p->ID ) ) . '</span>' : '' ),
		$advtr_evid ? Tabella::pill( __( 'Attivo', 'advertrieste' ), 'oro' ) : '<span class="ac-cella-tenue">—</span>',
		'<span class="ac-azioni-cella">' .
			$advtr_extra .
			AdminConsole::azioni_cestino( 'locali', $advtr_p->ID, $advtr_cestino ) .
			'<a class="ac-btn ac-btn-neutro" href="' . esc_url( AdminConsole::url( 'locali', array( 'id' => $advtr_p->ID ) ) ) . '">' .
			esc_html__( 'Apri', 'advertrieste' ) . '</a>' .
		'</span>',
	);
}

?>
<?php
$advtr_barra = AdminConsole::link_cestino( 'locali' ) .
	AdminConsole::bottone_nuovo( 'locali', __( 'Aggiungi un locale', 'advertrieste' ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup dei componenti, già escapato.
echo '<div class="ac-barra-azioni">' . $advtr_barra . '</div>';
?>
<?php
$advtr_tabella = Tabella::rendi(
	array(
		'colonne' => array(
			__( 'Attività', 'advertrieste' ),
			__( 'Cliente', 'advertrieste' ),
			__( 'Visibilità', 'advertrieste' ),
			__( 'Abbonamento', 'advertrieste' ),
			__( 'In evidenza', 'advertrieste' ),
			'',
		),
		'righe'   => $advtr_righe,
		'vuoto'   => '' !== $cerca
			? __( 'Nessun locale corrisponde alla ricerca.', 'advertrieste' )
			: AdminConsole::vuoto( __( 'Non ci sono ancora locali.', 'advertrieste' ) ),
		'ricerca' => $cerca,
		'azione'  => AdminConsole::url(),
		'sezione' => 'locali',
	)
);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tabella::rendi() escapa intestazioni e celle.
echo $advtr_tabella;
