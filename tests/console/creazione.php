<?php
/**
 * Creazione di contenuti e account dalla console.
 *
 * Da lanciare con: wp eval-file tests/console/creazione.php
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/helpers.php';

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Admin\Salva;
use AdverTrieste\Console\Console;
use AdverTrieste\Access\Roles;

Console::registra_asset_plugin();
\AdverTrieste\Frontend\ClientArea::registra_asset();
$admin = get_users(array('role'=>'administrator','number'=>1))[0];
$pag = get_page_by_path('console');
$GLOBALS['wp_query']->queried_object = $pag;
$GLOBALS['wp_query']->queried_object_id = $pag->ID;
wp_set_current_user($admin->ID);

echo "\n# 1. Il bottone c'è in ogni sezione\n";
foreach (array('locali','poi','offerte','eventi','qr') as $sez) {
  $_GET['sezione']=$sez; unset($_GET['id']);
  $h = do_shortcode('[advtr_console_admin]');
  ok( false !== strpos($h,'value="crea"'), "sezione '{$sez}': bottone Aggiungi" );
}
$_GET['sezione']='clienti';
$h = do_shortcode('[advtr_console_admin]');
ok( false !== strpos($h,'value="crea_cliente"'), 'clienti: modulo di creazione' );
ok( false === strpos($h,'type="password"'), 'clienti: nessun campo password' );

echo "\n# 2. Creazione dei contenuti\n";
$creati = array();
foreach (Salva::CREABILI as $sez=>$tipo) {
  $_POST = array();
  $nuovo = Salva::crea($sez);
  ok( $nuovo > 0, "creato '{$sez}'" );
  if (!$nuovo) continue;
  $creati[] = $nuovo;
  ok( $tipo === get_post_type($nuovo), "  tipo corretto ({$tipo})" );
  ok( 'draft' === get_post_status($nuovo), "  nasce in bozza, non online" );
}
$loc = null;
foreach ($creati as $c) { if ('locale' === get_post_type($c)) $loc = $c; }
ok( '14' === (string)get_post_meta($loc,'advtr_zoom_min',true), 'locale: zoom di partenza sensato' );
ok( '' !== (string)get_post_meta($loc,'advtr_data_fine',true), 'locale: validità di partenza impostata' );

echo "\n# 3. Il nuovo elemento non compare al pubblico\n";
$r = rest_do_request(new WP_REST_Request('GET','/advertrieste/v1/map/markers'));
$req = new WP_REST_Request('GET','/advertrieste/v1/map/markers');
foreach (array('min_lat'=>45.0,'max_lat'=>46.0,'min_lng'=>13.0,'max_lng'=>14.5,'zoom'=>18) as $k=>$v) $req->set_param($k,$v);
$dati = rest_do_request($req)->get_data();
$ids = array(); foreach ((array)$dati as $m) $ids[] = $m['id'];
ok( !in_array($loc, $ids, true), 'il locale appena creato non è sulla mappa' );

echo "\n# 4. Creazione di un cliente\n";
$mail = 'nuovo_cliente_test@advertrieste.local';
if (email_exists($mail)) wp_delete_user(email_exists($mail));
$GLOBALS['mail_inviate'] = 0;
add_filter('pre_wp_mail', function(){ ++$GLOBALS['mail_inviate']; return true; }, 10, 0);
$_POST = array('advtr_nome'=>'Nuovo Cliente Prova','advtr_email'=>$mail,'advtr_ruolo'=>Roles::CLIENTE);
$esito = Salva::crea_cliente();
ok( 'creato_cliente' === $esito['esito'], 'account creato' );
$u = get_userdata($esito['id']);
ok( $u && 'Nuovo Cliente Prova' === $u->display_name, 'nome impostato' );
ok( $u && in_array(Roles::CLIENTE,(array)$u->roles,true), 'ruolo cliente' );
ok( $GLOBALS['mail_inviate'] >= 1, 'email con il link per la password inviata' );

$_POST = array('advtr_nome'=>'Doppione','advtr_email'=>$mail,'advtr_ruolo'=>Roles::CLIENTE);
ok( 'account_email' === Salva::crea_cliente()['esito'], 'email già usata: rifiutata' );
$_POST = array('advtr_nome'=>'','advtr_email'=>'non-una-email','advtr_ruolo'=>Roles::CLIENTE);
ok( 'account_ko' === Salva::crea_cliente()['esito'], 'dati non validi: rifiutati' );

echo "\n# 5. Un cliente non può creare\n";
wp_set_current_user(get_user_by('login','demo_cliente')->ID);
ok( 0 === Salva::crea('locali'), 'creazione contenuti negata al cliente' );
$_POST = array('advtr_nome'=>'X','advtr_email'=>'x@y.local','advtr_ruolo'=>Roles::CLIENTE);
ok( 'negato' === Salva::crea_cliente()['esito'], 'creazione account negata al cliente' );

// Pulizia.
wp_set_current_user($admin->ID);
foreach ($creati as $c) wp_delete_post($c, true);
require_once ABSPATH . 'wp-admin/includes/user.php';
if ($esito['id']) wp_delete_user($esito['id']);
$_POST = array();

advtr_test_riepilogo();
