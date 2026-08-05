<?php
/**
 * Console admin — modifica di un'offerta.
 *
 * Variabili disponibili: $id (int).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Cpt\Locale;
use AdverTrieste\Coupon\Coupon;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_post = get_post( $id );
if ( ! $advtr_post || 'offerta' !== $advtr_post->post_type ) {
	echo '<div class="ac-vuoto"><p>' . esc_html__( 'Offerta non trovata.', 'advertrieste' ) . '</p></div>';
	return;
}

/**
 * Data pronta per un campo `date`.
 *
 * @param string $v Valore salvato.
 * @return string
 */
function advtr_ac_data( $v ) {
	$ts = $v ? strtotime( $v ) : 0;
	return $ts ? gmdate( 'Y-m-d', $ts ) : '';
}

$advtr_locale_id = (int) get_post_meta( $id, 'advtr_locale_id', true );
$advtr_tipo      = (string) get_post_meta( $id, 'advtr_tipo_coupon', true );
?>
<p style="margin:0 0 16px">
	<a class="ac-btn ac-btn-neutro" href="<?php echo esc_url( AdminConsole::url( 'offerte' ) ); ?>">
		← <?php esc_html_e( 'Torna all\'elenco', 'advertrieste' ); ?>
	</a>
</p>

<form class="advtr-form" method="post" action="<?php echo esc_url( AdminConsole::url( 'offerte', array( 'id' => $id ) ) ); ?>">
	<?php wp_nonce_field( AdminConsole::NONCE ); ?>
	<input type="hidden" name="advtr_azione" value="offerta_salva" />
	<input type="hidden" name="advtr_id" value="<?php echo esc_attr( $id ); ?>" />

	<div class="ac-griglia ac-griglia-3-2" style="align-items:start">
		<div class="ac-card">
			<h3 class="ac-card-titolo"><?php esc_html_e( 'Promozione', 'advertrieste' ); ?></h3>

			<label for="ac-off-titolo"><?php esc_html_e( 'Titolo', 'advertrieste' ); ?></label>
			<input type="text" id="ac-off-titolo" name="advtr_titolo" required value="<?php echo esc_attr( $advtr_post->post_title ); ?>" />

			<label for="ac-off-desc"><?php esc_html_e( 'Descrizione', 'advertrieste' ); ?></label>
			<textarea id="ac-off-desc" name="advtr_descrizione" rows="5"><?php echo esc_textarea( $advtr_post->post_content ); ?></textarea>

			<label for="ac-off-locale"><?php esc_html_e( 'Locale collegato', 'advertrieste' ); ?></label>
			<select id="ac-off-locale" name="advtr_locale_id">
				<option value="0"><?php esc_html_e( '— nessuno —', 'advertrieste' ); ?></option>
				<?php
				foreach ( get_posts(
					array(
						'post_type'      => Locale::POST_TYPE,
						'post_status'    => array( 'publish', 'pending', 'draft' ),
						'posts_per_page' => 200,
						'orderby'        => 'title',
						'order'          => 'ASC',
					)
				) as $advtr_l ) :
					?>
					<option value="<?php echo esc_attr( $advtr_l->ID ); ?>" <?php selected( $advtr_locale_id, $advtr_l->ID ); ?>>
						<?php echo esc_html( $advtr_l->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="advtr-aiuto"><?php esc_html_e( 'Determina su quale scheda compare l\'offerta e a chi vanno i riscatti nelle statistiche.', 'advertrieste' ); ?></p>
		</div>

		<div class="ac-card">
			<h3 class="ac-card-titolo"><?php esc_html_e( 'Validità e coupon', 'advertrieste' ); ?></h3>

			<label for="ac-off-stato"><?php esc_html_e( 'Pubblicazione', 'advertrieste' ); ?></label>
			<select id="ac-off-stato" name="advtr_stato_post">
				<option value="publish" <?php selected( 'publish', $advtr_post->post_status ); ?>><?php esc_html_e( 'Online', 'advertrieste' ); ?></option>
				<option value="draft" <?php selected( 'publish' !== $advtr_post->post_status ); ?>><?php esc_html_e( 'Ritirata', 'advertrieste' ); ?></option>
			</select>

			<label for="ac-off-inizio"><?php esc_html_e( 'Valida dal', 'advertrieste' ); ?></label>
			<input type="date" id="ac-off-inizio" name="advtr_data_inizio"
				value="<?php echo esc_attr( advtr_ac_data( get_post_meta( $id, 'advtr_data_inizio', true ) ) ); ?>" />

			<label for="ac-off-fine"><?php esc_html_e( 'Scade il', 'advertrieste' ); ?></label>
			<input type="date" id="ac-off-fine" name="advtr_data_scadenza"
				value="<?php echo esc_attr( advtr_ac_data( get_post_meta( $id, 'advtr_data_scadenza', true ) ) ); ?>" />

			<label for="ac-off-tipo"><?php esc_html_e( 'Tipo di coupon', 'advertrieste' ); ?></label>
			<select id="ac-off-tipo" name="advtr_tipo_coupon">
				<option value="codice" <?php selected( 'qr' !== $advtr_tipo ); ?>><?php esc_html_e( 'Codice da presentare', 'advertrieste' ); ?></option>
				<option value="qr" <?php selected( 'qr', $advtr_tipo ); ?>><?php esc_html_e( 'QR code', 'advertrieste' ); ?></option>
			</select>

			<label for="ac-off-codice"><?php esc_html_e( 'Codice', 'advertrieste' ); ?></label>
			<input type="text" id="ac-off-codice" name="advtr_codice"
				value="<?php echo esc_attr( get_post_meta( $id, 'advtr_codice', true ) ); ?>" />

			<p class="advtr-aiuto">
				<?php
				printf(
					/* translators: %d: numero di riscatti */
					esc_html__( 'Riscatti registrati finora: %d.', 'advertrieste' ),
					(int) Coupon::redemptions_count( $id )
				);
				?>
			</p>
		</div>
	</div>

	<div class="advtr-form-azioni">
		<button type="submit" class="ac-btn ac-btn-verde"><?php esc_html_e( 'Salva', 'advertrieste' ); ?></button>
	</div>
</form>
