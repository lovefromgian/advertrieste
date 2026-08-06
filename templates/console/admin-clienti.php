<?php
/**
 * Console admin — clienti e organizzatori, con ciò che possiedono.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Console\Tabella;
use AdverTrieste\Access\Roles;
use AdverTrieste\Cpt\Locale;
use AdverTrieste\Cpt\Offerta;
use AdverTrieste\Cliente\Abbonamento;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_args = array(
	'role__in' => array( Roles::CLIENTE, Roles::ORGANIZZATORE ),
	'number'   => 200,
	'orderby'  => 'display_name',
);
if ( '' !== $cerca ) {
	$advtr_args['search']         = '*' . $cerca . '*';
	$advtr_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
}

$advtr_righe = array();
foreach ( get_users( $advtr_args ) as $advtr_u ) {
	$advtr_schede  = get_posts(
		array(
			'post_type'      => Locale::POST_TYPE,
			'post_status'    => array( 'publish', 'pending', 'draft' ),
			'author'         => $advtr_u->ID,
			'posts_per_page' => -1,
		)
	);
	$advtr_offerte = count(
		get_posts(
			array(
				'post_type'      => Offerta::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'author'         => $advtr_u->ID,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		)
	);

	$advtr_nomi     = array();
	$advtr_peggiore = null;
	foreach ( $advtr_schede as $advtr_s ) {
		$advtr_nomi[] = $advtr_s->post_title;
		$advtr_g      = Abbonamento::giorni_alla_scadenza( $advtr_s->ID );
		if ( null !== $advtr_g && ( null === $advtr_peggiore || $advtr_g < $advtr_peggiore ) ) {
			$advtr_peggiore = $advtr_g;
		}
	}

	$advtr_ruolo = in_array( Roles::ORGANIZZATORE, (array) $advtr_u->roles, true )
		? __( 'Organizzatore', 'advertrieste' )
		: __( 'Cliente', 'advertrieste' );

	if ( null === $advtr_peggiore ) {
		$advtr_scad = '<span class="ac-cella-tenue">—</span>';
	} elseif ( $advtr_peggiore < 0 ) {
		$advtr_scad = Tabella::pill( __( 'Scaduto', 'advertrieste' ), 'attesa' );
	} elseif ( $advtr_peggiore <= 30 ) {
		/* translators: %d: giorni mancanti */
		$advtr_scad = Tabella::pill( sprintf( __( '%d giorni', 'advertrieste' ), $advtr_peggiore ), 'attesa' );
	} else {
		/* translators: %d: giorni mancanti */
		$advtr_scad = '<span class="ac-cella-tenue">' . esc_html( sprintf( __( '%d giorni', 'advertrieste' ), $advtr_peggiore ) ) . '</span>';
	}

	$advtr_righe[] = array(
		'<strong>' . esc_html( $advtr_u->display_name ) . '</strong><br />' .
			'<span class="ac-cella-tenue">' . esc_html( $advtr_u->user_email ) . '</span>',
		esc_html( $advtr_ruolo ),
		$advtr_nomi
			? esc_html( implode( ', ', $advtr_nomi ) )
			: '<span class="ac-cella-tenue">' . esc_html__( 'nessuna scheda collegata', 'advertrieste' ) . '</span>',
		esc_html( number_format_i18n( $advtr_offerte ) ),
		$advtr_scad,
		'<span class="ac-azioni-cella">' .
			Tabella::azione(
				array(
					'azione'    => 'elimina_cliente',
					'etichetta' => __( 'Elimina account', 'advertrieste' ),
					'url'       => AdminConsole::url( 'clienti' ),
					'nonce'     => AdminConsole::NONCE,
					'classe'    => 'ac-btn ac-btn-fragile',
					'conferma'  => __( 'Sicuro? Le sue schede passano a te', 'advertrieste' ),
					'campi'     => array(
						'advtr_id'      => $advtr_u->ID,
						'advtr_sezione' => 'clienti',
					),
				)
			) .
			'<a class="ac-btn ac-btn-neutro" href="' . esc_url( AdminConsole::url( 'clienti', array( 'id' => $advtr_u->ID ) ) ) . '">' .
			esc_html__( 'Apri', 'advertrieste' ) . '</a>' .
		'</span>',
	);
}

