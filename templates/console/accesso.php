<?php
/**
 * Schermata di accesso alla console, con la stessa veste del resto.
 *
 * Fondo scuro come la sidebar e come la home della proposta di progetto, con la
 * scheda di accesso al centro. Nessun riferimento a WordPress.
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

$advtr_recupero = isset( $vista ) && 'password' === $vista;
?>
<div class="ac-accesso">
	<div class="ac-accesso-scheda">

		<div class="ac-accesso-marchio">
			<span class="ac-marchio-pin" aria-hidden="true"></span>
			<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
		</div>

		<?php if ( ! empty( $avviso ) ) : ?>
			<p class="ac-accesso-avviso <?php echo esc_attr( $avviso['tipo'] ); ?>">
				<?php echo esc_html( $avviso['testo'] ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $advtr_recupero ) : ?>

			<h1 class="ac-accesso-titolo"><?php esc_html_e( 'Password dimenticata', 'advertrieste' ); ?></h1>
			<p class="ac-accesso-nota">
				<?php esc_html_e( 'Inserisci l\'email del tuo account: ti inviamo un link per sceglierne una nuova.', 'advertrieste' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( ClientArea::url() ); ?>">
				<?php wp_nonce_field( ClientArea::NONCE . '_password' ); ?>
				<input type="hidden" name="advtr_azione" value="password" />

				<label for="ac-utente"><?php esc_html_e( 'Email', 'advertrieste' ); ?></label>
				<input type="email" id="ac-utente" name="advtr_utente" autocomplete="email" required />

				<button type="submit" class="ac-btn ac-btn-primario ac-accesso-invia">
					<?php esc_html_e( 'Invia il link', 'advertrieste' ); ?>
				</button>
			</form>

			<p class="ac-accesso-alt">
				<a href="<?php echo esc_url( ClientArea::url() ); ?>"><?php esc_html_e( 'Torna all\'accesso', 'advertrieste' ); ?></a>
			</p>

		<?php else : ?>

			<h1 class="ac-accesso-titolo"><?php esc_html_e( 'Area clienti', 'advertrieste' ); ?></h1>
			<p class="ac-accesso-nota">
				<?php esc_html_e( 'Accedi per gestire la tua scheda, le foto e le offerte.', 'advertrieste' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( ClientArea::url() ); ?>">
				<?php wp_nonce_field( ClientArea::NONCE . '_login' ); ?>
				<input type="hidden" name="advtr_azione" value="login" />

				<label for="ac-utente"><?php esc_html_e( 'Email o nome utente', 'advertrieste' ); ?></label>
				<input type="text" id="ac-utente" name="advtr_utente" autocomplete="username" required />

				<label for="ac-password"><?php esc_html_e( 'Password', 'advertrieste' ); ?></label>
				<input type="password" id="ac-password" name="advtr_password" autocomplete="current-password" required />

				<label class="ac-accesso-ricorda">
					<input type="checkbox" name="advtr_ricordami" value="1" />
					<?php esc_html_e( 'Resta collegato', 'advertrieste' ); ?>
				</label>

				<button type="submit" class="ac-btn ac-btn-primario ac-accesso-invia">
					<?php esc_html_e( 'Accedi', 'advertrieste' ); ?>
				</button>
			</form>

			<p class="ac-accesso-alt">
				<a href="<?php echo esc_url( ClientArea::url( '', array( 'vista' => 'password' ) ) ); ?>">
					<?php esc_html_e( 'Password dimenticata?', 'advertrieste' ); ?>
				</a>
			</p>

		<?php endif; ?>
	</div>
</div>
