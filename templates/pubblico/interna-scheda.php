<?php
/**
 * Scheda attività dentro il guscio pubblico (schermata 07 della proposta).
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

	<main class="ap-contenuto ap-contenuto-scheda">
		<?php require ADVTR_PATH . 'templates/single-locale.php'; ?>
	</main>

	<?php require ADVTR_PATH . 'templates/pubblico/footer.php'; ?>
</div>
