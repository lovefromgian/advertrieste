<?php
/**
 * Template del form di validazione coupon (esercente).
 *
 * Variabili (da Offerte::shortcode_valida):
 *   $offerte  array  lista {id, titolo}
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="advtr-valida" data-advtr-valida="1">
	<p>
		<label for="advtr-valida-offerta"><?php esc_html_e( 'Offerta', 'advertrieste' ); ?></label><br />
		<select id="advtr-valida-offerta" data-advtr-valida-offerta="1">
			<?php foreach ( $offerte as $advtr_off ) : ?>
				<option value="<?php echo esc_attr( $advtr_off['id'] ); ?>">
					<?php echo esc_html( $advtr_off['titolo'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="advtr-valida-codice"><?php esc_html_e( 'Codice presentato dal cliente', 'advertrieste' ); ?></label><br />
		<input type="text" id="advtr-valida-codice" data-advtr-valida-codice="1" />
	</p>
	<p>
		<button type="button" class="button" data-advtr-valida-btn="1"><?php esc_html_e( 'Valida coupon', 'advertrieste' ); ?></button>
	</p>
	<p class="advtr-valida-esito" data-advtr-valida-esito="1" role="status"></p>
</div>
