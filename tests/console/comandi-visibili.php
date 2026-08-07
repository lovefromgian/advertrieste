<?php
/**
 * I comandi dell'area clienti ci sono e si vedono.
 *
 * Da lanciare con: wp eval-file tests/console/comandi-visibili.php
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/helpers.php';

use AdverTrieste\Frontend\ClientArea;

$cliente = get_user_by( 'login', 'demo_cliente' );
if ( ! $cliente ) {
	exit( "  demo_cliente non trovato\n" );
}

$cookie = advtr_test_sessione( $cliente->ID );

$pagina = function ( $sezione ) use ( $cookie ) {
	return advtr_test_apri( add_query_arg( 'sezione', $sezione, ClientArea::url() ), $cookie );
};

echo "\n== Le variabili di colore raggiungono il contenitore reale ==\n";

$css = (string) file_get_contents( ADVTR_PATH . 'assets/src/cliente/cliente.css' );

// Le variabili --advtr-* usate nel foglio devono essere anche dichiarate.
preg_match_all( '/var\(\s*(--advtr-[a-z-]+)/', $css, $usate );
preg_match_all( '/^\s*(--advtr-[a-z-]+)\s*:/m', $css, $dichiarate );
$mancanti = array_diff( array_unique( $usate[1] ), array_unique( $dichiarate[1] ) );
advtr_test_verifica( 'nessuna variabile usata senza essere dichiarata', ! $mancanti, implode( ', ', $mancanti ) );

// E devono essere dichiarate per il guscio della console, non solo per il
// vecchio contenitore dell'area clienti autonoma.
$blocco = '';
if ( preg_match( '/([^}]*)\{[^}]*--advtr-accento\s*:/', $css, $m ) ) {
	$blocco = trim( $m[1] );
}
advtr_test_verifica( 'il blocco delle variabili include .advtr-console', false !== strpos( $blocco, '.advtr-console' ), $blocco );
advtr_test_verifica( 'e continua a includere .advtr-cliente', false !== strpos( $blocco, '.advtr-cliente' ), $blocco );

echo "\n== Il contenitore dichiarato è quello che la pagina usa davvero ==\n";

$html = $pagina( 'scheda' );
advtr_test_verifica( "l'area clienti risponde", strlen( $html ) > 5000, strlen( $html ) . ' byte' );
advtr_test_verifica( 'la pagina è dentro .advtr-console', false !== strpos( $html, 'class="advtr-console"' ) );
advtr_test_verifica( 'e il foglio dei clienti è caricato', false !== strpos( $html, 'cliente/cliente.css' ) );

echo "\n== Ogni sezione modificabile ha il suo comando ==\n";

$attesi = array(
	'scheda'       => array( 'Salva modifiche' ),
	'immagini'     => array( 'Carica logo', 'Aggiungi foto' ),
	'offerte'      => array( 'advtr-btn-primario' ),
	'impostazioni' => array( 'ac-btn' ),
	'coupon'       => array( 'advtr-valida-btn' ),
);

foreach ( $attesi as $sezione => $aghi ) {
	$h = $pagina( $sezione );
	foreach ( $aghi as $ago ) {
		advtr_test_verifica( "$sezione: c'è \"$ago\"", false !== strpos( $h, $ago ) );
	}
	// Nessun bottone deve restare con la sola classe generica di WordPress:
	// nella console il CSS del tema è soppresso e resterebbe grezzo.
	advtr_test_verifica( "$sezione: nessun bottone con la sola classe \"button\"", ! preg_match( '/<button[^>]*class="button"/', $h ) );
}

echo "\n== Le sezioni di sola lettura non promettono comandi ==\n";

foreach ( array( 'statistiche', 'evidenza', 'abbonamento', 'qr' ) as $sezione ) {
	$h = $pagina( $sezione );
	advtr_test_verifica( "$sezione: risponde", strlen( $h ) > 5000, strlen( $h ) . ' byte' );
}

advtr_test_riepilogo();
