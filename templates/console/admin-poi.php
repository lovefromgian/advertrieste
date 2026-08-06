<?php
/**
 * Console admin — punti d'interesse.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Console\Tabella;
use AdverTrieste\Cpt\Poi;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_url  = AdminConsole::url( 'poi' );
$advtr_args = array(
	'post_type'      => Poi::POST_TYPE,
	'post_status'    => AdminConsole::stati_elenco( array( 'publish', 'draft' ) ),
	'posts_per_page' => 200,
	'orderby'        => 'title',
	'order'          => 'ASC',
);
if ( '' !== $cerca ) {
	$advtr_args['s'] = $cerca;
}

$advtr_righe = array();
foreach ( get_posts( $advtr_args ) as $advtr_p ) {
	$advtr_online = 'publish' === $advtr_p->post_status;
	$advtr_lat    = get_post_meta( $advtr_p->ID, 'advtr_lat', true );
	$advtr_lng    = get_post_meta( $advtr_p->ID, 'advtr_lng', true );
	$advtr_cat    = wp_get_post_terms( $advtr_p->ID, 'categoria', array( 'fields' => 'names' ) );

	$advtr_righe[] = array(
		'<strong>' . esc_html( $advtr_p->post_title ) . '</strong>',
		esc_html( AdminConsole::o_trattino( (string) get_post_meta( $advtr_p->ID, 'advtr_tipo', true ) ) ),
		esc_html( is_array( $advtr_cat ) && $advtr_cat ? implode( ', ', $advtr_cat ) : '—' ),
		( '' !== $advtr_lat && '' !== $advtr_lng )
			? '<span class="ac-cella-tenue">' . esc_html( $advtr_lat . ', ' . $advtr_lng ) . '</span>'
			: Tabella::pill( __( 'Senza coordinate', 'advertrieste' ), 'attesa' ),
		$advtr_online
			? Tabella::pill( __( 'Online', 'advertrieste' ), 'ok' )
			: Tabella::pill( __( 'Non pubblicato', 'advertrieste' ), 'attesa' ),
		'<span class="ac-azioni-cella">' .
			( AdminConsole::mostra_cestino() ? '' : Tabella::azione(
				array(
					'azione'    => $advtr_online ? 'sospendi' : 'pubblica',
					'etichetta' => $advtr_online ? __( 'Ritira', 'advertrieste' ) : __( 'Pubblica', 'advertrieste' ),
					'url'       => $advtr_url,
					'nonce'     => AdminConsole::NONCE,
					'classe'    => $advtr_online ? 'ac-btn ac-btn-fragile' : 'ac-btn ac-btn-verde',
					'conferma'  => $advtr_online ? __( 'Confermi?', 'advertrieste' ) : '',
					'campi'     => array(
						'advtr_id'      => $advtr_p->ID,
						'advtr_sezione' => 'poi',
					),
				)
			) ) .
			AdminConsole::azioni_cestino( 'poi', $advtr_p->ID, AdminConsole::mostra_cestino() ) .
			'<a class="ac-btn ac-btn-neutro" href="' . esc_url( AdminConsole::url( 'poi', array( 'id' => $advtr_p->ID ) ) ) . '">' .
			esc_html__( 'Apri', 'advertrieste' ) . '</a>' .
		'</span>',
	);
}

?>
<?php
$advtr_barra = AdminConsole::link_cestino( 'poi' ) .
	AdminConsole::bottone_nuovo( 'poi', __( 'Aggiungi un punto d\'interesse', 'advertrieste' ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup dei componenti, già escapato.
echo '<div class="ac-barra-azioni">' . $advtr_barra . '</div>';
?>
<?php
$advtr_tabella = Tabella::rendi(
	array(
		'colonne' => array(
			__( 'Luogo', 'advertrieste' ),
			__( 'Tipo', 'advertrieste' ),
			__( 'Categorie', 'advertrieste' ),
			__( 'Coordinate', 'advertrieste' ),
			__( 'Visibilità', 'advertrieste' ),
			'',
		),
		'righe'   => $advtr_righe,
		'vuoto'   => AdminConsole::vuoto( __( 'Nessun punto d\'interesse.', 'advertrieste' ) ),
		'ricerca' => $cerca,
		'azione'  => AdminConsole::url(),
		'sezione' => 'poi',
	)
);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tabella::rendi() escapa intestazioni e celle.
echo $advtr_tabella;
