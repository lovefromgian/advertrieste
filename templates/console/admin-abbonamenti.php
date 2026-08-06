<?php
/**
 * Console admin — abbonamenti: cosa scade e rinnovo in un gesto.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Console\Tabella;
use AdverTrieste\Cliente\Abbonamento;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_url = AdminConsole::url( 'abbonamenti' );

// Ordinati per urgenza: chi scade prima sta in cima. È l'ordine in cui si
// lavora davvero, non quello alfabetico.
$advtr_lista = array();
foreach ( AdminConsole::locali( $cerca ) as $advtr_p ) {
	$advtr_lista[] = array(
		'post'   => $advtr_p,
		'giorni' => Abbonamento::giorni_alla_scadenza( $advtr_p->ID ),
	);
}
usort(
	$advtr_lista,
	static function ( $a, $b ) {
		$ga = null === $a['giorni'] ? PHP_INT_MAX : $a['giorni'];
		$gb = null === $b['giorni'] ? PHP_INT_MAX : $b['giorni'];
		return $ga <=> $gb;
	}
);

$advtr_righe = array();
foreach ( $advtr_lista as $advtr_v ) {
	$advtr_p     = $advtr_v['post'];
	$advtr_g     = $advtr_v['giorni'];
	$advtr_stato = Abbonamento::stato( $advtr_p->ID );
	$advtr_aut   = get_userdata( $advtr_p->post_author );

	if ( null === $advtr_g ) {
		$advtr_resta = '<span class="ac-cella-tenue">' . esc_html__( 'nessuna scadenza', 'advertrieste' ) . '</span>';
	} elseif ( $advtr_g < 0 ) {
		/* translators: %d: giorni trascorsi */
		$advtr_resta = '<strong>' . esc_html( sprintf( __( 'scaduto da %d giorni', 'advertrieste' ), abs( $advtr_g ) ) ) . '</strong>';
	} else {
		/* translators: %d: giorni mancanti */
		$advtr_resta = esc_html( sprintf( _n( '%d giorno', '%d giorni', $advtr_g, 'advertrieste' ), $advtr_g ) );
	}

	$advtr_rinnovi = '';
	foreach ( array( 30, 365 ) as $advtr_gg ) {
		$advtr_rinnovi .= Tabella::azione(
			array(
				'azione'    => 'rinnova',
				/* translators: %d: giorni di rinnovo */
				'etichetta' => sprintf( __( '+%d gg', 'advertrieste' ), $advtr_gg ),
				'url'       => $advtr_url,
				'nonce'     => AdminConsole::NONCE,
				'classe'    => 365 === $advtr_gg ? 'ac-btn ac-btn-verde' : 'ac-btn ac-btn-neutro',
				'campi'     => array(
					'advtr_id'      => $advtr_p->ID,
					'advtr_giorni'  => $advtr_gg,
					'advtr_sezione' => 'abbonamenti',
				),
			)
		);
	}

	$advtr_righe[] = array(
		'<strong>' . esc_html( $advtr_p->post_title ) . '</strong>',
		esc_html( $advtr_aut ? $advtr_aut->display_name : '—' ),
		Tabella::pill( $advtr_stato['etichetta'], $advtr_stato['pill'] ),
		$advtr_resta,
		esc_html( '' !== Abbonamento::data_scadenza( $advtr_p->ID ) ? Abbonamento::data_scadenza( $advtr_p->ID ) : '—' ),
		'<span class="ac-azioni-cella">' . $advtr_rinnovi . '</span>',
	);
}

