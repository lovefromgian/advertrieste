<?php
/**
 * I due gestori di azioni non devono pestarsi i piedi.
 *
 * Da lanciare con: wp eval-file tests/console/azioni-incrociate.php
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/helpers.php';

use AdverTrieste\Frontend\ClientArea;
use AdverTrieste\Admin\AdminConsole;

$admin = get_users(array('role'=>'administrator','number'=>1))[0];
wp_set_current_user($admin->ID);

$console = get_page_by_path('console');
$area    = get_page_by_path('area-clienti');

/**
 * Finge di essere su una pagina.
 *
 * @param WP_Post $pagina Pagina.
 */
function su_pagina( $pagina ) {
	$GLOBALS['wp_query']->queried_object    = $pagina;
	$GLOBALS['wp_query']->queried_object_id = $pagina->ID;
}

echo "\n# Sulla console, l'area clienti si tira indietro\n";
su_pagina( $console );
$_POST = array( 'advtr_azione' => 'crea', 'advtr_sezione' => 'locali' );
ok( ! ClientArea::e_console(), 'la pagina console non è riconosciuta come area clienti' );

// Se il gestore dell'area clienti intervenisse, morirebbe qui sul nonce.
$morto = false;
try {
	ClientArea::gestisci_azioni();
} catch ( \Throwable $e ) {
	$morto = true;
}
ok( ! $morto, 'il gestore dell\'area clienti non interviene sul POST della console' );

echo "\n# Sull'area clienti, la console si tira indietro\n";
su_pagina( $area );
$_POST = array( 'advtr_azione' => 'scheda_salva' );
ok( ! AdminConsole::e_console(), 'la pagina area clienti non è riconosciuta come console' );
$morto = false;
try {
	AdminConsole::gestisci_azioni();
} catch ( \Throwable $e ) {
	$morto = true;
}
ok( ! $morto, 'il gestore della console non interviene sul POST dell\'area clienti' );

echo "\n# Il nonce della console è valido per la console\n";
su_pagina( $console );
$n = wp_create_nonce( AdminConsole::NONCE );
ok( 1 === wp_verify_nonce( $n, AdminConsole::NONCE ), 'nonce della console verificabile' );
ok( false === wp_verify_nonce( $n, ClientArea::NONCE ), 'lo stesso nonce NON vale per l\'area clienti (ecco perché serviva la guardia)' );

$_POST = array();
advtr_test_riepilogo();
