<?php
/**
 * Template dell'ingresso guidato (shortcode `[advtr_onboarding]`).
 *
 * Variabili (da Onboarding::shortcode):
 *   $titolo  string      domanda guida
 *   $map_url string      URL della pagina mappa (già escapato)
 *   $terms   WP_Term[]   categorie d'intenzione
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="advtr-onboarding">
	<h2 class="advtr-ob-titolo"><?php echo esc_html( $titolo ); ?></h2>
	<div class="advtr-ob-cards">
		<?php foreach ( $terms as $advtr_term ) : ?>
			<a class="advtr-ob-card advtr-ob-<?php echo esc_attr( $advtr_term->slug ); ?>"
				href="<?php echo esc_url( add_query_arg( 'categoria', $advtr_term->slug, $map_url ) ); ?>">
				<span class="advtr-ob-nome"><?php echo esc_html( $advtr_term->name ); ?></span>
			</a>
		<?php endforeach; ?>
		<a class="advtr-ob-card advtr-ob-tutte" href="<?php echo esc_url( $map_url ); ?>">
			<span class="advtr-ob-nome"><?php esc_html_e( 'Esplora tutto', 'advertrieste' ); ?></span>
		</a>
	</div>
</div>
