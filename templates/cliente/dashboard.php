<?php
/**
 * Guscio dell'area clienti: intestazione, navigazione e sezione corrente.
 *
 * Variabili disponibili: $avviso (array|null), $sezione (string),
 * $locale (WP_Post|null), $utente (WP_User).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Frontend\ClientArea;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="advtr-cliente">

	<header class="advtr-cliente-testa">
		<div>
			<span class="advtr-cliente-occhiello"><?php esc_html_e( 'Area clienti', 'advertrieste' ); ?></span>
			<strong class="advtr-cliente-nome"><?php echo esc_html( $utente->display_name ); ?></strong>
		</div>
		<a class="advtr-btn advtr-btn-neutro" href="<?php echo esc_url( wp_logout_url( ClientArea::url() ) ); ?>">
			<?php esc_html_e( 'Esci', 'advertrieste' ); ?>
		</a>
	</header>

	<nav class="advtr-cliente-nav">
		<?php foreach ( ClientArea::sezioni() as $advtr_slug => $advtr_label ) : ?>
			<a
				class="advtr-cliente-voce<?php echo $advtr_slug === $sezione ? ' attiva' : ''; ?>"
				href="<?php echo esc_url( ClientArea::url( $advtr_slug ) ); ?>"
			><?php echo esc_html( $advtr_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( ! empty( $avviso ) ) : ?>
		<p class="advtr-cliente-avviso <?php echo esc_attr( $avviso['tipo'] ); ?>">
			<?php echo esc_html( $avviso['testo'] ); ?>
		</p>
	<?php endif; ?>

	<?php if ( ! $locale && in_array( $sezione, array( 'scheda', 'immagini', 'offerte' ), true ) ) : ?>

		<div class="advtr-cliente-vuoto">
			<h3><?php esc_html_e( 'Nessuna scheda collegata al tuo account', 'advertrieste' ); ?></h3>
			<p>
				<?php esc_html_e( 'La scheda della tua attività viene creata dalla redazione al momento dell\'attivazione. Appena è pronta la troverai qui e potrai completarla.', 'advertrieste' ); ?>
			</p>
			<p>
				<a href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
					<?php esc_html_e( 'Scrivi alla redazione', 'advertrieste' ); ?>
				</a>
			</p>
		</div>

	<?php else : ?>

		<div class="advtr-cliente-corpo">
			<?php
			switch ( $sezione ) {
				case 'immagini':
					require ADVTR_PATH . 'templates/cliente/sez-immagini.php';
					break;
				case 'offerte':
					require ADVTR_PATH . 'templates/cliente/sez-offerte.php';
					break;
				case 'statistiche':
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lo shortcode restituisce markup già escapato.
					echo do_shortcode( '[advtr_statistiche]' );
					break;
				case 'coupon':
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lo shortcode restituisce markup già escapato.
					echo do_shortcode( '[advtr_valida_coupon]' );
					break;
				case 'qr':
					require ADVTR_PATH . 'templates/cliente/sez-qr.php';
					break;
				case 'scheda':
				default:
					require ADVTR_PATH . 'templates/cliente/sez-scheda.php';
					break;
			}
			?>
		</div>

	<?php endif; ?>
</div>
