<?php
/**
 * Rende una sezione di console e riferisce se è uscita pulita.
 *
 * Strumento di servizio, non una suite: serve a guardare una sezione senza
 * aprire il browser, per esempio dopo aver toccato un template.
 *
 * Uso (le opzioni sono parole semplici: WP-CLI si prenderebbe quelle con --):
 *   wp eval-file tests/console/rendi.php admin locali
 *   wp eval-file tests/console/rendi.php admin locali cestino
 *   wp eval-file tests/console/rendi.php admin clienti 26
 *   wp eval-file tests/console/rendi.php cliente statistiche
 *   wp eval-file tests/console/rendi.php admin locali dump
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Frontend\ClientArea;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_chi      = isset( $args[0] ) ? $args[0] : 'admin';
$advtr_sezione  = isset( $args[1] ) ? $args[1] : 'panoramica';
$advtr_opzioni  = array_slice( (array) $args, 2 );
$advtr_id       = 0;
$advtr_cestino  = in_array( 'cestino', $advtr_opzioni, true );
$advtr_dump     = in_array( 'dump', $advtr_opzioni, true );

foreach ( $advtr_opzioni as $advtr_o ) {
	if ( ctype_digit( (string) $advtr_o ) ) {
		$advtr_id = (int) $advtr_o;
	}
}

if ( 'cliente' === $advtr_chi ) {
	$advtr_utente = get_user_by( 'login', 'demo_cliente' );
	$advtr_pagina = get_post( (int) get_option( 'advtr_client_area_page_id' ) );
	if ( ! $advtr_pagina ) {
		$advtr_pagina = get_page_by_path( 'area-clienti' );
	}
} else {
	$advtr_lista  = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$advtr_utente = $advtr_lista ? $advtr_lista[0] : null;
	$advtr_pagina = get_post( (int) get_option( AdminConsole::OPTION_PAGE ) );
}

if ( ! $advtr_utente || ! $advtr_pagina ) {
	exit( "  Utente o pagina non trovati: hai lanciato tools/seed-demo.php?\n" );
}

wp_set_current_user( $advtr_utente->ID );

// Il template si aspetta una query che punti alla pagina della console.
$GLOBALS['wp_query']->queried_object    = $advtr_pagina;
$GLOBALS['wp_query']->queried_object_id = $advtr_pagina->ID;
$GLOBALS['wp_query']->is_page           = true;
$GLOBALS['wp_query']->is_singular       = true;

$_GET['sezione'] = $advtr_sezione;
if ( $advtr_cestino ) {
	$_GET['cestino'] = '1';
}
if ( $advtr_id ) {
	$_GET['id'] = $advtr_id;
}

ob_start();
include ADVTR_PATH . ( 'cliente' === $advtr_chi
	? 'templates/console/pagina-intera.php'
	: 'templates/console/pagina-admin.php' );
$advtr_html = ob_get_clean();

if ( $advtr_dump ) {
	echo $advtr_html; // phpcs:ignore
	exit( 0 );
}

$advtr_problemi = array();
if ( preg_match( '/(Fatal error|Warning:|Notice:|Deprecated:)/', $advtr_html, $advtr_m ) ) {
	$advtr_problemi[] = 'php: ' . $advtr_m[1];
}
if ( strlen( $advtr_html ) < 2000 ) {
	$advtr_problemi[] = 'output troppo corto (' . strlen( $advtr_html ) . ' byte)';
}

$advtr_etichetta = $advtr_chi . '/' . $advtr_sezione
	. ( $advtr_cestino ? ' [cestino]' : '' )
	. ( $advtr_id ? ' #' . $advtr_id : '' );

printf(
	"  %-30s %s\n",
	$advtr_etichetta,
	$advtr_problemi ? 'PROBLEMA: ' . implode( '; ', $advtr_problemi ) : 'ok (' . strlen( $advtr_html ) . ' byte)'
);

exit( $advtr_problemi ? 1 : 0 );
