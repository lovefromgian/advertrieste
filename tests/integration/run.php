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
use AdverTrieste\Rest\Eventi as EventiRest;

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
 * Il titolo compare fra i marker restituiti?
 *
 * @param mixed  $marker Risposta dell'endpoint.
 * @param string $titolo Titolo cercato.
 * @return bool
 */
function in_titoli( $marker, $titolo ) {
	foreach ( (array) $marker as $m ) {
		if ( isset( $m['title'] ) && $m['title'] === $titolo ) {
			return true;
		}
	}
	return false;
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
echo "\n# 6. Tipi tracciabili pubblicamente\n";
// `coupon` è la metrica di ritorno commerciale mostrata al cliente: può nascere
// solo dalla validazione autenticata dell'esercente, mai da /track pubblico.
wp_set_current_user( 0 );
$nonce         = wp_create_nonce( 'wp_rest' );
$coupon_prima  = Stats::totals_by_type( $loc )['coupon'];
list( $s )     = advtr_req( 'POST', "/advertrieste/v1/locale/{$loc}/track", array( 'tipo' => 'coupon' ), $nonce );
advtr_eq( 400, $s, '/track con tipo coupon → 400 (non tracciabile pubblicamente)' );
advtr_eq( $coupon_prima, Stats::totals_by_type( $loc )['coupon'], 'nessun coupon registrato dall\'endpoint pubblico' );
list( $s ) = advtr_req( 'POST', "/advertrieste/v1/locale/{$loc}/track", array( 'tipo' => 'contact' ), $nonce );
advtr_eq( 200, $s, '/track con tipo contact → 200 (tipo pubblico ammesso)' );

/* ------------------------------------------------------------------ */
echo "\n# 6b. Tracciamento senza nonce (compatibilità con le page cache)\n";
// Il nonce non viene più emesso per gli anonimi: congelato in una pagina in
// cache scadrebbe (vive 12–24h contro le 24h di una copia servita) e il core
// risponderebbe 403, azzerando i conteggi in silenzio. Al suo posto si verifica
// l'origine della richiesta. Questa sezione blocca quel comportamento.
/**
 * Esegue una richiesta REST con intestazioni arbitrarie.
 *
 * @param string               $method Metodo.
 * @param string               $route  Route.
 * @param array<string,mixed>  $params Parametri.
 * @param array<string,string> $head   Intestazioni.
 * @return array{0:int,1:mixed}
 */
function advtr_req_head( $method, $route, $params = array(), $head = array() ) {
	$req = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$req->set_param( $k, $v );
	}
	foreach ( $head as $k => $v ) {
		$req->set_header( $k, $v );
	}
	$res = rest_do_request( $req );
	return array( $res->get_status(), $res->get_data() );
}

wp_set_current_user( 0 );
$view_prima = Stats::totals_by_type( $loc )['view'];

list( $s, $d ) = advtr_req_head(
	'POST',
	"/advertrieste/v1/locale/{$loc}/track",
	array( 'tipo' => 'view' ),
	array( 'Origin' => home_url() )
);
advtr_eq( 200, $s, 'senza nonce, dal sito → 200 (visitatore su pagina in cache)' );
advtr_eq( $view_prima + 1, Stats::totals_by_type( $loc )['view'], 'la visita è stata registrata davvero' );

list( $s ) = advtr_req_head(
	'POST',
	"/advertrieste/v1/locale/{$loc}/track",
	array( 'tipo' => 'contact' ),
	array( 'Origin' => 'https://sito-esterno.example' )
);
advtr_eq( 403, $s, 'senza nonce, da origine esterna → 403' );

list( $s ) = advtr_req_head(
	'POST',
	"/advertrieste/v1/locale/{$loc}/track",
	array( 'tipo' => 'contact' ),
	array( 'Referer' => 'https://sito-esterno.example/pagina' )
);
advtr_eq( 403, $s, 'anche il solo Referer esterno viene rifiutato' );

// Il nonce non deve raggiungere gli anonimi: è ciò che rendeva il tracciamento
// incompatibile con le page cache. In CLI l'hook wp_enqueue_scripts non scatta,
// quindi registriamo noi gli asset prima di rendere lo shortcode.
\AdverTrieste\Frontend\Map::register_assets();
do_shortcode( '[advtr_map]' );
$dati_mappa = wp_scripts()->get_data( 'advtr-map', 'data' );
advtr_ok( is_string( $dati_mappa ) && false !== strpos( $dati_mappa, '"nonce":""' ), 'nessun nonce nella configurazione servita agli anonimi' );

