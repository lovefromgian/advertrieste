<?php
/**
 * Il guscio della console per il cliente: sezioni, dashboard, menu.
 *
 * Da lanciare con: wp eval-file tests/console/guscio-cliente.php
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/helpers.php';

use AdverTrieste\Frontend\ClientArea;
use AdverTrieste\Stats\Stats;

\AdverTrieste\Frontend\Map::register_assets();
\AdverTrieste\Frontend\ClientArea::registra_asset();
$cli = get_user_by('login','demo_cliente');
wp_set_current_user($cli->ID);
$loc = ClientArea::locale_utente();

echo "\n# 1. Ogni sezione rende dentro il guscio\n";
foreach (array_keys(ClientArea::sezioni()) as $sez) {
    $_GET['sezione'] = $sez;
    $html = do_shortcode('[advtr_area_clienti]');
    ok(false !== strpos($html,'advtr-console') && false === strpos($html,'Fatal'), "sezione '{$sez}'");
}
unset($_GET['sezione']);

echo "\n# 2. Elementi del mockup presenti nella dashboard\n";
$_GET['sezione']='statistiche';
$h = do_shortcode('[advtr_area_clienti]');
ok(false !== strpos($h,'ac-lato'), 'sidebar');
ok(4 === substr_count($h,'ac-kpi-valore'), 'quattro indicatori (atteso 4, reale '.substr_count($h,'ac-kpi-valore').')');
ok(false !== strpos($h,'ac-grafico'), 'grafico andamento');
ok(false !== strpos($h,'Sezioni più viste'), 'riquadro sezioni più viste');
ok(false !== strpos($h,'Statistiche della scheda'), 'titolo come nel documento');
ok(false !== strpos($h,'ac-utente-sigla'), 'card utente in fondo alla sidebar');
foreach (array('Principale','Marketing','Account') as $g) {
    ok(false !== strpos($h, $g), "gruppo di menu '{$g}'");
}

echo "\n# 3. Confronto con il periodo precedente\n";
$c = Stats::totali_confronto($loc->ID, 30);
ok(isset($c['ora']['view'], $c['prima']['view']), 'periodo corrente e precedente calcolati');
$tot = Stats::totals_by_type($loc->ID);
ok($c['ora']['view'] <= $tot['view'], 'la finestra di 30 giorni non supera il totale storico');

echo "\n# 4. Sezioni più viste\n";
global $wpdb;
$wpdb->delete( Stats::table(), array( 'post_id' => $loc->ID, 'tipo' => 'sezione' ), array( '%d', '%s' ) );
Stats::record($loc->ID,'sezione','galleria');
Stats::record($loc->ID,'sezione','galleria');
Stats::record($loc->ID,'sezione','contatti');
$s = Stats::conteggi_per_meta($loc->ID,'sezione',30);
ok(isset($s['galleria']) && 2 === $s['galleria'], 'conteggio per sezione corretto');
ok(array_key_first($s) === 'galleria', 'ordinate per frequenza decrescente');

echo "\n# 5. Banner di scadenza\n";
$fine_orig = get_post_meta($loc->ID,'advtr_data_fine',true);
update_post_meta($loc->ID,'advtr_data_fine', wp_date('Y-m-d', time()+12*DAY_IN_SECONDS));
$h = do_shortcode('[advtr_area_clienti]');
ok(false !== strpos($h,'Rinnova ora'), 'compare il pulsante Rinnova ora');
ok(false !== strpos($h,'scade fra 12 giorni'), 'conta i giorni corretti');
update_post_meta($loc->ID,'advtr_data_fine', wp_date('Y-m-d', time()+300*DAY_IN_SECONDS));
$h = do_shortcode('[advtr_area_clienti]');
ok(false === strpos($h,'Rinnova ora'), 'nessun banner se la scadenza è lontana');
update_post_meta($loc->ID,'advtr_data_fine',$fine_orig);

echo "\n# 6. Isolamento invariato\n";
$b = get_user_by('login','demo_cliente2');
$altrui = get_posts(array('post_type'=>'locale','author'=>$b->ID,'posts_per_page'=>1));
$_GET['sezione']='scheda';
$h = do_shortcode('[advtr_area_clienti]');
ok(false === strpos($h, $altrui[0]->post_title), 'la scheda altrui non compare');

advtr_test_riepilogo();