// La ricerca filtra la tabella, non le schede fra cui si sceglie qui: chi cerca
// "Rossetti" per controllarne la scadenza non deve trovarsi il menu ridotto a
// una voce quando poi vuole attivare un'altra scheda.
$advtr_attivabili = '' !== $cerca ? AdminConsole::locali( '' ) : wp_list_pluck( $advtr_lista, 'post' );
?>
<div class="ac-card" style="margin-bottom:20px">
	<h3 class="ac-card-titolo"><?php esc_html_e( 'Attiva un abbonamento', 'advertrieste' ); ?></h3>
	<p class="ac-card-sottotitolo">
		<?php esc_html_e( 'Fissa la finestra di validità da capo: serve alla prima attivazione o quando si riparte con un contratto nuovo. Per aggiungere tempo a un abbonamento che c\'è già, usa "Rinnova" nella riga corrispondente.', 'advertrieste' ); ?>
	</p>
	<form class="advtr-form" method="post" action="<?php echo esc_url( $advtr_url ); ?>"
		data-advtr-conferma="<?php esc_attr_e( 'Confermi? Sostituisce la validità attuale', 'advertrieste' ); ?>">
		<?php wp_nonce_field( AdminConsole::NONCE ); ?>
		<input type="hidden" name="advtr_azione" value="attiva_abbonamento" />

		<label for="ac-ab-locale"><?php esc_html_e( 'Scheda', 'advertrieste' ); ?></label>
		<select id="ac-ab-locale" name="advtr_locale" required>
			<option value=""><?php esc_html_e( '— scegli una scheda —', 'advertrieste' ); ?></option>
			<?php foreach ( $advtr_attivabili as $advtr_o ) : ?>
				<?php
				$advtr_scad = Abbonamento::data_scadenza( $advtr_o->ID );
				$advtr_nota = '' !== $advtr_scad
					/* translators: %s: data di scadenza */
					? sprintf( __( 'scade il %s', 'advertrieste' ), $advtr_scad )
					: __( 'senza abbonamento', 'advertrieste' );
				?>
				<option value="<?php echo esc_attr( $advtr_o->ID ); ?>">
					<?php echo esc_html( $advtr_o->post_title . ' — ' . $advtr_nota ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<div class="advtr-griglia-2">
			<div>
				<label for="ac-ab-dal"><?php esc_html_e( 'Decorrenza', 'advertrieste' ); ?></label>
				<input type="date" id="ac-ab-dal" name="advtr_decorrenza"
					value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required />
			</div>
			<div>
				<label for="ac-ab-durata"><?php esc_html_e( 'Durata', 'advertrieste' ); ?></label>
				<select id="ac-ab-durata" name="advtr_durata">
					<?php foreach ( AdminConsole::DURATE as $advtr_gg ) : ?>
						<option value="<?php echo esc_attr( $advtr_gg ); ?>"<?php echo 365 === $advtr_gg ? ' selected' : ''; ?>>
							<?php
							/* translators: %d: giorni di durata */
							echo esc_html( sprintf( _n( '%d giorno', '%d giorni', $advtr_gg, 'advertrieste' ), $advtr_gg ) );
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="advtr-form-azioni">
			<button type="submit" class="ac-btn ac-btn-verde"><?php esc_html_e( 'Attiva', 'advertrieste' ); ?></button>
		</div>
	</form>
</div>
<?php
$advtr_tabella = Tabella::rendi(
	array(
		'colonne' => array(
			__( 'Attività', 'advertrieste' ),
			__( 'Cliente', 'advertrieste' ),
			__( 'Stato', 'advertrieste' ),
			__( 'Tempo residuo', 'advertrieste' ),
			__( 'Scadenza', 'advertrieste' ),
			__( 'Rinnova', 'advertrieste' ),
		),
		'righe'   => $advtr_righe,
		'vuoto'   => __( 'Nessuna scheda da gestire.', 'advertrieste' ),
		'ricerca' => $cerca,
		'azione'  => AdminConsole::url(),
		'sezione' => 'abbonamenti',
	)
);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tabella::rendi() escapa intestazioni e celle.
echo $advtr_tabella;
?>
<p class="ac-card-sottotitolo" style="margin-top:14px">
	<?php esc_html_e( 'Il rinnovo estende la validità a partire dalla scadenza attuale (o da oggi, se già scaduta), azzera gli avvisi inviati e ripubblica la scheda se era stata sospesa automaticamente.', 'advertrieste' ); ?>
</p>
