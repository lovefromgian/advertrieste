<?php
/**
 * Seed dei contenuti dimostrativi (ambienti di sviluppo).
 *
 * Crea pagine, locali, punti d'interesse, un'offerta con coupon e alcuni punti
 * QR per poter provare le funzionalità del plugin su un'installazione vuota.
 *
 * Eseguire con WP-CLI (WordPress caricato, plugin attivo):
 *   wp eval-file wp-content/plugins/advertrieste/tools/seed-demo.php
 *   wp eval-file wp-content/plugins/advertrieste/tools/seed-demo.php pulisci
 *
 * È IDEMPOTENTE: ogni oggetto è marcato con il meta `_advtr_demo_key` e a una
 * seconda esecuzione viene aggiornato, non duplicato. La modalità `pulisci`
 * rimuove tutto ciò che ha creato — contenuti, utente demo, righe delle tabelle
 * custom — e ripristina l'impostazione della pagina iniziale che ha trovato.
 *
 * NON usare in produzione: crea un utente con password nota.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Cpt\Categoria;
use AdverTrieste\Stats\Stats;
use AdverTrieste\Coupon\Coupon;
use AdverTrieste\Access\Roles;
use AdverTrieste\Rest\Eventi as EventiRest;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Eseguire con WP-CLI (wp eval-file).\n" );
	exit( 1 );
}

/** Meta che marca gli oggetti creati da questo script. */
const ADVTR_DEMO_META = '_advtr_demo_key';

/** Opzione con il backup dell'impostazione di pagina iniziale. */
const ADVTR_DEMO_BACKUP = 'advtr_demo_front_backup';

/** Login dell'utente cliente dimostrativo. */
const ADVTR_DEMO_USER = 'demo_cliente';

/** Password dell'utente dimostrativo (solo ambienti locali). */
const ADVTR_DEMO_PASS = 'demo1234';

/**
 * Trova l'ID di un oggetto demo dalla sua chiave.
 *
 * @param string $key Chiave logica dell'oggetto.
 * @return int ID del post, 0 se assente.
 */
