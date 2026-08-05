<?php
/**
 * Documento completo della console: nessun elemento del tema.
 *
 * Non chiama `get_header()` né `get_footer()`, quindi intestazione, menu e piè
 * di pagina del tema non esistono proprio — non sono nascosti via CSS, che si
 * romperebbe al primo cambio di tema.
 *
 * `wp_head()` e `wp_footer()` restano perché sono il meccanismo con cui
 * WordPress stampa gli asset accodati: senza, non arriverebbero né il CSS della
 * console né Leaflet. Gli asset del tema vengono invece rimossi a monte (vedi
 * `ClientArea::asset_console`).
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Il contenuto si compone PRIMA di wp_head(): è lì che la console accoda i
// propri asset, e wp_head deve trovarli già in coda per stamparli nell'head.
// Rimandandolo dopo, CSS e JS finirebbero nel piè di pagina e la console
// comparirebbe per un istante senza stile.
$advtr_contenuto = \AdverTrieste\Frontend\ClientArea::shortcode();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body class="advtr-console-pagina">
	<?php echo $advtr_contenuto; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup della console, già escapato. ?>
	<?php wp_footer(); ?>
</body>
</html>
