<?php
/**
 * Suite di integrazione dei percorsi critici (architettura §8).
 *
 * Eseguire con WP-CLI (WordPress caricato, plugin attivo):
 *   wp eval-file wp-content/plugins/advertrieste/tests/integration/run.php
 *
 * Copre: access control mappa QR, esclusione punto_qr dai marker pubblici,
 * workflow revisione eventi (doppia versione), scadenze/sospensione, coupon,
 * soglia visite, capability mapping (editing self-service).
 *
 * NB: non è PHPUnit (in questo ambiente manca lo scaffolding wp-phpunit + test DB);
 * è una suite di integrazione con assert reali, pensata come rete di regressione.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Evento\Workflow;
use AdverTrieste\Stats\Stats;
use AdverTrieste\Coupon\Coupon;
use AdverTrieste\Scadenze\Scadenze;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Eseguire con WP-CLI (wp eval-file).\n" );
	exit( 1 );
}

$GLOBALS['advtr_pass'] = 0;
$GLOBALS['advtr_fail'] = 0;

/**
 * Asserisce che una condizione sia vera.
 *
 * @param bool   $cond  Condizione.
 * @param string $label Descrizione.
 * @return void
 */
function advtr_ok( $cond, $label ) {
	if ( $cond ) {
		++$GLOBALS['advtr_pass'];
		echo "  \u{2713} {$label}\n";
	} else {
		++$GLOBALS['advtr_fail'];
		echo "  \u{2717} FAIL: {$label}\n";
	}
}

/**
 * Asserisce l'uguaglianza fra atteso e reale.
 *
 * @param mixed  $expected Atteso.
 * @param mixed  $actual   Reale.
 * @param string $label    Descrizione.
 * @return void
 */
function advtr_eq( $expected, $actual, $label ) {
	advtr_ok( $expected === $actual, $label . " (atteso " . var_export( $expected, true ) . ', reale ' . var_export( $actual, true ) . ')' );
}

/**
 * Esegue una richiesta REST e ne restituisce [status, data].
 *
 * @param string      $method Metodo.
 * @param string      $route  Route.
 * @param array       $params Query/body param.
 * @param string|null $nonce  Nonce REST opzionale.
 * @return array{0:int,1:mixed}
 */
function advtr_req( $method, $route, $params = array(), $nonce = null ) {
	$req = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$req->set_param( $k, $v );
	}
	if ( $nonce ) {
		$req->set_header( 'X-WP-Nonce', $nonce );
	}
	$res = rest_do_request( $req );
	return array( $res->get_status(), $res->get_data() );
}

do_action( 'rest_api_init' );
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID;
$now   = strtotime( current_time( 'mysql' ) );

/* ------------------------------------------------------------------ */
echo "\n# 1. Access control mappa QR + esclusione punto_qr\n";
$cli = wp_insert_user( array( 'user_login' => 'it_cli', 'user_pass' => 'x', 'user_email' => 'it_cli@ex.com', 'role' => 'cliente_locale' ) );
$sub = wp_insert_user( array( 'user_login' => 'it_sub', 'user_pass' => 'x', 'user_email' => 'it_sub@ex.com', 'role' => 'subscriber' ) );
$qr  = wp_insert_post( array( 'post_type' => 'punto_qr', 'post_status' => 'publish', 'post_title' => 'QR', 'post_author' => $admin ) );
update_post_meta( $qr, 'advtr_lat', 45.649 );
update_post_meta( $qr, 'advtr_lng', 13.77 );

wp_set_current_user( 0 );
list( $s ) = advtr_req( 'GET', '/advertrieste/v1/qr-map' );
advtr_eq( 401, $s, 'qr-map non autenticato → 401' );
wp_set_current_user( $sub );
list( $s ) = advtr_req( 'GET', '/advertrieste/v1/qr-map' );
advtr_eq( 403, $s, 'qr-map subscriber → 403' );
wp_set_current_user( $cli );
list( $s, $d ) = advtr_req( 'GET', '/advertrieste/v1/qr-map' );
advtr_eq( 200, $s, 'qr-map cliente → 200' );
advtr_ok( is_array( $d ) && count( $d ) >= 1, 'qr-map cliente riceve i punti' );

