<?php
/**
 * Console cliente — Piano In Evidenza (sola consultazione).
 *
 * Variabili disponibili: $locale (WP_Post).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Cliente\Evidenza;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_attiva      = Evidenza::attiva( $locale->ID );
$advtr_programmata = Evidenza::programmata( $locale->ID );
$advtr_finestra    = Evidenza::finestra( $locale->ID );
?>
<div class="ac-card" style="margin-bottom:16px">
	<h3 class="ac-card-titolo">
		<?php esc_html_e( 'Stato del piano', 'advertrieste' ); ?>
		<?php if ( $advtr_attiva ) : ?>
			<span class="ac-pill oro"><?php esc_html_e( 'Attivo', 'advertrieste' ); ?></span>
		<?php elseif ( $advtr_programmata ) : ?>
			<span class="ac-pill attesa"><?php esc_html_e( 'Programmato', 'advertrieste' ); ?></span>
		<?php else : ?>
			<span class="ac-pill"><?php esc_html_e( 'Non attivo', 'advertrieste' ); ?></span>
		<?php endif; ?>
	</h3>

	<?php if ( $advtr_attiva || $advtr_programmata ) : ?>
		<p class="ac-card-sottotitolo">
			<?php if ( $advtr_finestra['inizio'] || $advtr_finestra['fine'] ) : ?>
				<?php
				printf(
					/* translators: 1: data inizio, 2: data fine */
					esc_html__( 'Periodo: dal %1$s al %2$s.', 'advertrieste' ),
					esc_html( $advtr_finestra['inizio'] ? $advtr_finestra['inizio'] : '—' ),
					esc_html( $advtr_finestra['fine'] ? $advtr_finestra['fine'] : '—' )
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'Attivo senza una data di fine.', 'advertrieste' ); ?>
			<?php endif; ?>
		</p>
	<?php else : ?>
		<p class="ac-card-sottotitolo">
			<?php esc_html_e( 'La tua scheda compare sulla mappa con il marker standard.', 'advertrieste' ); ?>
		</p>
	<?php endif; ?>
</div>

<div class="ac-card">
	<h3 class="ac-card-titolo"><?php esc_html_e( 'Cosa comprende', 'advertrieste' ); ?></h3>
	<p class="ac-card-sottotitolo"><?php esc_html_e( 'Il pacchetto premium agisce su come la tua attività appare agli utenti.', 'advertrieste' ); ?></p>
	<ul style="margin:0;padding-left:18px">
		<li><?php esc_html_e( 'Marker dorato sulla mappa, distinguibile a colpo d\'occhio', 'advertrieste' ); ?></li>
		<li><?php esc_html_e( 'Posizionamento prioritario nei risultati', 'advertrieste' ); ?></li>
		<li><?php esc_html_e( 'Maggiore visibilità durante i grandi eventi cittadini', 'advertrieste' ); ?></li>
	</ul>
	<?php if ( ! $advtr_attiva ) : ?>
		<p style="margin-top:16px">
			<a class="ac-btn ac-btn-primario" href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); ?>?subject=<?php echo esc_attr( rawurlencode( __( 'Piano In Evidenza', 'advertrieste' ) . ' — ' . $locale->post_title ) ); ?>">
				<?php esc_html_e( 'Richiedi il piano', 'advertrieste' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