function advtr_demo_find( $key ) {
	$found = get_posts(
		array(
			'post_type'        => 'any',
			'post_status'      => 'any',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'suppress_filters' => false,
			'meta_key'         => ADVTR_DEMO_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	return $found ? (int) $found[0] : 0;
}

/**
 * Crea o aggiorna un oggetto demo.
 *
 * @param string               $key   Chiave logica (identità stabile fra le esecuzioni).
 * @param array<string,mixed>  $args  Argomenti per wp_insert_post/wp_update_post.
 * @param array<string,mixed>  $meta  Meta da impostare (chiave => valore).
 * @param string[]             $terms Slug di `categoria` da assegnare.
 * @return int ID del post.
 */
function advtr_demo_upsert( $key, $args, $meta = array(), $terms = array() ) {
	$id = advtr_demo_find( $key );

	if ( $id ) {
		$args['ID'] = $id;
		wp_update_post( $args );
	} else {
		$id = wp_insert_post( $args );
		if ( is_wp_error( $id ) || ! $id ) {
			echo "  ! impossibile creare {$key}\n";
			return 0;
		}
		update_post_meta( $id, ADVTR_DEMO_META, $key );
	}

	foreach ( $meta as $mk => $mv ) {
		update_post_meta( $id, $mk, $mv );
	}
	if ( $terms ) {
		wp_set_object_terms( $id, $terms, Categoria::TAXONOMY );
	}
	return (int) $id;
}

/* ==================================================================== */
/* Modalità pulizia.                                                     */
/* ==================================================================== */

$modo = isset( $args[0] ) ? (string) $args[0] : 'seed';

if ( 'pulisci' === $modo ) {
	echo "Pulizia dei contenuti dimostrativi…\n";

	// Ripristina l'impostazione della pagina iniziale trovata al primo seed.
	$backup = get_option( ADVTR_DEMO_BACKUP );
	if ( is_array( $backup ) ) {
		update_option( 'show_on_front', $backup['show_on_front'] );
		update_option( 'page_on_front', $backup['page_on_front'] );
		delete_option( ADVTR_DEMO_BACKUP );
		printf( "  pagina iniziale ripristinata (show_on_front=%s, page_on_front=%s)\n", $backup['show_on_front'], $backup['page_on_front'] );
	}

	$ids = get_posts(
		array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => ADVTR_DEMO_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		)
	);

	global $wpdb;
	// NB: non usare `$id` a livello di script — è una global di WordPress.
	foreach ( $ids as $demo_id ) {
		// Righe delle tabelle custom collegate al post.
		$wpdb->delete( Stats::table(), array( 'post_id' => $demo_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Coupon::table(), array( 'offerta_id' => $demo_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_delete_post( $demo_id, true );
	}
	printf( "  %d contenuti eliminati\n", count( $ids ) );

	$user = get_user_by( 'login', ADVTR_DEMO_USER );
	if ( $user ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user->ID );
		echo "  utente demo eliminato\n";
	}

	delete_transient( EventiRest::CACHE_LOCALI_IN_EVENTO );
	flush_rewrite_rules();
	echo "Fatto.\n";
	return;
}

/* ==================================================================== */
/* Seed.                                                                 */
/* ==================================================================== */

echo "Seed dei contenuti dimostrativi…\n";

// I termini di `categoria` esistono già dopo l'attivazione, ma non diamolo per scontato.
Categoria::seed_terms();

/* -- Utente cliente dimostrativo ------------------------------------- */

$user = get_user_by( 'login', ADVTR_DEMO_USER );
if ( $user ) {
	$demo_user = $user->ID;
} else {
	$demo_user = wp_insert_user(
		array(
			'user_login'   => ADVTR_DEMO_USER,
			'user_pass'    => ADVTR_DEMO_PASS,
			'user_email'   => 'demo_cliente@advertrieste.local',
			'display_name' => 'Cliente Demo',
			'role'         => Roles::CLIENTE,
		)
	);
	if ( is_wp_error( $demo_user ) ) {
		echo "  ! utente demo non creato: " . $demo_user->get_error_message() . "\n";
		$demo_user = 0;
	}
}
printf( "  utente cliente: %s / %s\n", ADVTR_DEMO_USER, ADVTR_DEMO_PASS );

$oggi    = current_time( 'Y-m-d' );
$fra_uno = wp_date( 'Y-m-d', time() + YEAR_IN_SECONDS );

/* -- Punti d'interesse (musei, castelli, monumenti) ------------------- */
// zoom_min basso: visibili già da lontano, prima delle attività commerciali.

$poi = array(
	array(
		'key'   => 'poi-miramare',
		'title' => 'Castello di Miramare',
		'desc'  => 'Residenza ottocentesca dell\'arciduca Massimiliano d\'Asburgo, affacciata sul golfo con il suo parco di 22 ettari. Museo storico e riserva marina.',
		'lat'   => 45.7025,
		'lng'   => 13.7126,
		'tipo'  => 'castello',
		'zoom'  => 0,
	),
	array(
		'key'   => 'poi-san-giusto',
		'title' => 'Castello di San Giusto',
		'desc'  => 'Fortezza cinquecentesca sul colle che domina la città, accanto alla cattedrale. Dai bastioni si vede tutto il golfo di Trieste.',
		'lat'   => 45.6469,
		'lng'   => 13.7739,
		'tipo'  => 'castello',
		'zoom'  => 0,
	),
	array(
		'key'   => 'poi-revoltella',
		'title' => 'Museo Revoltella',
		'desc'  => 'Galleria d\'arte moderna nata dal lascito del barone Pasquale Revoltella, con l\'ampliamento firmato Carlo Scarpa.',
		'lat'   => 45.6473,
		'lng'   => 13.7669,
		'tipo'  => 'museo',
		'zoom'  => 0,
	),
	array(
		'key'   => 'poi-faro-vittoria',
		'title' => 'Faro della Vittoria',
		'desc'  => 'Faro monumentale del 1927, alto 68 metri, dedicato ai caduti del mare. Illumina il golfo e si visita dalla terrazza panoramica.',
		'lat'   => 45.6706,
		'lng'   => 13.7521,
		'tipo'  => 'monumento',
		'zoom'  => 0,
	),
	array(
		'key'   => 'poi-molo-audace',
		'title' => 'Molo Audace',
		'desc'  => 'Duecentocinquanta metri di pietra bianca sul mare, il punto da cui i triestini guardano il tramonto e la Barcolana.',
		'lat'   => 45.6503,
		'lng'   => 13.7663,
		'tipo'  => 'monumento',
		'zoom'  => 0,
	),
	array(
		'key'   => 'poi-grotta-gigante',
		'title' => 'Grotta Gigante',
		'desc'  => 'Una delle cavità turistiche più grandi al mondo: una sola sala alta 98 metri, sul Carso alle spalle della città.',
		'lat'   => 45.7086,
		'lng'   => 13.7639,
		'tipo'  => 'grotta',
		'zoom'  => 0,
	),
);

foreach ( $poi as $p ) {
	advtr_demo_upsert(
		$p['key'],
		array(
			'post_type'    => 'poi',
			'post_status'  => 'publish',
			'post_title'   => $p['title'],
			'post_content' => $p['desc'],
			'post_author'  => get_current_user_id() ? get_current_user_id() : 1,
		),
		array(
			'advtr_lat'      => $p['lat'],
			'advtr_lng'      => $p['lng'],
			'advtr_zoom_min' => $p['zoom'],
			'advtr_tipo'     => $p['tipo'],
		),
		array( 'visitare' )
	);
}
printf( "  %d punti d'interesse\n", count( $poi ) );

/* -- Locali (attività commerciali) ------------------------------------ */
// zoom_min 14: compaiono avvicinandosi, sopra i POI.

$locali = array(
	array(
		'key'       => 'loc-san-marco',
		'title'     => 'Antico Caffè San Marco',
		'desc'      => 'Caffè letterario del 1914, tavolini di marmo e libreria interna. Frequentato da Svevo e Saba, è ancora il salotto della città.',
		'lat'       => 45.6533,
		'lng'       => 13.7745,
		'cat'       => array( 'bere' ),
		'servizi'   => array( 'Wi-Fi gratuito', 'Libreria interna', 'Colazioni', 'Aperitivo' ),
		'tel'       => '+39 040 0641724',
		'mail'      => 'info@caffesanmarco.local',
		'sito'      => 'https://example.com/caffesanmarco',
		'indirizzo' => 'Via Cesare Battisti 18, Trieste',
		'orari'     => "Lun–Gio 8:00–22:00\nVen–Sab 8:00–24:00\nDom 9:00–20:00",
		'evidenza'  => true,
		'visite'    => 34,
	),
	array(
		'key'       => 'loc-da-pepi',
		'title'     => 'Buffet da Pepi',
		'desc'      => 'Il buffet storico della città: bollito, porzina e kren dal 1897. Si mangia al bancone, in piedi, come si è sempre fatto.',
		'lat'       => 45.6503,
		'lng'       => 13.7692,
		'cat'       => array( 'mangiare' ),
		'servizi'   => array( 'Cucina tipica', 'Servizio al banco', 'Asporto' ),
		'tel'       => '+39 040 366858',
		'mail'      => 'info@dapepi.local',
		'indirizzo' => 'Via Cassa di Risparmio 3, Trieste',
		'orari'     => "Lun–Sab 8:30–22:00\nDomenica chiuso",
		'visite'    => 27,
	),
	array(
		'key'       => 'loc-bomboniera',
		'title'     => 'Pasticceria La Bomboniera',
		'desc'      => 'Pasticceria asburgica con forno a legna originale: putizza, presnitz e strudel fatti come a fine Ottocento.',
		'lat'       => 45.6512,
		'lng'       => 13.7705,
		'cat'       => array( 'mangiare' ),
		'servizi'   => array( 'Dolci tipici', 'Confezioni regalo' ),
		'tel'       => '+39 040 632752',
		'indirizzo' => 'Via Trenta Ottobre 3, Trieste',
		'orari'     => "Mar–Dom 9:00–19:30\nLunedì chiuso",
		// Nessuna visita: resta sotto soglia e mostra il badge "Novità".
		'visite'    => 0,
	),
	array(
		'key'       => 'loc-eppinger',
		'title'     => 'Eppinger Caffè',
		'desc'      => 'Torrefazione e caffetteria storica, tappa fissa per il capo in b e i dolci mitteleuropei.',
		'lat'       => 45.6497,
		'lng'       => 13.7690,
		'cat'       => array( 'bere' ),
		'servizi'   => array( 'Torrefazione propria', 'Colazioni', 'Dehors' ),
		'tel'       => '+39 040 365123',
		'indirizzo' => 'Via Dante Alighieri 2, Trieste',
		'orari'     => "Tutti i giorni 7:00–20:00",
		'visite'    => 22,
	),
	array(
		'key'       => 'loc-tommaseo',
		'title'     => 'Caffè Tommaseo',
		'desc'      => 'Il più antico caffè della città, aperto nel 1830: specchi belgi, stucchi e concerti dal vivo il fine settimana.',
		'lat'       => 45.6494,
		'lng'       => 13.7649,
		'cat'       => array( 'bere', 'mangiare' ),
		'servizi'   => array( 'Musica dal vivo', 'Sala storica', 'Pranzo' ),
		'tel'       => '+39 040 362666',
		'indirizzo' => 'Riva Tre Novembre 5, Trieste',
		'orari'     => "Lun–Dom 8:00–23:00",
		'visite'    => 19,
	),
	array(
		'key'       => 'loc-minerva',
		'title'     => 'Libreria Minerva',
		'desc'      => 'Libreria indipendente con un fondo dedicato alla letteratura triestina e mitteleuropea. Presentazioni ogni giovedì.',
		'lat'       => 45.6519,
		'lng'       => 13.7723,
		'cat'       => array( 'shopping' ),
		'servizi'   => array( 'Ordinazioni su richiesta', 'Presentazioni', 'Confezione regalo' ),
		'tel'       => '+39 040 771234',
		'indirizzo' => 'Via San Nicolò 20, Trieste',
		'orari'     => "Lun–Sab 9:00–19:30",
		'visite'    => 12,
	),
	array(
		'key'       => 'loc-osteria-marino',
		'title'     => 'Osteria da Marino',
		'desc'      => 'Osteria di pesce a due passi dal Canal Grande: sardoni in savor, sarde impanate e vini del Carso.',
		'lat'       => 45.6486,
		'lng'       => 13.7674,
		'cat'       => array( 'mangiare' ),
		'servizi'   => array( 'Cucina di pesce', 'Vini del Carso', 'Prenotazione consigliata' ),
		'tel'       => '+39 040 366596',
		'indirizzo' => 'Via del Ponte 5, Trieste',
		'orari'     => "Mar–Sab 12:00–15:00, 19:00–23:00",
		'visite'    => 25,
	),
	array(
		'key'       => 'loc-bottega-pane',
		'title'     => 'Bottega del Pane',
		'desc'      => 'Panificio a lievitazione naturale con farine del Friuli. Pane di segale, focacce e biscotti al papavero.',
		'lat'       => 45.6541,
		'lng'       => 13.7761,
		'cat'       => array( 'shopping', 'mangiare' ),
		'servizi'   => array( 'Lievito madre', 'Senza glutine su ordinazione' ),
		'tel'       => '+39 040 940211',
		'indirizzo' => 'Via Giulia 44, Trieste',
		'orari'     => "Lun–Sab 7:00–14:00",
		'visite'    => 8,
	),
	array(
		'key'       => 'loc-farmacia',
		'title'     => 'Farmacia al Redentore',
		'desc'      => 'Farmacia storica con laboratorio galenico, servizio notturno a turno e misurazioni gratuite.',
		'lat'       => 45.6472,
		'lng'       => 13.7712,
		'cat'       => array( 'servizi' ),
		'servizi'   => array( 'Laboratorio galenico', 'Autoanalisi', 'Turno notturno' ),
		'tel'       => '+39 040 302355',
		'indirizzo' => 'Via Carducci 21, Trieste',
		'orari'     => "Lun–Ven 8:30–19:30\nSab 9:00–13:00",
		'visite'    => 31,
	),
	array(
		'key'       => 'loc-barcolana-sport',
		'title'     => 'Barcolana Sport',
		'desc'      => 'Attrezzature per la vela e abbigliamento tecnico, sul lungomare di Barcola. Noleggio e riparazioni.',
		'lat'       => 45.6602,
		'lng'       => 13.7548,
		'cat'       => array( 'shopping', 'servizi' ),
		'servizi'   => array( 'Noleggio attrezzature', 'Riparazioni', 'Corsi di vela' ),
		'tel'       => '+39 040 410455',
		'indirizzo' => 'Viale Miramare 30, Trieste',
		'orari'     => "Lun–Sab 9:00–13:00, 15:30–19:30",
		'visite'    => 6,
	),
);

$locale_ids = array();
foreach ( $locali as $l ) {
	$meta = array(
		'advtr_lat'         => $l['lat'],
		'advtr_lng'         => $l['lng'],
		'advtr_zoom_min'    => 14,
		'advtr_data_inizio' => $oggi,
		'advtr_data_fine'   => $fra_uno,
		'advtr_servizi'     => $l['servizi'],
		'advtr_telefono'    => $l['tel'],
		'advtr_indirizzo'   => $l['indirizzo'],
		'advtr_orari'       => $l['orari'],
	);
	if ( isset( $l['mail'] ) ) {
		$meta['advtr_email'] = $l['mail'];
	}
	if ( isset( $l['sito'] ) ) {
		$meta['advtr_sito'] = $l['sito'];
	}
	if ( ! empty( $l['evidenza'] ) ) {
		$meta['advtr_in_evidenza']     = 1;
		$meta['advtr_evidenza_inizio'] = $oggi;
		$meta['advtr_evidenza_fine']   = $fra_uno;
	}

	// NB: non usare `$id` a livello di script — è una global di WordPress.
	$loc_id = advtr_demo_upsert(
		$l['key'],
		array(
			'post_type'    => 'locale',
			'post_status'  => 'publish',
			'post_title'   => $l['title'],
			'post_content' => $l['desc'],
			'post_author'  => $demo_user ? $demo_user : 1,
		),
		$meta,
		$l['cat']
	);

	$locale_ids[ $l['key'] ] = $loc_id;

	// Visite: alimentano il contatore reale e la soglia "Novità" (§1.6).
	// Registrate solo alla creazione, per non gonfiare a ogni ri-esecuzione.
	if ( $loc_id && $l['visite'] > 0 && 0 === (int) get_post_meta( $loc_id, 'advtr_visite_reali', true ) ) {
		for ( $i = 0; $i < $l['visite']; $i++ ) {
			Stats::record( $loc_id, 'view' );
		}
		Stats::record( $loc_id, 'map_click' );
		Stats::record( $loc_id, 'contact', 'telefono' );
	}
}
printf( "  %d locali (1 in evidenza, 1 sotto soglia \"Novità\")\n", count( $locali ) );

/* -- Offerta con coupon ----------------------------------------------- */

$offerta_id = 0;
if ( ! empty( $locale_ids['loc-san-marco'] ) ) {
	$offerta_id = advtr_demo_upsert(
		'off-san-marco',
		array(
			'post_type'    => 'offerta',
			'post_status'  => 'publish',
			'post_title'   => 'Caffè + putizza a 5 €',
			'post_content' => 'Presenta il codice al banco: un caffè e una fetta di putizza a cinque euro, tutti i giorni fino alle 11.',
			'post_author'  => $demo_user ? $demo_user : 1,
		),
		array(
			'advtr_locale_id'      => $locale_ids['loc-san-marco'],
			'advtr_data_inizio'    => $oggi,
			'advtr_data_scadenza'  => wp_date( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ),
			'advtr_tipo_coupon'    => 'codice',
			'advtr_codice'         => 'SANMARCO5',
		)
	);
	echo "  1 offerta (codice coupon: SANMARCO5)\n";
}

/* -- Punti QR (riservati) --------------------------------------------- */

$punti_qr = array(
	array( 'key' => 'qr-unita', 'title' => 'Espositore Piazza Unità', 'lat' => 45.6495, 'lng' => 13.7683 ),
	array( 'key' => 'qr-stazione', 'title' => 'Totem Stazione Centrale', 'lat' => 45.6573, 'lng' => 13.7663 ),
	array( 'key' => 'qr-barcola', 'title' => 'Bacheca Lungomare Barcola', 'lat' => 45.6650, 'lng' => 13.7480 ),
);
foreach ( $punti_qr as $q ) {
	advtr_demo_upsert(
		$q['key'],
		array(
			'post_type'   => 'punto_qr',
			'post_status' => 'publish',
			'post_title'  => $q['title'],
			'post_author' => get_current_user_id() ? get_current_user_id() : 1,
		),
		array(
			'advtr_lat'   => $q['lat'],
			'advtr_lng'   => $q['lng'],
			'advtr_stato' => 'attivo',
		)
	);
}
printf( "  %d punti QR (visibili solo in area riservata)\n", count( $punti_qr ) );

/* -- Pagine ------------------------------------------------------------ */

$pagine = array(
	array(
		'key'     => 'page-mappa',
		'title'   => 'Mappa',
		'slug'    => 'mappa',
		'content' => '[advtr_map zoom="14" height="600"]',
	),
	array(
		'key'     => 'page-offerte',
		'title'   => 'Offerte',
		'slug'    => 'offerte',
		'content' => '[advtr_offerte]',
	),
	array(
		'key'     => 'page-eventi',
		'title'   => 'Eventi',
		'slug'    => 'eventi-advtr',
		'content' => "[advtr_grandi_eventi]\n\n[advtr_eventi]",
	),
	array(
		'key'     => 'page-area',
		'title'   => 'Area clienti',
		'slug'    => 'area-clienti',
		'content' => '[advtr_area_riservata]',
	),
	array(
		'key'     => 'page-stats',
		'title'   => 'Statistiche',
		'slug'    => 'statistiche',
		'content' => '[advtr_statistiche]',
	),
	array(
		'key'     => 'page-valida',
		'title'   => 'Valida coupon',
		'slug'    => 'valida-coupon',
		'content' => '[advtr_valida_coupon]',
	),
);

$page_ids = array();
foreach ( $pagine as $pg ) {
	$page_ids[ $pg['key'] ] = advtr_demo_upsert(
		$pg['key'],
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $pg['title'],
			'post_name'    => $pg['slug'],
			'post_content' => $pg['content'],
		)
	);
}

// La home usa l'ingresso guidato e rimanda alla pagina mappa.
$mappa_url = $page_ids['page-mappa'] ? get_permalink( $page_ids['page-mappa'] ) : home_url( '/' );
$home_id   = advtr_demo_upsert(
	'page-home',
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'AdverTrieste',
		'post_name'    => 'advertrieste-home',
		'post_content' => sprintf(
			"[advtr_onboarding map=\"%s\" titolo=\"Cosa stai cercando a Trieste?\"]\n\n[advtr_map zoom=\"13\" height=\"600\"]",
			esc_url( $mappa_url )
		),
	)
);
printf( "  %d pagine\n", count( $pagine ) + 1 );

/* -- Pagina iniziale --------------------------------------------------- */

if ( $home_id && ! get_option( ADVTR_DEMO_BACKUP ) ) {
	// Salva l'impostazione attuale così `pulisci` può ripristinarla.
	update_option(
		ADVTR_DEMO_BACKUP,
		array(
			'show_on_front' => get_option( 'show_on_front' ),
			'page_on_front' => get_option( 'page_on_front' ),
		)
	);
	printf(
		"  pagina iniziale precedente salvata (show_on_front=%s, page_on_front=%s)\n",
		get_option( 'show_on_front' ),
		get_option( 'page_on_front' )
	);
}
if ( $home_id ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home_id );
}

