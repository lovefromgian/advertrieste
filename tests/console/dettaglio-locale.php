<?php
/**
 * La scheda di dettaglio di un locale in console.
 *
 * Da lanciare con: wp eval-file tests/console/dettaglio-locale.php
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
use AdverTrieste\Cliente\Evidenza;

Console::registra_asset_plugin();
\AdverTrieste\Frontend\ClientArea::registra_asset();
$admin = get_users(array('role'=>'administrator','number'=>1))[0];
$pag = get_page_by_path('console');
$GLOBALS['wp_query']->queried_object = $pag;
$GLOBALS['wp_query']->queried_object_id = $pag->ID;
wp_set_current_user($admin->ID);

$loc = get_posts(array('post_type'=>'locale','title'=>'Eppinger Caffè','posts_per_page'=>1))[0];

echo "\n# 1. L'elenco rimanda alla console, non alla bacheca\n";
$_GET['sezione']='locali'; unset($_GET['id']);
$h = do_shortcode('[advtr_console_admin]');
ok( false === strpos($h, 'wp-admin/post.php'), 'nessun link a wp-admin per i locali' );
ok( false !== strpos($h, 'sezione=locali'.'&#038;id=') || false !== strpos($h,'id='.$loc->ID), 'i link puntano al dettaglio in console' );

echo "\n# 2. Il dettaglio si apre nella console\n";
$_GET['id'] = $loc->ID;
$h = do_shortcode('[advtr_console_admin]');
ok( false !== strpos($h,'advtr-console'), 'reso dentro il guscio' );
ok( false !== strpos($h,'Eppinger'), 'mostra la scheda giusta' );
foreach (array('advtr_titolo','advtr_descrizione','advtr_categorie[]','advtr_servizi','advtr_telefono','advtr_orari','advtr_lat',
               'advtr_autore','advtr_stato_post','advtr_data_fine','advtr_in_evidenza','advtr_evidenza_priorita','advtr_zoom_min') as $campo) {
  ok( false !== strpos($h, 'name="'.$campo.'"'), "campo {$campo}" );
}
ok( false !== strpos($h,'data-advtr-picker'), 'selettore di posizione presente' );
ok( false !== strpos($h,'data-advtr-geocode'), 'ricerca indirizzo presente' );
$dati = wp_scripts()->get_data('advtr-cliente','data');
ok( is_string($dati) && false !== strpos($dati,'advtrCliente'), 'configurazione del selettore localizzata' );
ok( is_string($dati) && false !== strpos($dati,'geocode'), 'endpoint di geocoding nella configurazione' );

echo "\n# 3. Salvataggio: contenuti E campi commerciali\n";
$_POST = array(
  'advtr_locale_id' => $loc->ID,
  'advtr_titolo' => 'Eppinger Caffè',
  'advtr_descrizione' => 'Descrizione dalla console admin.',
  'advtr_telefono' => '+39 040 999888',
  'advtr_categorie' => array('bere'),
  'advtr_lat' => '45.651000', 'advtr_lng' => '13.769000',
  'advtr_autore' => $admin->ID,
  'advtr_stato_post' => 'publish',
  'advtr_data_fine' => wp_date('Y-m-d', time()+200*DAY_IN_SECONDS),
  'advtr_zoom_min' => '15',
  'advtr_in_evidenza' => '1',
  'advtr_evidenza_inizio' => wp_date('Y-m-d'),
  'advtr_evidenza_fine' => wp_date('Y-m-d', time()+90*DAY_IN_SECONDS),
  'advtr_evidenza_priorita' => '7',
);
$esito = \AdverTrieste\Admin\Scheda::salva();
ok( 'scheda_ok' === $esito, 'salvataggio riuscito' );
ok( 'Descrizione dalla console admin.' === get_post($loc->ID)->post_content, 'contenuto salvato' );
ok( '+39 040 999888' === get_post_meta($loc->ID,'advtr_telefono',true), 'contatto salvato' );
ok( (int)$admin->ID === (int)get_post($loc->ID)->post_author, 'proprietario cambiato' );
ok( '15' === (string)get_post_meta($loc->ID,'advtr_zoom_min',true), 'zoom minimo salvato' );
ok( 7 === (int)get_post_meta($loc->ID,'advtr_evidenza_priorita',true), 'priorità evidenza salvata' );
ok( Evidenza::attiva($loc->ID), 'evidenza attiva' );

echo "\n# 4. Il cliente non può usare questa via\n";
$cli = get_user_by('login','demo_cliente');
wp_set_current_user($cli->ID);
$_POST['advtr_evidenza_priorita'] = '99';
ok( 'negato' === \AdverTrieste\Admin\Scheda::salva(), 'salvataggio admin rifiutato al cliente' );
ok( 7 === (int)get_post_meta($loc->ID,'advtr_evidenza_priorita',true), 'la priorità non è stata toccata' );

$_POST = array();
advtr_test_riepilogo();
