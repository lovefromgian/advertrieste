<?php
/**
 * Console admin — contenuti in attesa di approvazione.
 *
 * Le due code che richiedono una decisione: schede non ancora pubblicate ed
 * eventi inviati in revisione dagli organizzatori.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Console\Tabella;
use AdverTrieste\Cpt\Locale;
use AdverTrieste\Cpt\Evento;
use AdverTrieste\Evento\Workflow;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_url = AdminConsole::url( 'approvazioni' );

/* -- Schede in attesa ------------------------------------------------ */

$advtr_schede = get_posts(
	array(
		'post_type'      => Locale::POST_TYPE,
		'post_status'    => array( 'pending', 'draft' ),
		'posts_per_page' => 100,
		'orderby'        => 'date',
		'order'          => 'ASC',
	)
);

$advtr_righe = array();
foreach ( $advtr_schede as $advtr_p ) {
	$advtr_autore = get_userdata( $advtr_p->post_author );
	$advtr_sosp   = get_post_meta( $advtr_p->ID, 'advtr_sospesa', true );

	$advtr_righe[] = array(
		'<strong>' . esc_html( $advtr_p->post_title ) . '</strong>',
		esc_html( $advtr_autore ? $advtr_autore->display_name : '—' ),
		$advtr_sosp
			? Tabella::pill( __( 'Sospesa per scadenza', 'advertrieste' ), 'attesa' )
			: Tabella::pill( __( 'Mai pubblicata', 'advertrieste' ), '' ),
		esc_html( get_the_date( 'j M Y', $advtr_p ) ),
		'<span class="ac-azioni-cella">' .
			Tabella::azione(
				array(
					'azione'    => 'pubblica',
					'etichetta' => __( 'Pubblica', 'advertrieste' ),
					'url'       => $advtr_url,
					'nonce'     => AdminConsole::NONCE,
					'classe'    => 'ac-btn ac-btn-verde',
					'campi'     => array(
						'advtr_id'      => $advtr_p->ID,
						'advtr_sezione' => 'approvazioni',
					),
				)
			) .
			'<a class="ac-btn ac-btn-neutro" href="' . esc_url( get_edit_post_link( $advtr_p->ID ) ) . '">' .
			esc_html__( 'Apri', 'advertrieste' ) . '</a>' .
		'</span>',
	);
}
?>
<div class="ac-card" style="margin-bottom:20px">
	<h3 class="ac-card-titolo"><?php esc_html_e( 'Schede da pubblicare', 'advertrieste' ); ?></h3>
	<p class="ac-card-sottotitolo"><?php esc_html_e( 'Attività non ancora visibili sulla mappa.', 'advertrieste' ); ?></p>
	<?php
		$advtr_tabella = Tabella::rendi(
			array(
				'colonne' => array( __( 'Attività', 'advertrieste' ), __( 'Cliente', 'advertrieste' ), __( 'Stato', 'advertrieste' ), __( 'Creata', 'advertrieste' ), '' ),
				'righe'   => $advtr_righe,
				'vuoto'   => __( 'Nessuna scheda in attesa: sono tutte online.', 'advertrieste' ),
			)
		);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tabella::rendi() escapa intestazioni e celle.
		echo $advtr_tabella;
		?>
</div>

<?php
/* -- Eventi in revisione --------------------------------------------- */

$advtr_ev_righe = array();
foreach ( get_posts(
	array(
		'post_type'      => Evento::POST_TYPE,
		'post_status'    => array( 'publish', 'draft', 'pending' ),
		'posts_per_page' => 100,
	)
) as $advtr_ev ) {
	if ( Workflow::STATO_IN_REVISIONE !== Workflow::stato( $advtr_ev->ID ) ) {
		continue;
	}
	$advtr_org = get_userdata( $advtr_ev->post_author );
	$advtr_pub = Workflow::is_published( $advtr_ev->ID );

	$advtr_ev_righe[] = array(
		'<strong>' . esc_html( $advtr_ev->post_title ) . '</strong>',
		esc_html( $advtr_org ? $advtr_org->display_name : '—' ),
		esc_html( (string) get_post_meta( $advtr_ev->ID, 'advtr_tipo_evento', true ) ),
		$advtr_pub
			? Tabella::pill( __( 'Modifica di un evento online', 'advertrieste' ), 'attesa' )
			: Tabella::pill( __( 'Prima pubblicazione', 'advertrieste' ), '' ),
		'<span class="ac-azioni-cella">' .
			Tabella::azione(
				array(
					'azione'    => 'approva_evento',
					'etichetta' => __( 'Approva', 'advertrieste' ),
					'url'       => $advtr_url,
					'nonce'     => AdminConsole::NONCE,
					'classe'    => 'ac-btn ac-btn-verde',
					'campi'     => array(
						'advtr_id'      => $advtr_ev->ID,
						'advtr_sezione' => 'approvazioni',
					),
				)
			) .
			'<a class="ac-btn ac-btn-neutro" href="' . esc_url( get_edit_post_link( $advtr_ev->ID ) ) . '">' .
			esc_html__( 'Apri', 'advertrieste' ) . '</a>' .
		'</span>',
	);
}
?>
<div class="ac-card">
	<h3 class="ac-card-titolo"><?php esc_html_e( 'Eventi in revisione', 'advertrieste' ); ?></h3>
	<p class="ac-card-sottotitolo">
		<?php esc_html_e( 'Finché non approvi, il pubblico continua a vedere l\'ultima versione approvata.', 'advertrieste' ); ?>
	</p>
	<?php
		$advtr_tabella1 = Tabella::rendi(
			array(
				'colonne' => array( __( 'Evento', 'advertrieste' ), __( 'Organizzatore', 'advertrieste' ), __( 'Tipo', 'advertrieste' ), __( 'Stato', 'advertrieste' ), '' ),
				'righe'   => $advtr_ev_righe,
				'vuoto'   => __( 'Nessun evento in attesa di approvazione.', 'advertrieste' ),
			)
		);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tabella::rendi() escapa intestazioni e celle.
		echo $advtr_tabella1;
		?>
</div>
