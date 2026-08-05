<?php
/**
 * Console admin — offerte dei clienti.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Console\Tabella;
use AdverTrieste\Cpt\Offerta;
use AdverTrieste\Coupon\Coupon;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_url  = AdminConsole::url( 'offerte' );
$advtr_args = array(
	'post_type'      => Offerta::POST_TYPE,
	'post_status'    => array( 'publish', 'pending', 'draft' ),
	'posts_per_page' => 200,
	'orderby'        => 'date',
	'order'          => 'DESC',
);
if ( '' !== $cerca ) {
	$advtr_args['s'] = $cerca;
}

$advtr_righe = array();
foreach ( get_posts( $advtr_args ) as $advtr_o ) {
	$advtr_loc_id = (int) get_post_meta( $advtr_o->ID, 'advtr_locale_id', true );
	$advtr_loc    = $advtr_loc_id ? get_post( $advtr_loc_id ) : null;
	$advtr_attiva = Coupon::is_offer_active( $advtr_o->ID );
	$advtr_online = 'publish' === $advtr_o->post_status;

	$advtr_righe[] = array(
		'<strong>' . esc_html( $advtr_o->post_title ) . '</strong>',
		$advtr_loc
			? esc_html( $advtr_loc->post_title )
			: '<span class="ac-cella-tenue">' . esc_html__( 'nessun locale collegato', 'advertrieste' ) . '</span>',
		'<code>' . esc_html( (string) get_post_meta( $advtr_o->ID, 'advtr_codice', true ) ) . '</code>',
		$advtr_attiva
			? Tabella::pill( __( 'Attiva', 'advertrieste' ), 'ok' )
			: Tabella::pill( __( 'Non attiva', 'advertrieste' ), 'attesa' ),
		esc_html( number_format_i18n( Coupon::redemptions_count( $advtr_o->ID ) ) ),
		'<span class="ac-azioni-cella">' .
			Tabella::azione(
				array(
					'azione'    => $advtr_online ? 'sospendi' : 'pubblica',
					'etichetta' => $advtr_online ? __( 'Ritira', 'advertrieste' ) : __( 'Pubblica', 'advertrieste' ),
					'url'       => $advtr_url,
					'nonce'     => AdminConsole::NONCE,
					'classe'    => $advtr_online ? 'ac-btn ac-btn-fragile' : 'ac-btn ac-btn-verde',
					'conferma'  => $advtr_online ? __( 'Confermi?', 'advertrieste' ) : '',
					'campi'     => array(
						'advtr_id'      => $advtr_o->ID,
						'advtr_sezione' => 'offerte',
					),
				)
			) .
			'<a class="ac-btn ac-btn-neutro" href="' . esc_url( AdminConsole::url( 'offerte', array( 'id' => $advtr_o->ID ) ) ) . '">' .
			esc_html__( 'Apri', 'advertrieste' ) . '</a>' .
		'</span>',
	);
}

?>
<div class="ac-barra-azioni">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup del componente, già escapato.
	echo AdminConsole::bottone_nuovo( 'offerte', __( 'Aggiungi un\'offerta', 'advertrieste' ) );
	?>
</div>
<?php
$advtr_tabella = Tabella::rendi(
	array(
		'colonne' => array(
			__( 'Offerta', 'advertrieste' ),
			__( 'Locale', 'advertrieste' ),
			__( 'Codice', 'advertrieste' ),
			__( 'Stato', 'advertrieste' ),
			__( 'Riscatti', 'advertrieste' ),
			'',
		),
		'righe'   => $advtr_righe,
		'vuoto'   => __( 'Nessuna offerta.', 'advertrieste' ),
		'ricerca' => $cerca,
		'azione'  => AdminConsole::url(),
		'sezione' => 'offerte',
	)
);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tabella::rendi() escapa intestazioni e celle.
echo $advtr_tabella;
