<?php
/**
 * La console amministratore: sezioni, indicatori, elenchi.
 *
 * Da lanciare con: wp eval-file tests/console/guscio-admin.php
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/helpers.php';

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Console\Console;
use AdverTrieste\Cliente\Abbonamento;
use AdverTrieste\Cliente\Evidenza;
use AdverTrieste\Evento\Workflow;

Console::registra_asset_plugin();
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
$cli   = get_user_by( 'login', 'demo_cliente' );
$pag   = get_page_by_path( 'console' );
$GLOBALS['wp_query']->queried_object    = $pag;
$GLOBALS['wp_query']->queried_object_id = $pag->ID;

echo "\n# 1. Cancello d'accesso\n";
wp_set_current_user( 0 );
$h = do_shortcode( '[advtr_console_admin]' );
ok( false !== strpos( $h, 'Area riservata' ), 'anonimo: solo l\'invito ad accedere' );
ok( false === strpos( $h, 'ac-tabella' ), 'anonimo: nessuna tabella di dati' );

wp_set_current_user( $cli->ID );
$h = do_shortcode( '[advtr_console_admin]' );
ok( false !== strpos( $h, 'Area riservata' ), 'cliente: respinto' );
ok( false === strpos( $h, 'ac-tabella' ), 'cliente: nessun dato altrui esposto' );

echo "\n# 2. Ogni sezione rende come amministratore\n";
wp_set_current_user( $admin->ID );
foreach ( array_keys( AdminConsole::sezioni() ) as $sez ) {
	$_GET['sezione'] = $sez;
	$h = do_shortcode( '[advtr_console_admin]' );
	ok(
		false !== strpos( $h, 'advtr-console' ) && false === stripos( $h, 'fatal' ) && '' !== trim( $h ),
		"sezione '{$sez}'"
	);
}
unset( $_GET['sezione'] );

echo "\n# 3. Contenuti attesi nelle sezioni chiave\n";
$_GET['sezione'] = 'panoramica';
$h = do_shortcode( '[advtr_console_admin]' );
ok( substr_count( $h, 'ac-kpi-valore' ) >= 4, 'panoramica: quattro indicatori' );

$_GET['sezione'] = 'locali';
$h = do_shortcode( '[advtr_console_admin]' );
ok( false !== strpos( $h, 'Antico Caff' ), 'locali: elenca le schede' );
ok( false !== strpos( $h, 'Abbonamento' ), 'locali: mostra lo stato abbonamento' );

$_GET['sezione'] = 'clienti';
$h = do_shortcode( '[advtr_console_admin]' );
ok( false !== strpos( $h, 'demo_cliente' ) || false !== strpos( $h, 'Caff' ), 'clienti: elenca gli account' );

$_GET['sezione'] = 'abbonamenti';
$h = do_shortcode( '[advtr_console_admin]' );
ok( false !== strpos( $h, '+365 gg' ), 'abbonamenti: bottoni di rinnovo' );

$_GET['sezione'] = 'qr';
$h = do_shortcode( '[advtr_console_admin]' );
ok( false !== strpos( $h, 'Espositore' ) || false !== strpos( $h, 'Totem' ), 'punti QR: elenca gli espositori' );
unset( $_GET['sezione'] );

echo "\n# 4. La ricerca filtra\n";
$_GET['sezione'] = 'locali';
$_GET['q']       = 'Pepi';
$h = do_shortcode( '[advtr_console_admin]' );
ok( false !== strpos( $h, 'Pepi' ), 'ricerca trova il locale cercato' );
ok( false === strpos( $h, 'Libreria Minerva' ), 'ricerca esclude gli altri' );
unset( $_GET['q'], $_GET['sezione'] );

echo "\n# 5. Azioni (invocate come farebbe il form)\n";
$loc = get_posts( array( 'post_type' => 'locale', 'title' => 'Buffet da Pepi', 'posts_per_page' => 1 ) )[0];

// Sospendi / pubblica.
$stato_iniziale = get_post_status( $loc->ID );
$metodo = new ReflectionMethod( AdminConsole::class, 'pubblica' );
$metodo->setAccessible( true );
$metodo->invoke( null, $loc->ID, false );
ok( 'draft' === get_post_status( $loc->ID ), 'sospensione: la scheda esce dalla mappa' );
$metodo->invoke( null, $loc->ID, true );
ok( 'publish' === get_post_status( $loc->ID ), 'pubblicazione: la scheda torna online' );
ok( '' === (string) get_post_meta( $loc->ID, 'advtr_sospesa', true ), 'pubblicando si azzera il flag di sospensione automatica' );

// Rinnovo.
$prima = Abbonamento::giorni_alla_scadenza( $loc->ID );
$mr = new ReflectionMethod( AdminConsole::class, 'rinnova' );
$mr->setAccessible( true );
$mr->invoke( null, $loc->ID, 30 );
$dopo = Abbonamento::giorni_alla_scadenza( $loc->ID );
ok( null !== $dopo && ( null === $prima || $dopo > $prima ), sprintf( 'rinnovo: scadenza spostata (%s -> %s giorni)', var_export( $prima, true ), var_export( $dopo, true ) ) );

// Evidenza.
$me = new ReflectionMethod( AdminConsole::class, 'evidenza' );
$me->setAccessible( true );
$era = Evidenza::attiva( $loc->ID );
$me->invoke( null, $loc->ID );
ok( Evidenza::attiva( $loc->ID ) !== $era, 'evidenza: lo stato si inverte' );
$me->invoke( null, $loc->ID );
ok( Evidenza::attiva( $loc->ID ) === $era, 'evidenza: si riporta com\'era' );

// Rifiuto su tipo sbagliato.
$pagina_id = $pag->ID;
ok( 'negato' === $metodo->invoke( null, $pagina_id, true ), 'azione su un tipo non gestito: rifiutata' );
ok( 'negato' === $mr->invoke( null, $pagina_id, 30 ), 'rinnovo su un tipo non gestito: rifiutato' );

echo "\n# 6. Approvazione evento\n";
$ev = wp_insert_post( array( 'post_type' => 'evento', 'post_status' => 'publish', 'post_title' => 'Evento Console Test', 'post_content' => 'X' ) );
update_post_meta( $ev, 'advtr_tipo_evento', 'grande' );
Workflow::submit( $ev );
ok( Workflow::STATO_IN_REVISIONE === Workflow::stato( $ev ), 'evento in revisione' );
$ma = new ReflectionMethod( AdminConsole::class, 'approva_evento' );
$ma->setAccessible( true );
$ma->invoke( null, $ev );
ok( Workflow::is_published( $ev ), 'approvazione: la versione pubblica esiste' );
ok( Workflow::STATO_PUBBLICATO === Workflow::stato( $ev ), 'approvazione: stato aggiornato' );
wp_delete_post( $ev, true );

echo "\n# 7. Cose da fare\n";
$df = AdminConsole::da_fare();
ok( isset( $df['schede'], $df['eventi'], $df['scadenza'] ), 'il riepilogo conta le tre code' );

advtr_test_riepilogo();
