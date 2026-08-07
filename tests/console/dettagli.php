<?php
/**
 * Le schede di dettaglio di POI, offerte, eventi, punti QR e clienti.
 *
 * Da lanciare con: wp eval-file tests/console/dettagli.php
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
use AdverTrieste\Evento\Workflow;
use AdverTrieste\Access\Roles;

Console::registra_asset_plugin();
\AdverTrieste\Frontend\ClientArea::registra_asset();
$admin = get_users(array('role'=>'administrator','number'=>1))[0];
$pag = get_page_by_path('console');
$GLOBALS['wp_query']->queried_object = $pag;
$GLOBALS['wp_query']->queried_object_id = $pag->ID;
wp_set_current_user($admin->ID);

$poi = get_posts(array('post_type'=>'poi','posts_per_page'=>1))[0];
$qr  = get_posts(array('post_type'=>'punto_qr','posts_per_page'=>1))[0];
$off = get_posts(array('post_type'=>'offerta','posts_per_page'=>1))[0];
$cli = get_user_by('login','demo_cliente2');
$ev  = wp_insert_post(array('post_type'=>'evento','post_status'=>'publish','post_title'=>'Evento Dettaglio Test','post_content'=>'V1'));
update_post_meta($ev,'advtr_tipo_evento','grande');

echo "\n# 1. Nessun elenco rimanda più alla bacheca\n";
foreach (array('locali','poi','offerte','eventi','qr','clienti') as $sez) {
  $_GET['sezione']=$sez; unset($_GET['id']);
  $h = do_shortcode('[advtr_console_admin]');
  ok( false === strpos($h,'wp-admin/post.php') && false === strpos($h,'wp-admin/user-edit.php'), "sezione '{$sez}'" );
}

echo "\n# 2. Ogni dettaglio si apre nella console\n";
foreach (array('poi'=>$poi->ID, 'qr'=>$qr->ID, 'offerte'=>$off->ID, 'eventi'=>$ev, 'clienti'=>$cli->ID) as $sez=>$oid) {
  $_GET['sezione']=$sez; $_GET['id']=$oid;
  $h = do_shortcode('[advtr_console_admin]');
  ok( false !== strpos($h,'advtr-console') && false !== strpos($h,'advtr_azione'), "dettaglio '{$sez}' con modulo" );
}
unset($_GET['id']);

echo "\n# 3. Salvataggi\n";
$_POST = array('advtr_id'=>$poi->ID,'advtr_titolo'=>$poi->post_title,'advtr_descrizione'=>'Testo POI console.',
  'advtr_tipo'=>'castello','advtr_lat'=>'45.7100','advtr_lng'=>'13.7200','advtr_zoom_min'=>'2',
  'advtr_categorie'=>array('visitare'),'advtr_stato_post'=>'publish');
ok('salvato'===Salva::poi(),'POI salvato');
ok('castello'===get_post_meta($poi->ID,'advtr_tipo',true),'POI: tipo aggiornato');
ok('2'===(string)get_post_meta($poi->ID,'advtr_zoom_min',true),'POI: zoom aggiornato');

$_POST = array('advtr_id'=>$qr->ID,'advtr_titolo'=>$qr->post_title,'advtr_indirizzo'=>'Piazza Prova 1',
  'advtr_lat'=>'45.6600','advtr_lng'=>'13.7700','advtr_stato'=>'inattivo','advtr_stato_post'=>'publish');
ok('salvato'===Salva::qr(),'punto QR salvato');
ok('Piazza Prova 1'===get_post_meta($qr->ID,'advtr_indirizzo',true),'QR: indirizzo salvato');
ok('inattivo'===get_post_meta($qr->ID,'advtr_stato',true),'QR: stato salvato');

$_POST = array('advtr_id'=>$off->ID,'advtr_titolo'=>$off->post_title,'advtr_descrizione'=>'X',
  'advtr_data_inizio'=>wp_date('Y-m-d'),'advtr_data_scadenza'=>wp_date('Y-m-d',time()-DAY_IN_SECONDS),
  'advtr_codice'=>'ABC','advtr_stato_post'=>'publish');
ok('date_ko'===Salva::offerta(),'offerta: date incoerenti rifiutate');
$_POST['advtr_data_scadenza']=wp_date('Y-m-d',time()+20*DAY_IN_SECONDS);
$_POST['advtr_codice']='NUOVO10';
ok('salvato'===Salva::offerta(),'offerta salvata');
ok('NUOVO10'===get_post_meta($off->ID,'advtr_codice',true),'offerta: codice aggiornato');

$loc = get_posts(array('post_type'=>'locale','posts_per_page'=>1))[0];
Workflow::approve($ev);
$_POST = array('advtr_id'=>$ev,'advtr_titolo'=>'Evento Dettaglio Test','advtr_descrizione'=>'V2',
  'advtr_tipo_evento'=>'grande','advtr_locali_collegati'=>array($loc->ID),'advtr_stato_post'=>'publish');
ok('salvato'===Salva::evento(),'evento salvato');
ok(Workflow::STATO_BOZZA===Workflow::stato($ev),'evento: salvare NON pubblica, torna in bozza');
$pub = Workflow::public_version($ev);
ok(isset($pub['contenuto']) && false === strpos($pub['contenuto'],'V2'),'il pubblico vede ancora la versione approvata');
ok(in_array($loc->ID,(array)get_post_meta($ev,'advtr_locali_collegati',true),true),'evento: locali collegati salvati');

$_POST = array('advtr_id'=>$cli->ID,'advtr_nome'=>'Ristorazione Demo','advtr_email'=>$cli->user_email,'advtr_ruolo'=>Roles::ORGANIZZATORE);
ok('salvato'===Salva::cliente(),'cliente salvato');
ok(in_array(Roles::ORGANIZZATORE,(array)get_userdata($cli->ID)->roles,true),'cliente: ruolo cambiato');
$_POST['advtr_ruolo']=Roles::CLIENTE; Salva::cliente();

echo "\n# 4. Limiti\n";
$_POST = array('advtr_id'=>$admin->ID,'advtr_nome'=>'X','advtr_email'=>$admin->user_email,'advtr_ruolo'=>Roles::CLIENTE);
ok('negato'===Salva::cliente(),'un amministratore non si modifica da qui');
ok(!in_array(Roles::CLIENTE,(array)get_userdata($admin->ID)->roles,true),'il ruolo admin è intatto');

wp_set_current_user(get_user_by('login','demo_cliente')->ID);
$_POST = array('advtr_id'=>$qr->ID,'advtr_titolo'=>'DIROTTATO');
ok('negato'===Salva::qr(),'un cliente non salva dalla console admin');
ok('DIROTTATO'!==get_post($qr->ID)->post_title,'il punto QR è intatto');

wp_set_current_user($admin->ID);
wp_delete_post($ev,true);
$_POST=array();
advtr_test_riepilogo();
