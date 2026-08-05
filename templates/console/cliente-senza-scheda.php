<?php
/**
 * Console cliente — nessuna scheda collegata all'account.
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ac-vuoto">
	<h3><?php esc_html_e( 'Nessuna scheda collegata al tuo account', 'advertrieste' ); ?></h3>
	<p>
		<?php esc_html_e( 'La scheda della tua attività viene creata dalla redazione al momento dell\'attivazione. Appena è pronta la trovi qui e puoi completarla.', 'advertrieste' ); ?>
	</p>
	<p style="margin-top:16px">
		<a class="ac-btn ac-btn-primario" href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
			<?php esc_html_e( 'Scrivi alla redazione', 'advertrieste' ); ?>
		</a>
	</p>
</div>