?>
<div class="ac-card" style="margin-bottom:16px">
	<h3 class="ac-card-titolo"><?php esc_html_e( 'Nuovo account', 'advertrieste' ); ?></h3>
	<p class="ac-card-sottotitolo">
		<?php esc_html_e( 'Se lasci vuota la password riceverà un\'email con il link per impostarsela da sé — è la via consigliata. Scrivendola qui, invece, l\'account è pronto subito e le credenziali gliele consegni tu.', 'advertrieste' ); ?>
	</p>
	<form class="advtr-form ac-nuovo-cliente" method="post" action="<?php echo esc_url( AdminConsole::url( 'clienti' ) ); ?>">
		<?php wp_nonce_field( AdminConsole::NONCE ); ?>
		<input type="hidden" name="advtr_azione" value="crea_cliente" />
		<div class="advtr-griglia-2">
			<div>
				<label for="ac-nc-nome"><?php esc_html_e( 'Nome dell\'attività o della persona', 'advertrieste' ); ?></label>
				<input type="text" id="ac-nc-nome" name="advtr_nome" required />
			</div>
			<div>
				<label for="ac-nc-mail"><?php esc_html_e( 'Email', 'advertrieste' ); ?></label>
				<input type="email" id="ac-nc-mail" name="advtr_email" required />
			</div>
		</div>
		<label for="ac-nc-pass"><?php esc_html_e( 'Password (facoltativa)', 'advertrieste' ); ?></label>
		<input type="text" id="ac-nc-pass" name="advtr_password" autocomplete="new-password" spellcheck="false"
			minlength="<?php echo esc_attr( \AdverTrieste\Admin\Salva::PASSWORD_MIN ); ?>"
			data-advtr-genera="<?php esc_attr_e( 'Genera', 'advertrieste' ); ?>" />
		<p class="advtr-aiuto">
			<?php
			printf(
				/* translators: %d: numero minimo di caratteri */
				esc_html__( 'Almeno %d caratteri, in chiaro perché tu possa leggerla. Lasciala vuota per far arrivare al cliente il link via email.', 'advertrieste' ),
				(int) \AdverTrieste\Admin\Salva::PASSWORD_MIN
			);
			?>
		</p>

		<label for="ac-nc-ruolo"><?php esc_html_e( 'Ruolo', 'advertrieste' ); ?></label>
		<select id="ac-nc-ruolo" name="advtr_ruolo">
			<option value="<?php echo esc_attr( Roles::CLIENTE ); ?>"><?php esc_html_e( 'Cliente (locale)', 'advertrieste' ); ?></option>
			<option value="<?php echo esc_attr( Roles::ORGANIZZATORE ); ?>"><?php esc_html_e( 'Organizzatore evento', 'advertrieste' ); ?></option>
		</select>
		<div class="advtr-form-azioni">
			<button type="submit" class="ac-btn ac-btn-verde"><?php esc_html_e( 'Crea account', 'advertrieste' ); ?></button>
		</div>
	</form>
</div>
<?php
$advtr_tabella = Tabella::rendi(
	array(
		'colonne' => array(
			__( 'Account', 'advertrieste' ),
			__( 'Ruolo', 'advertrieste' ),
			__( 'Schede', 'advertrieste' ),
			__( 'Offerte', 'advertrieste' ),
			__( 'Prima scadenza', 'advertrieste' ),
			'',
		),
		'righe'   => $advtr_righe,
		'vuoto'   => __( 'Nessun cliente registrato.', 'advertrieste' ),
		'ricerca' => $cerca,
		'azione'  => AdminConsole::url(),
		'sezione' => 'clienti',
	)
);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tabella::rendi() escapa intestazioni e celle.
echo $advtr_tabella;