/* -- Chiusura ---------------------------------------------------------- */

delete_transient( EventiRest::CACHE_LOCALI_IN_EVENTO );
flush_rewrite_rules();

$scheda = ! empty( $locale_ids['loc-san-marco'] ) ? get_permalink( $locale_ids['loc-san-marco'] ) : '';

echo "\n----------------------------------------\n";
echo "Pronto. Da visitare:\n";
printf( "  Home (mappa + filtri)  %s\n", home_url( '/' ) );
printf( "  Mappa                  %s\n", get_permalink( $page_ids['page-mappa'] ) );
printf( "  Scheda di un locale    %s\n", $scheda );
printf( "  Offerte                %s\n", get_permalink( $page_ids['page-offerte'] ) );
printf( "  Area clienti           %s\n", get_permalink( $page_ids['page-area'] ) );
printf( "  Statistiche            %s\n", get_permalink( $page_ids['page-stats'] ) );
printf( "  Valida coupon          %s\n", get_permalink( $page_ids['page-valida'] ) );
printf( "  Eventi                 %s\n", get_permalink( $page_ids['page-eventi'] ) );
printf( "\n  Login cliente: %s / %s  (%s)\n", ADVTR_DEMO_USER, ADVTR_DEMO_PASS, wp_login_url() );
echo "\nPer rimuovere tutto:\n  wp eval-file wp-content/plugins/advertrieste/tools/seed-demo.php pulisci\n";
