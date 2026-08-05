<?php
/**
 * Console cliente — dati dell'account.
 *
 * La password non si cambia da qui: si usa il flusso di reimpostazione via
 * email, che verifica l'identità. Gestire password in un modulo significa
 * trattarle in chiaro, e non è necessario.
 *
 * Variabili disponibili: $utente (WP_User).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Frontend\ClientArea;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ac-card" style="margin-bottom:16px">
	<h3 class="ac-card-titolo"><?php esc_html_e( 'Il tuo account', 'advertrieste' ); ?></h3>
	<p class="ac-card-sottotitolo"><?php esc_html_e( 'Questi dati servono per l\'accesso e per gli avvisi di scadenza.', 'advertrieste' ); ?></p>

	<form class="advtr-form" method="post" action="<?php echo esc_url( ClientArea::url( 'impostazioni' ) ); ?>">
		<?php wp_nonce_field( ClientArea::NONCE ); ?>
		<input type="hidden" name="advtr_azione" value="account_salva" />

		<label for="advtr-nome"><?php esc_html_e( 'Nome visualizzato', 'advertrieste' ); ?></label>
		<input type="text" id="advtr-nome" name="advtr_nome" required
			value="<?php echo esc_attr( $utente->display_name ); ?>" />

		<label for="advtr-mail"><?php esc_html_e( 'Email', 'advertrieste' ); ?></label>
		<input type="email" id="advtr-mail" name="advtr_email" required
			value="<?php echo esc_attr( $utente->user_email ); ?>" />
		<p class="advtr-aiuto"><?php esc_html_e( 'È l\'indirizzo a cui arrivano gli avvisi di scadenza.', 'advertrieste' ); ?></p>

		<div class="advtr-form-azioni">
			<button type="submit" class="ac-btn ac-btn-verde"><?php esc_html_e( 'Salva', 'advertrieste' ); ?></button>
		</div>
	</form>
</div>

<div class="ac-card">
	<h3 class="ac-card-titolo"><?php esc_html_e( 'Password', 'advertrieste' ); ?></h3>
	<p class="ac-card-sottotitolo">
		<?php esc_html_e( 'Per cambiarla ti inviamo un link via email: è il modo sicuro di farlo, senza che la password passi da questo modulo.', 'advertrieste' ); ?>
	</p>
	<form method="post" action="<?php echo esc_url( ClientArea::url() ); ?>">
		<?php wp_nonce_field( ClientArea::NONCE . '_password' ); ?>
		<input type="hidden" name="advtr_azione" value="password" />
		<input type="hidden" name="advtr_utente" value="<?php echo esc_attr( $utente->user_email ); ?>" />
		<button type="submit" class="ac-btn ac-btn-neutro"><?php esc_html_e( 'Inviami il link', 'advertrieste' ); ?></button>
	</form>
</div>