/* ------------------------------------------------------------------ */
echo "\n# 7. Fuso orario della serie statistica\n";
// Le righe sono scritte con current_time() (fuso del sito): la finestra della
// serie deve usare lo stesso fuso. Guardia di regressione sul confine di
// mezzanotte — con una finestra calcolata in UTC l'ultimo giorno slitta.
$oggi  = wp_date( 'Y-m-d' );
$serie = Stats::daily_series( $loc, 'view', 7 );
advtr_eq( 7, count( $serie ), 'serie giornaliera: 7 giorni richiesti, 7 restituiti' );
advtr_eq( $oggi, (string) array_key_last( $serie ), 'ultimo giorno della serie = oggi nel fuso del sito' );
advtr_ok( isset( $serie[ $oggi ] ) && $serie[ $oggi ] >= 1, 'le visite di oggi cadono nel giorno corrente' );

/* ------------------------------------------------------------------ */
echo "\n# 8. Cache dei locali in evento\n";
$grande = wp_insert_post( array( 'post_type' => 'evento', 'post_status' => 'publish', 'post_title' => 'Barcolana', 'post_author' => $admin ) );
update_post_meta( $grande, 'advtr_tipo_evento', 'grande' );
update_post_meta( $grande, 'advtr_locali_collegati', array( $loc ) );
update_post_meta( $grande, 'advtr_data_inizio', wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
update_post_meta( $grande, 'advtr_data_fine', wp_date( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );

Workflow::approve( $grande );
advtr_ok( false === get_transient( EventiRest::CACHE_LOCALI_IN_EVENTO ), 'approvazione evento → cache invalidata' );

$in_evento = EventiRest::locali_in_evento();
advtr_ok( isset( $in_evento[ $loc ] ), 'locale collegato a un grande evento in corso risulta evidenziato' );
advtr_ok( is_array( get_transient( EventiRest::CACHE_LOCALI_IN_EVENTO ) ), 'risultato memorizzato in cache' );

wp_update_post( array( 'ID' => $grande, 'post_title' => 'Barcolana 2' ) );
advtr_ok( false === get_transient( EventiRest::CACHE_LOCALI_IN_EVENTO ), 'modifica evento → cache invalidata' );

/* ------------------------------------------------------------------ */
echo "\n# 9. Ricerca libera sulla mappa (§1.2)\n";
// L'ingresso guidato manda il termine qui: se la ricerca non trovasse nulla,
// il campo della home sarebbe decorativo.
update_post_meta( $loc, 'advtr_indirizzo', 'Via delle Prove 42, Trieste' );
// La scheda di prova nasce senza coordinate: l'endpoint filtra per riquadro,
// quindi senza queste non comparirebbe a prescindere dalla ricerca.
update_post_meta( $loc, 'advtr_lat', 45.65 );
update_post_meta( $loc, 'advtr_lng', 13.77 );
update_post_meta( $loc, 'advtr_zoom_min', 0 );
$bbox = array( 'min_lat' => 45.0, 'max_lat' => 46.0, 'min_lng' => 13.0, 'max_lng' => 14.5, 'zoom' => 18 );

list( , $r ) = advtr_req( 'GET', '/advertrieste/v1/map/markers', array_merge( $bbox, array( 'q' => 'Loc' ) ) );
advtr_ok( in_titoli( $r, get_the_title( $loc ) ), 'ricerca per nome trova la scheda' );

list( , $r ) = advtr_req( 'GET', '/advertrieste/v1/map/markers', array_merge( $bbox, array( 'q' => 'delle Prove' ) ) );
advtr_ok( in_titoli( $r, get_the_title( $loc ) ), 'ricerca per VIA trova la scheda (indirizzo nei meta)' );

list( , $r ) = advtr_req( 'GET', '/advertrieste/v1/map/markers', array_merge( $bbox, array( 'q' => 'zzznonesiste' ) ) );
advtr_eq( 0, count( (array) $r ), 'termine inesistente non restituisce nulla' );

list( , $r ) = advtr_req( 'GET', '/advertrieste/v1/map/markers', $bbox );
advtr_ok( count( (array) $r ) > 1, 'senza termine i marker restano tutti' );

// La ricerca non deve aggirare la visibilità.
$protetta = wp_insert_post(
	array(
		'post_type'      => 'locale',
		'post_status'    => 'publish',
		'post_title'     => 'Scheda Protetta Prove',
		'post_password'  => 'segreto',
	)
);
update_post_meta( $protetta, 'advtr_lat', 45.65 );
update_post_meta( $protetta, 'advtr_lng', 13.77 );
list( , $r ) = advtr_req( 'GET', '/advertrieste/v1/map/markers', array_merge( $bbox, array( 'q' => 'Protetta Prove' ) ) );
advtr_ok( ! in_titoli( $r, 'Scheda Protetta Prove' ), 'una scheda protetta da password resta fuori dai risultati' );
wp_delete_post( $protetta, true );

/* ------------------------------------------------------------------ */
// Pulizia.
foreach ( array( $qr, $ev, $loc, $off, $scaduto, $loc_altrui, $grande ) as $pid ) {
	wp_delete_post( $pid, true );
}
delete_transient( EventiRest::CACHE_LOCALI_IN_EVENTO );
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
