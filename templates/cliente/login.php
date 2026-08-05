<?php
/**
 * Accesso all'area clienti (e recupero password), senza schermate di WordPress.
 *
 * Variabili disponibili: $avviso (array|null), $vista (string).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Frontend\ClientArea;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_password = isset( $vista ) && 'password' === $vista;
?>
<div class="advtr-cliente advtr-cliente-accesso">
	<div class="advtr-accesso-box">

		<?php if ( ! empty( $avviso ) ) : ?>
			<p class="advtr-cliente-avviso <?php echo esc_attr( $avviso['tipo'] ); ?>">
				<?php echo esc_html( $avviso['testo'] ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $advtr_password ) : ?>

			<h2><?php esc_html_e( 'Password dimenticata', 'advertrieste' ); ?></h2>
			<p class="advtr-accesso-nota">
				<?php esc_html_e( 'Inserisci l\'email del tuo account: ti invieremo un link per sceglierne una nuova.', 'advertrieste' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( ClientArea::url() ); ?>">
				<?php wp_nonce_field( ClientArea::NONCE . '_password' ); ?>
				<input type="hidden" name="advtr_azione" value="password" />

				<label for="advtr-utente"><?php esc_html_e( 'Email', 'advertrieste' ); ?></label>
				<input type="email" id="advtr-utente" name="advtr_utente" autocomplete="email" required />

				<button type="submit" class="advtr-btn advtr-btn-primario">
					<?php esc_html_e( 'Invia il link', 'advertrieste' ); ?>
				</button>
			</form>

			<p class="advtr-accesso-alt">
				<a href="<?php echo esc_url( ClientArea::url() ); ?>"><?php esc_html_e( 'Torna all\'accesso', 'advertrieste' ); ?></a>
			</p>

		<?php else : ?>

			<h2><?php esc_html_e( 'Area clienti', 'advertrieste' ); ?></h2>
			<p class="advtr-accesso-nota">
				<?php esc_html_e( 'Accedi per gestire la tua scheda, le foto e le offerte.', 'advertrieste' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( ClientArea::url() ); ?>">
				<?php wp_nonce_field( ClientArea::NONCE . '_login' ); ?>
				<input type="hidden" name="advtr_azione" value="login" />

				<label for="advtr-utente"><?php esc_html_e( 'Email o nome utente', 'advertrieste' ); ?></label>
				<input type="text" id="advtr-utente" name="advtr_utente" autocomplete="username" required />

				<label for="advtr-password"><?php esc_html_e( 'Password', 'advertrieste' ); ?></label>
				<input type="password" id="advtr-password" name="advtr_password" autocomplete="current-password" required />

				<label class="advtr-accesso-ricorda">
					<input type="checkbox" name="advtr_ricordami" value="1" />
					<?php esc_html_e( 'Resta collegato', 'advertrieste' ); ?>
				</label>

				<button type="submit" class="advtr-btn advtr-btn-primario">
					<?php esc_html_e( 'Accedi', 'advertrieste' ); ?>
				</button>
			</form>

			<p class="advtr-accesso-alt">
				<a href="<?php echo esc_url( ClientArea::url( '', array( 'vista' => 'password' ) ) ); ?>">
					<?php esc_html_e( 'Password dimenticata?', 'advertrieste' ); ?>
				</a>
			</p>

		<?php endif; ?>
	</div>
</div>
