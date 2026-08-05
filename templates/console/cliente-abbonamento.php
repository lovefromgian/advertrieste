<?php
/**
 * Console cliente — stato dell'abbonamento e rinnovo.
 *
 * Variabili disponibili: $locale (WP_Post).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Cliente\Abbonamento;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_stato  = Abbonamento::stato( $locale->ID );
$advtr_giorni = Abbonamento::giorni_alla_scadenza( $locale->ID );
$advtr_woo    = class_exists( 'WooCommerce' );
?>
<div class="ac-griglia ac-griglia-2">

	<div class="ac-card">
		<h3 class="ac-card-titolo">
			<?php esc_html_e( 'Stato', 'advertrieste' ); ?>
			<span class="ac-pill <?php echo esc_attr( $advtr_stato['pill'] ); ?>"><?php echo esc_html( $advtr_stato['etichetta'] ); ?></span>
		</h3>
		<p class="ac-card-sottotitolo">
			<?php if ( null === $advtr_giorni ) : ?>
				<?php esc_html_e( 'La tua scheda non ha una data di scadenza impostata.', 'advertrieste' ); ?>
			<?php elseif ( $advtr_giorni > 0 ) : ?>
				<?php
				printf(
					/* translators: %d: giorni mancanti */
					esc_html( _n( 'Mancano %d giorno alla scadenza.', 'Mancano %d giorni alla scadenza.', $advtr_giorni, 'advertrieste' ) ),
					(int) $advtr_giorni
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'L\'abbonamento è scaduto e la scheda non è più visibile sulla mappa.', 'advertrieste' ); ?>
			<?php endif; ?>
		</p>
	</div>

	<div class="ac-card">
		<h3 class="ac-card-titolo"><?php esc_html_e( 'Periodo di validità', 'advertrieste' ); ?></h3>
		<p class="ac-card-sottotitolo">
			<?php
			printf(
				/* translators: 1: data inizio, 2: data fine */
				esc_html__( 'Dal %1$s al %2$s', 'advertrieste' ),
				esc_html( Abbonamento::data_inizio( $locale->ID ) ? Abbonamento::data_inizio( $locale->ID ) : '—' ),
				esc_html( Abbonamento::data_scadenza( $locale->ID ) ? Abbonamento::data_scadenza( $locale->ID ) : '—' )
			);
			?>
		</p>
	</div>

</div>

<div class="ac-card">
	<h3 class="ac-card-titolo"><?php esc_html_e( 'Rinnovo', 'advertrieste' ); ?></h3>
	<?php if ( $advtr_woo ) : ?>
		<p class="ac-card-sottotitolo">
			<?php esc_html_e( 'Il rinnovo estende la validità e riattiva la scheda se era stata sospesa.', 'advertrieste' ); ?>
		</p>
		<p>
			<a class="ac-btn ac-btn-primario" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>">
				<?php esc_html_e( 'Rinnova ora', 'advertrieste' ); ?>
			</a>
		</p>
	<?php else : ?>
		<p class="ac-card-sottotitolo">
			<?php esc_html_e( 'Il rinnovo online non è ancora attivo su questo sito: scrivi alla redazione e ce ne occupiamo noi. Riceverai comunque un promemoria via email prima della scadenza.', 'advertrieste' ); ?>
		</p>
		<p>
			<a class="ac-btn ac-btn-primario" href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); ?>?subject=<?php echo esc_attr( rawurlencode( __( 'Rinnovo abbonamento', 'advertrieste' ) . ' — ' . $locale->post_title ) ); ?>">
				<?php esc_html_e( 'Richiedi il rinnovo', 'advertrieste' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
