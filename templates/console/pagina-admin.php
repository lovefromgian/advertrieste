<?php
/**
 * Documento della console amministratore.
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\AdverTrieste\Console\Console::registra_asset_plugin();
$advtr_contenuto = \AdverTrieste\Admin\AdminConsole::shortcode();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<?php \AdverTrieste\Console\Pagina::stampa_head(); ?>
</head>
<body class="advtr-console-pagina">
	<?php echo $advtr_contenuto; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup della console, già escapato. ?>
	<?php wp_footer(); ?>
</body>
</html>
