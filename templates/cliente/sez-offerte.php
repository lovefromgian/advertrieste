<?php
/**
 * Sezione "Offerte": elenco delle proprie offerte e form di creazione/modifica.
 *
 * Variabili disponibili: $locale (WP_Post).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Frontend\ClientArea;
use AdverTrieste\Cliente\Offerte;
use AdverTrieste\Coupon\Coupon;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_mie = Offerte::mie();

// Offerta in modifica, se richiesta e di proprietà.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola selezione di vista.
$advtr_edit_id = isset( $_GET['offerta'] ) ? absint( wp_unslash( $_GET['offerta'] ) ) : 0;
$advtr_edit    = ( $advtr_edit_id && current_user_can( 'edit_post', $advtr_edit_id ) ) ? get_post( $advtr_edit_id ) : null;

/**
 * Converte una data salvata nel formato dell'input date.
 *
 * @param string $valore Data in formato MySQL.
 * @return string
 */
function advtr_data_input( $valore ) {
	$ts = $valore ? strtotime( $valore ) : 0;
	return $ts ? gmdate( 'Y-m-d', $ts ) : '';
}
?>
<section class="advtr-offerte-cliente">

	<h3><?php esc_html_e( 'Le mie offerte', 'advertrieste' ); ?></h3>

	<?php if ( ! $advtr_mie ) : ?>
		<p class="advtr-nota"><?php esc_html_e( 'Non hai ancora creato offerte.', 'advertrieste' ); ?></p>
	<?php else : ?>
		<div class="advtr-tabella-scorri">
		<table class="advtr-tabella">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Offerta', 'advertrieste' ); ?></th>
					<th><?php esc_html_e( 'Validità', 'advertrieste' ); ?></th>
					<th><?php esc_html_e( 'Codice', 'advertrieste' ); ?></th>
					<th><?php esc_html_e( 'Stato', 'advertrieste' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $advtr_mie as $advtr_off ) : ?>
					<?php $advtr_attiva = Coupon::is_offer_active( $advtr_off->ID ); ?>
					<tr>
						<td><strong><?php echo esc_html( $advtr_off->post_title ); ?></strong></td>
						<td>
							<?php
							echo esc_html(
								trim(
									advtr_data_input( get_post_meta( $advtr_off->ID, 'advtr_data_inizio', true ) ) . ' → ' .
									advtr_data_input( get_post_meta( $advtr_off->ID, 'advtr_data_scadenza', true ) ),
									' →'
								)
							);
							?>
						</td>
						<td><code><?php echo esc_html( get_post_meta( $advtr_off->ID, 'advtr_codice', true ) ); ?></code></td>
						<td>
							<span class="advtr-pill <?php echo $advtr_attiva ? 'ok' : 'attesa'; ?>">
								<?php echo $advtr_attiva ? esc_html__( 'Attiva', 'advertrieste' ) : esc_html__( 'Non attiva', 'advertrieste' ); ?>
							</span>
						</td>
						<td class="advtr-tabella-azioni">
							<a href="<?php echo esc_url( ClientArea::url( 'offerte', array( 'offerta' => $advtr_off->ID ) ) ); ?>">
								<?php esc_html_e( 'Modifica', 'advertrieste' ); ?>
							</a>
							<form method="post" action="<?php echo esc_url( ClientArea::url( 'offerte' ) ); ?>"
								data-advtr-conferma="<?php esc_attr_e( 'Eliminare questa offerta?', 'advertrieste' ); ?>">
								<?php wp_nonce_field( ClientArea::NONCE ); ?>
								<input type="hidden" name="advtr_azione" value="offerta_elimina" />
								<input type="hidden" name="advtr_offerta_id" value="<?php echo esc_attr( $advtr_off->ID ); ?>" />
								<button type="submit" class="advtr-btn advtr-btn-rimuovi"><?php esc_html_e( 'Elimina', 'advertrieste' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
	<?php endif; ?>

	<h3><?php echo $advtr_edit ? esc_html__( 'Modifica offerta', 'advertrieste' ) : esc_html__( 'Nuova offerta', 'advertrieste' ); ?></h3>

	<form class="advtr-form" method="post" action="<?php echo esc_url( ClientArea::url( 'offerte' ) ); ?>">
		<?php wp_nonce_field( ClientArea::NONCE ); ?>
		<input type="hidden" name="advtr_azione" value="offerta_salva" />
		<input type="hidden" name="advtr_locale_id" value="<?php echo esc_attr( $locale->ID ); ?>" />
		<input type="hidden" name="advtr_offerta_id" value="<?php echo esc_attr( $advtr_edit ? $advtr_edit->ID : 0 ); ?>" />

		<label for="advtr-off-titolo"><?php esc_html_e( 'Titolo dell\'offerta', 'advertrieste' ); ?></label>
		<input type="text" id="advtr-off-titolo" name="advtr_titolo" required
			value="<?php echo esc_attr( $advtr_edit ? $advtr_edit->post_title : '' ); ?>" />

		<label for="advtr-off-testo"><?php esc_html_e( 'Descrizione', 'advertrieste' ); ?></label>
		<textarea id="advtr-off-testo" name="advtr_descrizione" rows="4"><?php echo esc_textarea( $advtr_edit ? $advtr_edit->post_content : '' ); ?></textarea>

		<div class="advtr-griglia-2">
			<div>
				<label for="advtr-off-inizio"><?php esc_html_e( 'Valida dal', 'advertrieste' ); ?></label>
				<input type="date" id="advtr-off-inizio" name="advtr_data_inizio"
					value="<?php echo esc_attr( $advtr_edit ? advtr_data_input( get_post_meta( $advtr_edit->ID, 'advtr_data_inizio', true ) ) : '' ); ?>" />
			</div>
			<div>
				<label for="advtr-off-fine"><?php esc_html_e( 'Scade il', 'advertrieste' ); ?></label>
				<input type="date" id="advtr-off-fine" name="advtr_data_scadenza"
					value="<?php echo esc_attr( $advtr_edit ? advtr_data_input( get_post_meta( $advtr_edit->ID, 'advtr_data_scadenza', true ) ) : '' ); ?>" />
			</div>
		</div>

		<div class="advtr-griglia-2">
			<div>
				<label for="advtr-off-tipo"><?php esc_html_e( 'Tipo di coupon', 'advertrieste' ); ?></label>
				<select id="advtr-off-tipo" name="advtr_tipo_coupon">
					<?php $advtr_tipo = $advtr_edit ? get_post_meta( $advtr_edit->ID, 'advtr_tipo_coupon', true ) : 'codice'; ?>
					<option value="codice" <?php selected( 'qr' !== $advtr_tipo ); ?>><?php esc_html_e( 'Codice da presentare', 'advertrieste' ); ?></option>
					<option value="qr" <?php selected( 'qr', $advtr_tipo ); ?>><?php esc_html_e( 'QR code', 'advertrieste' ); ?></option>
				</select>
			</div>
			<div>
				<label for="advtr-off-codice"><?php esc_html_e( 'Codice', 'advertrieste' ); ?></label>
				<input type="text" id="advtr-off-codice" name="advtr_codice"
					value="<?php echo esc_attr( $advtr_edit ? get_post_meta( $advtr_edit->ID, 'advtr_codice', true ) : '' ); ?>" />
			</div>
		</div>
		<p class="advtr-aiuto"><?php esc_html_e( 'È il codice che il cliente ti mostra e che validi nella sezione "Valida coupon".', 'advertrieste' ); ?></p>

		<div class="advtr-form-azioni">
			<button type="submit" class="advtr-btn advtr-btn-primario">
				<?php echo $advtr_edit ? esc_html__( 'Salva offerta', 'advertrieste' ) : esc_html__( 'Crea offerta', 'advertrieste' ); ?>
			</button>
			<?php if ( $advtr_edit ) : ?>
				<a class="advtr-btn advtr-btn-neutro" href="<?php echo esc_url( ClientArea::url( 'offerte' ) ); ?>">
					<?php esc_html_e( 'Annulla', 'advertrieste' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</form>
</section>
