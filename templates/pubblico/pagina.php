<?php
/**
 * Documento completo delle pagine pubbliche del progetto.
 *
 * Come per la console: niente `get_header()`/`get_footer()`, quindi il markup
 * del tema non esiste. Il contenuto si compone prima di `wp_head()`, perché è
 * lì che si accodano gli asset e `wp_localize_script()` richiede handle già
 * registrati.
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\AdverTrieste\Console\Console::registra_asset_plugin();
\AdverTrieste\Frontend\Home::registra_asset();

$advtr_contenuto = \AdverTrieste\Frontend\Home::shortcode();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body class="advtr-pubblico-pagina">
	<?php echo $advtr_contenuto; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup del plugin, già escapato. ?>
	<?php wp_footer(); ?>
</body>
</html>
