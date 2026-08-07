<?php
/**
 * La ricerca per indirizzo dentro l'area clienti.
 *
 * Da lanciare con: wp eval-file tests/console/geocoding-cliente.php
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/helpers.php';

use AdverTrieste\Geo\Geocode;

// In CLI l'hook wp_enqueue_scripts non scatta: registriamo noi gli asset,
// altrimenti wp_localize_script non ha un handle su cui agire e tace.
\AdverTrieste\Frontend\ClientArea::registra_asset();

$cli = get_user_by( 'login', 'demo_cliente' );
wp_set_current_user( $cli->ID );
$_GET['sezione'] = 'scheda';

echo "\n# 1. Il pulsante è nella sezione scheda\n";
$html = do_shortcode( '[advtr_area_clienti]' );
ok( false !== strpos( $html, 'data-advtr-geocode' ), 'pulsante "Trova dall\'indirizzo" presente' );
ok( false !== strpos( $html, 'data-advtr-geo-esito' ), 'area messaggi presente' );
ok( false !== strpos( $html, 'name="advtr_indirizzo"' ), 'il campo Indirizzo dei contatti esiste (quello che il pulsante legge)' );
ok( 1 === substr_count( $html, 'name="advtr_indirizzo"' ), 'campo indirizzo UNICO: non ne è stato aggiunto un secondo' );
ok( false !== strpos( $html, 'data-advtr-picker' ), 'selettore su mappa presente' );

echo "\n# 2. La configurazione JS arriva alla pagina\n";
// wp_localize_script scrive nello script registrato: verifichiamo il dato.
global $wp_scripts;
$dati = $wp_scripts->get_data( 'advtr-cliente', 'data' );
ok( is_string( $dati ) && false !== strpos( $dati, 'advtrCliente' ), 'oggetto advtrCliente localizzato' );
ok( is_string( $dati ) && false !== strpos( $dati, 'geocode' ), 'endpoint geocode presente nella configurazione' );
ok( is_string( $dati ) && false !== strpos( $dati, 'nonce' ), 'nonce presente' );

echo "\n# 3. Il cliente puo davvero usare l'endpoint\n";
do_action( 'rest_api_init' );
delete_transient( Geocode::LOCK );
$req = new WP_REST_Request( 'GET', '/advertrieste/v1/geocode' );
$req->set_param( 'indirizzo', 'Via Cesare Battisti 18, Trieste' );
$res = rest_do_request( $req );
ok( 200 === $res->get_status(), 'cliente -> 200 (atteso 200, reale ' . $res->get_status() . ')' );
$d = $res->get_data();
if ( is_array( $d ) && isset( $d['lat'] ) ) {
	printf( "      lat=%s lng=%s\n", $d['lat'], $d['lng'] );
	ok( abs( $d['lat'] - 45.65 ) < 0.02, 'coordinate su Trieste' );
	// Confronto con la posizione del seed per lo stesso indirizzo.
	$loc = get_posts( array( 'post_type' => 'locale', 'title' => 'Antico Caffè San Marco', 'posts_per_page' => 1 ) );
	if ( $loc ) {
		$slat = (float) get_post_meta( $loc[0]->ID, 'advtr_lat', true );
		$slng = (float) get_post_meta( $loc[0]->ID, 'advtr_lng', true );
		$dlat = ( $d['lat'] - $slat ) * 111320;
		$dlng = ( $d['lng'] - $slng ) * 111320 * cos( deg2rad( 45.65 ) );
		printf( "      scarto dalla posizione salvata: %.0f m\n", sqrt( $dlat * $dlat + $dlng * $dlng ) );
	}
}

echo "\n# 4. Resta chiuso a chi non deve entrare\n";
wp_set_current_user( 0 );
$req = new WP_REST_Request( 'GET', '/advertrieste/v1/geocode' );
$req->set_param( 'indirizzo', 'Trieste' );
ok( 401 === rest_do_request( $req )->get_status(), 'anonimo -> 401' );

advtr_test_riepilogo();
