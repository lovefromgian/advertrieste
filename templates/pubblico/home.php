<?php
/**
 * Home pubblica — "Cosa stai cercando?" (schermata 05 della proposta).
 *
 * Variabili disponibili: $termini (WP_Term[]).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Frontend\Home;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_intenzioni = Home::intenzioni();
?>
<div class="ap-ingresso">

	<?php require ADVTR_PATH . 'templates/pubblico/navbar.php'; ?>

	<main class="ap-eroe">
		<p class="ap-occhiello"><?php esc_html_e( 'Benvenuto', 'advertrieste' ); ?></p>

		<h1 class="ap-titolo">
			<?php
			printf(
				/* translators: %s: la parola "cercando", evidenziata */
				esc_html__( 'Cosa stai %s?', 'advertrieste' ),
				'<em>' . esc_html__( 'cercando', 'advertrieste' ) . '</em>'
			);
			?>
		</h1>

		<p class="ap-sottotitolo">
			<?php esc_html_e( 'Dicci cosa vuoi fare: ti mostriamo i posti giusti vicino a te.', 'advertrieste' ); ?>
		</p>

		<form class="ap-cerca" method="get" action="<?php echo esc_url( Home::url_mappa() ); ?>" role="search">
			<span class="ap-cerca-icona" aria-hidden="true">🔍</span>
			<label class="screen-reader-text" for="ap-q"><?php esc_html_e( 'Cerca un locale, una via, una zona', 'advertrieste' ); ?></label>
			<input type="search" id="ap-q" name="q" autocomplete="off"
				placeholder="<?php esc_attr_e( 'Pizzeria, museo, parrucchiere, una via…', 'advertrieste' ); ?>" />
			<button type="submit" class="ap-btn ap-btn-primario"><?php esc_html_e( 'Cerca', 'advertrieste' ); ?></button>
		</form>

		<p class="ap-oppure"><?php esc_html_e( '…oppure scegli cosa vuoi fare', 'advertrieste' ); ?></p>

		<div class="ap-intenzioni">
			<?php foreach ( $termini as $advtr_term ) : ?>
				<?php
				$advtr_i      = $advtr_intenzioni[ $advtr_term->slug ] ?? null;
				$advtr_icona  = $advtr_i ? $advtr_i['icona'] : '📍';
				$advtr_titolo = $advtr_i ? $advtr_i['titolo'] : $advtr_term->name;
				$advtr_dett   = $advtr_i ? $advtr_i['dett'] : '';
				?>
				<a class="ap-carta" href="<?php echo esc_url( Home::url_mappa( array( 'categoria' => $advtr_term->slug ) ) ); ?>">
					<span class="ap-carta-icona" aria-hidden="true"><?php echo esc_html( $advtr_icona ); ?></span>
					<span class="ap-carta-titolo"><?php echo esc_html( $advtr_titolo ); ?></span>
					<?php if ( $advtr_dett ) : ?>
						<span class="ap-carta-dett"><?php echo esc_html( $advtr_dett ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>

		<p class="ap-tutto">
			<a href="<?php echo esc_url( Home::url_mappa() ); ?>">
				<?php esc_html_e( 'Oppure esplora tutta la mappa →', 'advertrieste' ); ?>
			</a>
		</p>
	</main>
</div>