wp_set_current_user( 0 );
list( , $mk ) = advtr_req( 'GET', '/advertrieste/v1/map/markers', array( 'min_lat' => 45.4, 'max_lat' => 45.9, 'min_lng' => 13.4, 'max_lng' => 14.0, 'zoom' => 18 ) );
$tipi = array();
foreach ( (array) $mk as $m ) {
	$tipi[ $m['type'] ] = true;
}
advtr_ok( ! isset( $tipi['punto_qr'] ), 'punto_qr MAI nei marker pubblici' );

/* ------------------------------------------------------------------ */
echo "\n# 2. Workflow revisione eventi (doppia versione)\n";
$org = wp_insert_user( array( 'user_login' => 'it_org', 'user_pass' => 'x', 'user_email' => 'it_org@ex.com', 'role' => 'organizzatore_evento' ) );
$ev  = wp_insert_post( array( 'post_type' => 'evento', 'post_status' => 'publish', 'post_title' => 'Ev', 'post_content' => 'V1', 'post_author' => $org ) );
update_post_meta( $ev, 'advtr_tipo_evento', 'organizzatore' );

wp_set_current_user( 0 );
list( $s ) = advtr_req( 'POST', "/advertrieste/v1/evento/{$ev}/submit" );
advtr_eq( 401, $s, 'submit anonimo → 401' );
wp_set_current_user( $sub );
list( $s ) = advtr_req( 'POST', "/advertrieste/v1/evento/{$ev}/submit" );
advtr_eq( 403, $s, 'submit non-autore → 403' );
wp_set_current_user( $org );
list( $s ) = advtr_req( 'POST', "/advertrieste/v1/evento/{$ev}/submit" );
advtr_eq( 200, $s, 'submit autore → 200' );
list( , $pub ) = advtr_req( 'GET', '/advertrieste/v1/eventi' );
advtr_ok( ! self_in_list( $pub, $ev ), 'evento non ancora approvato: assente dal pubblico' );
wp_set_current_user( $org );
list( $s ) = advtr_req( 'POST', "/advertrieste/v1/evento/{$ev}/approve" );
advtr_eq( 403, $s, 'approve organizzatore → 403' );
wp_set_current_user( $admin );
list( $s ) = advtr_req( 'POST', "/advertrieste/v1/evento/{$ev}/approve" );
advtr_eq( 200, $s, 'approve admin → 200' );
list( , $pub ) = advtr_req( 'GET', '/advertrieste/v1/eventi' );
advtr_eq( 'V1', list_content( $pub, $ev ), 'pubblico vede V1 dopo approvazione' );
wp_update_post( array( 'ID' => $ev, 'post_content' => 'V2' ) );
Workflow::mark_dirty( $ev );
list( , $pub ) = advtr_req( 'GET', '/advertrieste/v1/eventi' );
advtr_eq( 'V1', list_content( $pub, $ev ), 'modifica non approvata: pubblico vede ANCORA V1' );
Workflow::approve( $ev );
list( , $pub ) = advtr_req( 'GET', '/advertrieste/v1/eventi' );
advtr_eq( 'V2', list_content( $pub, $ev ), 'dopo ri-approvazione: pubblico vede V2' );

/* ------------------------------------------------------------------ */
echo "\n# 3. Soglia visite + coupon\n";
$loc = wp_insert_post( array( 'post_type' => 'locale', 'post_status' => 'publish', 'post_title' => 'Loc', 'post_author' => $cli ) );
for ( $i = 0; $i < Stats::SOGLIA_VISITE; $i++ ) {
	Stats::record( $loc, 'view' );
}
advtr_ok( Stats::soglia_raggiunta( $loc ), 'soglia visite raggiunta a ' . Stats::SOGLIA_VISITE );
advtr_ok( ! Stats::is_novita( $loc ), 'oltre soglia: non più "Novità"' );

