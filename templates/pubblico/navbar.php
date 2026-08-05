<?php
/**
 * Barra di navigazione pubblica del progetto.
 *
 * Sostituisce quella del tema sulle pagine servite dal plugin.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Frontend\Home;
use AdverTrieste\Frontend\ClientArea;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_voci = array(
	array(
		'etichetta' => __( 'Esplora', 'advertrieste' ),
		'url'       => Home::url_mappa(),
		'attiva'    => true,
	),
	array(
		'etichetta' => __( 'Offerte', 'advertrieste' ),
		'url'       => home_url( '/offerte/' ),
		'attiva'    => false,
	),
	array(
		'etichetta' => __( 'Eventi', 'advertrieste' ),
		'url'       => home_url( '/eventi-advtr/' ),
		'attiva'    => false,
	),
);
?>
<header class="ap-nav">
	<a class="ap-marchio" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<span class="ap-pin" aria-hidden="true"></span>
		<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
	</a>

	<nav class="ap-nav-voci">
		<?php foreach ( $advtr_voci as $advtr_v ) : ?>
			<a class="ap-nav-voce<?php echo $advtr_v['attiva'] ? ' attiva' : ''; ?>"
				href="<?php echo esc_url( $advtr_v['url'] ); ?>">
				<?php echo esc_html( $advtr_v['etichetta'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<a class="ap-btn ap-btn-primario" href="<?php echo esc_url( ClientArea::url() ); ?>">
		<?php esc_html_e( 'Area Clienti', 'advertrieste' ); ?>
	</a>
</header>
