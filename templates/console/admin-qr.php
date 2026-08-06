<?php
/**
 * Console admin — punti QR.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Console\Tabella;
use AdverTrieste\Cpt\PuntoQr;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_args = array(
	'post_type'      => PuntoQr::POST_TYPE,
	'post_status'    => AdminConsole::stati_elenco( array( 'publish', 'draft' ) ),
	'posts_per_page' => 300,
	'orderby'        => 'title',
	'order'          => 'ASC',
);
if ( '' !== $cerca ) {
	$advtr_args['s'] = $cerca;
}

$advtr_righe = array();
foreach ( get_posts( $advtr_args ) as $advtr_q ) {
	$advtr_lat   = get_post_meta( $advtr_q->ID, 'advtr_lat', true );
	$advtr_lng   = get_post_meta( $advtr_q->ID, 'advtr_lng', true );
	$advtr_stato = (string) get_post_meta( $advtr_q->ID, 'advtr_stato', true );
	$advtr_geo   = ( '' !== $advtr_lat && '' !== $advtr_lng );

	$advtr_righe[] = array(
		'<strong>' . esc_html( $advtr_q->post_title ) . '</strong>',
		esc_html( AdminConsole::o_trattino( (string) get_post_meta( $advtr_q->ID, 'advtr_indirizzo', true ) ) ),
		$advtr_geo
			? '<span class="ac-cella-tenue">' . esc_html( $advtr_lat . ', ' . $advtr_lng ) . '</span>'
			: Tabella::pill( __( 'Senza coordinate', 'advertrieste' ), 'attesa' ),
		'attivo' === $advtr_stato
			? Tabella::pill( __( 'Attivo', 'advertrieste' ), 'ok' )
			: Tabella::pill( __( 'Inattivo', 'advertrieste' ), '' ),
		'<span class="ac-azioni-cella">' .
			AdminConsole::azioni_cestino( 'qr', $advtr_q->ID, AdminConsole::mostra_cestino() ) .
			'<a class="ac-btn ac-btn-neutro" href="' . esc_url( AdminConsole::url( 'qr', array( 'id' => $advtr_q->ID ) ) ) . '">' .
			esc_html__( 'Apri', 'advertrieste' ) . '</a>' .
		'</span>',
	);
}

?>
<?php
$advtr_barra = AdminConsole::link_cestino( 'qr' ) .
	AdminConsole::bottone_nuovo( 'qr', __( 'Aggiungi un espositore', 'advertrieste' ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup dei componenti, già escapato.
echo '<div class="ac-barra-azioni">' . $advtr_barra . '</div>';
?>
<?php
$advtr_tabella = Tabella::rendi(
	array(
		'colonne' => array(
			__( 'Espositore', 'advertrieste' ),
			__( 'Indirizzo', 'advertrieste' ),
			__( 'Coordinate', 'advertrieste' ),
			__( 'Stato', 'advertrieste' ),
			'',
		),
		'righe'   => $advtr_righe,
		'vuoto'   => AdminConsole::vuoto( __( 'Nessun punto QR registrato.', 'advertrieste' ) ),
		'ricerca' => $cerca,
		'azione'  => AdminConsole::url(),
		'sezione' => 'qr',
	)
);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tabella::rendi() escapa intestazioni e celle.
echo $advtr_tabella;
?>
<p class="ac-card-sottotitolo" style="margin-top:14px">
	<?php esc_html_e( 'Le coordinate dei punti QR non raggiungono mai il pubblico: escono solo verso l\'area clienti, da un endpoint autenticato. Lo stato "Inattivo" oggi è informativo — l\'endpoint non filtra ancora per stato.', 'advertrieste' ); ?>
</p>
