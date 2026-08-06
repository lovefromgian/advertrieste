<?php
/**
 * Piè di pagina pubblico del progetto.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Frontend\ClientArea;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<footer class="ap-pie">
	<span class="ap-pie-marchio">
		<img class="ap-logo ap-logo-pie" src="<?php echo esc_url( ADVTR_URL . 'assets/img/logo.png' ); ?>"
			alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="480" height="333" />
	</span>
	<span class="ap-pie-voci">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'advertrieste' ); ?></a>
		<a href="<?php echo esc_url( ClientArea::url() ); ?>"><?php esc_html_e( 'Area clienti', 'advertrieste' ); ?></a>
	</span>
	<span class="ap-pie-nota">
		<?php echo esc_html( sprintf( '© %s', wp_date( 'Y' ) ) ); ?>
	</span>
</footer>
