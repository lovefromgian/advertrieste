<?php
/**
 * Pagina pubblica interna: barra di navigazione, intestazione, contenuto.
 *
 * Il contenuto è quello del post — in pratica gli shortcode del plugin — reso
 * dentro il guscio comune, così mappa, offerte ed eventi condividono la veste
 * dell'ingresso.
 *
 * Si usa il Loop e non `apply_filters( 'the_content', … )`: oltre a non
 * invocare un hook del core dall'esterno, `the_post()` imposta i dati globali
 * del post, su cui gli shortcode fanno affidamento.
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ap-sito">
	<?php require ADVTR_PATH . 'templates/pubblico/navbar.php'; ?>

	<main class="ap-contenuto">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<?php if ( get_the_title() ) : ?>
				<h1 class="ap-pagina-titolo"><?php the_title(); ?></h1>
			<?php endif; ?>
			<?php the_content(); ?>
			<?php
		endwhile;
		?>
	</main>

	<?php require ADVTR_PATH . 'templates/pubblico/footer.php'; ?>
</div>
