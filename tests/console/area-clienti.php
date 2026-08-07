<?php
/**
 * L'area clienti vista da un cliente reale.
 *
 * Da lanciare con: wp eval-file tests/console/area-clienti.php
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/helpers.php';

use AdverTrieste\Frontend\ClientArea;
use AdverTrieste\Cliente\Scheda as SchedaCliente;
use AdverTrieste\Cliente\Offerte as OfferteCliente;
use AdverTrieste\Access\AdminLock;

$GLOBALS['advtr_pass'] = 0;
$GLOBALS['advtr_fail'] = 0;
$a = get_user_by( 'login', 'demo_cliente' );
$b = get_user_by( 'login', 'demo_cliente2' );
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];

echo "\n# 1. Schermata di accesso (utente non autenticato)\n";
wp_set_current_user( 0 );
$html = do_shortcode( '[advtr_area_clienti]' );
ok( false !== strpos( $html, 'ac-accesso-scheda' ), 'mostra il form di accesso' );
ok( false !== strpos( $html, 'name="advtr_password"' ), 'campo password presente' );
ok( false === stripos( $html, 'wp-login' ), 'nessun rimando a wp-login nella pagina' );
ok( false === stripos( $html, 'wordpress' ), 'la parola "WordPress" non compare' );

echo "\n# 2. Dashboard del cliente\n";
wp_set_current_user( $a->ID );
$locale = ClientArea::locale_utente();
ok( $locale && 'locale' === $locale->post_type, 'trova la scheda del cliente: ' . ( $locale ? $locale->post_title : '—' ) );

foreach ( array( 'scheda', 'immagini', 'offerte', 'statistiche', 'coupon', 'qr' ) as $sez ) {
	$_GET['sezione'] = $sez;
	$html = do_shortcode( '[advtr_area_clienti]' );
	ok( '' !== trim( $html ) && false === strpos( $html, 'Fatal' ), "sezione '{$sez}' rende senza errori" );
}
unset( $_GET['sezione'] );

echo "\n# 3. La dashboard non espone dati altrui\n";
$_GET['sezione'] = 'scheda';
$html = do_shortcode( '[advtr_area_clienti]' );
$altrui = get_posts( array( 'post_type' => 'locale', 'author' => $b->ID, 'posts_per_page' => 1 ) );
ok( ! empty( $altrui ), 'esiste una scheda di un altro cliente' );
ok( false === strpos( $html, 'value="' . $altrui[0]->ID . '"' ), 'l\'ID della scheda altrui non compare nel form' );
ok( false === strpos( $html, $altrui[0]->post_title ), 'il titolo della scheda altrui non compare' );

echo "\n# 4. Salvataggio scheda (POST reale)\n";
$originale = $locale->post_title;
$_POST = array(
	'advtr_locale_id'  => $locale->ID,
	'advtr_titolo'     => $originale,
	'advtr_descrizione' => 'Descrizione aggiornata dal test.',
	'advtr_telefono'   => ' +39 040 111222 ',
	'advtr_email'      => 'nuova@example.com',
	'advtr_servizi'    => "Wi-Fi\nDehors\n\nAccessibile",
	'advtr_lat'        => '45.654321',
	'advtr_lng'        => '13.771234',
	'advtr_categorie'  => array( 'bere', 'inesistente-xyz' ),
);
$esito = SchedaCliente::salva();
ok( 'scheda_ok' === $esito, 'salvataggio riuscito' );
ok( 'Descrizione aggiornata dal test.' === get_post( $locale->ID )->post_content, 'descrizione aggiornata' );
ok( '+39 040 111222' === get_post_meta( $locale->ID, 'advtr_telefono', true ), 'telefono sanitizzato (spazi rimossi)' );
$servizi = get_post_meta( $locale->ID, 'advtr_servizi', true );
ok( array( 'Wi-Fi', 'Dehors', 'Accessibile' ) === $servizi, 'servizi: righe vuote scartate' );
ok( 45.654321 === (float) get_post_meta( $locale->ID, 'advtr_lat', true ), 'coordinate salvate' );
$cats = wp_get_post_terms( $locale->ID, 'categoria', array( 'fields' => 'slugs' ) );
ok( array( 'bere' ) === $cats, 'categoria inesistente scartata, valida tenuta' );

echo "\n# 5. Un cliente NON puo salvare la scheda di un altro\n";
$_POST = array( 'advtr_locale_id' => $altrui[0]->ID, 'advtr_titolo' => 'DIROTTATO' );
ok( 'negato' === SchedaCliente::salva(), 'salvataggio su scheda altrui rifiutato' );
ok( 'DIROTTATO' !== get_post( $altrui[0]->ID )->post_title, 'la scheda altrui e intatta' );

echo "\n# 6. Campi commerciali non scrivibili dal cliente\n";
$fine_prima = get_post_meta( $locale->ID, 'advtr_data_fine', true );
$_POST = array(
	'advtr_locale_id'   => $locale->ID,
	'advtr_titolo'      => $originale,
	'advtr_data_fine'   => '2099-12-31',
	'advtr_in_evidenza' => '1',
	'advtr_zoom_min'    => '0',
);
SchedaCliente::salva();
ok( $fine_prima === get_post_meta( $locale->ID, 'advtr_data_fine', true ), 'validita NON modificabile dal cliente' );
ok( '0' !== (string) get_post_meta( $locale->ID, 'advtr_zoom_min', true ), 'zoom_min NON modificabile dal cliente' );

echo "\n# 7. Offerte del cliente\n";
$_POST = array(
	'advtr_offerta_id'    => 0,
	'advtr_locale_id'     => $locale->ID,
	'advtr_titolo'        => 'Offerta di prova',
	'advtr_descrizione'   => 'Testo',
	'advtr_data_inizio'   => gmdate( 'Y-m-d' ),
	'advtr_data_scadenza' => gmdate( 'Y-m-d', time() + 7 * DAY_IN_SECONDS ),
	'advtr_tipo_coupon'   => 'codice',
	'advtr_codice'        => 'TEST10',
);
ok( 'offerta_ok' === OfferteCliente::salva(), 'creazione offerta riuscita' );
$mie = OfferteCliente::mie();
ok( ! empty( $mie ), 'l\'offerta compare fra le sue' );
$nuova = $mie[0];
ok( (int) $nuova->post_author === $a->ID, 'l\'offerta e di sua proprieta' );

$_POST['advtr_data_scadenza'] = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
$_POST['advtr_offerta_id'] = $nuova->ID;
ok( 'offerta_date' === OfferteCliente::salva(), 'date incoerenti rifiutate' );

wp_set_current_user( $b->ID );
$_POST = array( 'advtr_offerta_id' => $nuova->ID );
ok( 'negato' === OfferteCliente::elimina(), 'un altro cliente non puo eliminare questa offerta' );
wp_set_current_user( $a->ID );
$_POST = array( 'advtr_offerta_id' => $nuova->ID );
ok( 'offerta_del' === OfferteCliente::elimina(), 'il proprietario puo eliminarla' );

echo "\n# 8. Blocco della bacheca\n";
wp_set_current_user( $a->ID );
ok( AdminLock::is_cliente_bloccato(), 'il cliente e bloccato fuori da wp-admin' );
ok( false === apply_filters( 'show_admin_bar', true ), 'barra di amministrazione nascosta' );
ok( ClientArea::url() === apply_filters( 'login_redirect', admin_url(), '', wp_get_current_user() ), 'dopo il login va all\'area clienti' );
$args = apply_filters( 'ajax_query_attachments_args', array() );
ok( isset( $args['author'] ) && $args['author'] === $a->ID, 'selettore media limitato ai propri file' );

wp_set_current_user( $admin->ID );
ok( ! AdminLock::is_cliente_bloccato(), 'l\'amministratore NON e bloccato' );
$args = apply_filters( 'ajax_query_attachments_args', array() );
ok( ! isset( $args['author'] ), 'l\'amministratore vede tutta la libreria' );

// Ripristino.
wp_set_current_user( $a->ID );
$_POST = array(
	'advtr_locale_id' => $locale->ID,
	'advtr_titolo'    => $originale,
	'advtr_descrizione' => 'Caffè letterario del 1914, tavolini di marmo e libreria interna. Frequentato da Svevo e Saba, è ancora il salotto della città.',
);
SchedaCliente::salva();
$_POST = array();

advtr_test_riepilogo();