$off = wp_insert_post( array( 'post_type' => 'offerta', 'post_status' => 'publish', 'post_title' => 'Off', 'post_author' => $cli ) );
update_post_meta( $off, 'advtr_locale_id', $loc );
update_post_meta( $off, 'advtr_codice', 'ABC' );
update_post_meta( $off, 'advtr_data_scadenza', gmdate( 'Y-m-d H:i:s', $now + DAY_IN_SECONDS ) );
wp_set_current_user( $admin );
list( $s ) = advtr_req( 'POST', "/advertrieste/v1/offerta/{$off}/redeem", array( 'codice' => 'WRONG' ) );
advtr_eq( 422, $s, 'redeem codice errato → 422' );
list( $s, $d ) = advtr_req( 'POST', "/advertrieste/v1/offerta/{$off}/redeem", array( 'codice' => 'ABC' ) );
advtr_eq( 200, $s, 'redeem codice giusto → 200' );
advtr_eq( 1, $d['riscatti'], 'un riscatto registrato' );
advtr_eq( 1, Stats::totals_by_type( $loc )['coupon'], 'evento coupon tracciato' );

/* ------------------------------------------------------------------ */
echo "\n# 4. Scadenze schede\n";
$scaduto = wp_insert_post( array( 'post_type' => 'locale', 'post_status' => 'publish', 'post_title' => 'Scad', 'post_author' => $cli ) );
update_post_meta( $scaduto, 'advtr_data_fine', gmdate( 'Y-m-d', $now - DAY_IN_SECONDS ) );
$GLOBALS['advtr_mail_count'] = 0;
add_filter( 'pre_wp_mail', function () {
	++$GLOBALS['advtr_mail_count'];
	return true;
}, 10, 0 );
$sum = Scadenze::check();
advtr_ok( $sum['sospese'] >= 1, 'scheda scaduta sospesa' );
advtr_eq( 'draft', get_post_status( $scaduto ), 'scheda sospesa → draft (fuori dalla mappa)' );
advtr_ok( $GLOBALS['advtr_mail_count'] >= 1, 'email di notifica inviata' );

/* ------------------------------------------------------------------ */
echo "\n# 5. Capability mapping (editing self-service)\n";
$loc_altrui = wp_insert_post( array( 'post_type' => 'locale', 'post_status' => 'publish', 'post_title' => 'Altrui', 'post_author' => $admin ) );
wp_set_current_user( $cli );
advtr_ok( current_user_can( 'edit_post', $loc ), 'cliente edita il PROPRIO locale' );
advtr_ok( ! current_user_can( 'edit_post', $loc_altrui ), 'cliente NON edita locale altrui' );
advtr_ok( ! current_user_can( 'advtr_approve_evento' ), 'cliente NON può approvare eventi' );
wp_set_current_user( $admin );
advtr_ok( current_user_can( 'edit_post', $loc_altrui ), 'admin edita qualsiasi locale' );

/* ------------------------------------------------------------------ */
// Pulizia.
foreach ( array( $qr, $ev, $loc, $off, $scaduto, $loc_altrui ) as $pid ) {
	wp_delete_post( $pid, true );
}
foreach ( array( $cli, $sub, $org ) as $uid ) {
	wp_delete_user( $uid );
}

echo "\n----------------------------------------\n";
printf( "RISULTATO: %d passati, %d falliti\n", $GLOBALS['advtr_pass'], $GLOBALS['advtr_fail'] );
if ( $GLOBALS['advtr_fail'] > 0 ) {
	exit( 1 );
}

/**
 * L'evento è presente nella lista pubblica?
 *
 * @param mixed $list Lista eventi.
 * @param int   $id   ID evento.
 * @return bool
 */
function self_in_list( $list, $id ) {
	foreach ( (array) $list as $e ) {
		if ( (int) $e['id'] === (int) $id ) {
			return true;
		}
	}
	return false;
}

/**
 * Contenuto pubblico di un evento nella lista, o '' se assente.
 *
 * @param mixed $list Lista eventi.
 * @param int   $id   ID evento.
 * @return string
 */
function list_content( $list, $id ) {
	foreach ( (array) $list as $e ) {
		if ( (int) $e['id'] === (int) $id ) {
			return trim( wp_strip_all_tags( (string) $e['contenuto'] ) );
		}
	}
	return '';
}
