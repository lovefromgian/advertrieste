<?php
/**
 * Geocoding via Nominatim: chiamata reale, cache, limiti, permessi.
 *
 * Da lanciare con: wp eval-file tests/console/geocoding.php
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/helpers.php';

use AdverTrieste\Geo\Geocode;

// Partiamo puliti: niente cache, niente lucchetto.
delete_transient( Geocode::LOCK );
foreach ( array( 'Piazza Unità d\'Italia, Trieste', 'Via Cesare Battisti 18, Trieste', 'qwertyuiop asdfghjkl zxcvbnm 99999' ) as $ind ) {
	delete_transient( 'advtr_geo_' . md5( strtolower( $ind ) ) );
}

echo "\n# 1. Indirizzo reale\n";
$r = Geocode::cerca( 'Piazza Unità d\'Italia, Trieste' );
if ( is_wp_error( $r ) ) {
	echo '  ! servizio non raggiungibile: ' . $r->get_error_message() . "\n";
	echo "  (verifica saltata: serve rete)\n";
	return;
}
ok( is_array( $r ) && isset( $r['lat'], $r['lng'] ), 'restituisce coordinate' );
printf( "      lat=%s lng=%s\n      %s\n", $r['lat'], $r['lng'], $r['etichetta'] );
// Piazza Unità: circa 45.6495 N, 13.7683 E. Tolleranza generosa: ~1 km.
ok( abs( $r['lat'] - 45.6495 ) < 0.01 && abs( $r['lng'] - 13.7683 ) < 0.01, 'la posizione cade su Trieste, non altrove' );

echo "\n# 2. Cache\n";
$chiave = 'advtr_geo_' . md5( strtolower( 'Piazza Unità d\'Italia, Trieste' ) );
ok( is_array( get_transient( $chiave ) ), 'il risultato e in cache' );
delete_transient( Geocode::LOCK );
$t0 = microtime( true );
$r2 = Geocode::cerca( 'Piazza Unità d\'Italia, Trieste' );
$ms = ( microtime( true ) - $t0 ) * 1000;
ok( $r2 === $r, 'la seconda chiamata restituisce lo stesso risultato' );
ok( $ms < 50, sprintf( 'servita dalla cache senza toccare la rete (%.1f ms)', $ms ) );

echo "\n# 3. Limite di 1 richiesta al secondo\n";
delete_transient( Geocode::LOCK );
delete_transient( 'advtr_geo_' . md5( strtolower( 'Via Cesare Battisti 18, Trieste' ) ) );
Geocode::cerca( 'Via Cesare Battisti 18, Trieste' );
$subito = Geocode::cerca( 'Molo Audace, Trieste' );
ok( is_wp_error( $subito ) && 'advtr_geocode_troppo_rapido' === $subito->get_error_code(), 'una seconda richiesta immediata viene fermata' );

echo "\n# 4. Casi limite\n";
$vuoto = Geocode::cerca( '   ' );
ok( is_wp_error( $vuoto ) && 'advtr_geocode_vuoto' === $vuoto->get_error_code(), 'indirizzo vuoto rifiutato senza chiamare la rete' );
delete_transient( Geocode::LOCK );
$assurdo = Geocode::cerca( 'qwertyuiop asdfghjkl zxcvbnm 99999' );
ok( is_wp_error( $assurdo ), 'indirizzo inesistente: errore, non coordinate a caso' );
if ( is_wp_error( $assurdo ) ) {
	printf( "      codice: %s\n", $assurdo->get_error_code() );
}

echo "\n# 5. Permessi dell'endpoint REST\n";
do_action( 'rest_api_init' );
function req( $ind ) {
	$r = new WP_REST_Request( 'GET', '/advertrieste/v1/geocode' );
	$r->set_param( 'indirizzo', $ind );
	$res = rest_do_request( $r );
	return $res->get_status();
}
wp_set_current_user( 0 );
ok( 401 === req( 'Trieste' ), 'anonimo -> 401' );
$sub = wp_insert_user( array( 'user_login' => 'tmp_geo_sub', 'user_pass' => 'x', 'user_email' => 'tmp_geo@ex.com', 'role' => 'subscriber' ) );
wp_set_current_user( $sub );
ok( 403 === req( 'Trieste' ), 'subscriber -> 403' );
$cli = get_user_by( 'login', 'demo_cliente' );
wp_set_current_user( $cli->ID );
delete_transient( Geocode::LOCK );
$s = req( 'Piazza Unità d\'Italia, Trieste' );
ok( 200 === $s, 'cliente (dalla cache) -> 200, atteso 200, reale ' . $s );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $sub );

advtr_test_riepilogo();
