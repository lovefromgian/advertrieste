<?php
/**
 * Custom Post Type `punto_qr` — posizione fisica di espositore / QR code.
 *
 * !!! DATI RISERVATI — CRITICO PER LA SICUREZZA !!!
 * Questo post type NON deve MAI essere pubblico. Le coordinate dei punti QR sono
 * visibili solo agli utenti autenticati (ruolo cliente/admin) tramite l'area
 * riservata e un endpoint REST protetto da `permission_callback`.
 *   - `public`              => false  (niente front-end pubblico, niente query pubbliche)
 *   - `show_in_rest`        => false  (niente esposizione via REST core del CPT)
 *   - `publicly_queryable`  => false
 *   - `exclude_from_search` => true
 * Non affidarsi a "nascondere il link" o al CSS: il filtro è lato server.
 * L'accesso in area riservata verrà gestito da un endpoint REST dedicato con
 * verifica di capability (vedi docs/architettura.md §3, endpoint `/qr-map`).
 *
 * In questa fase (scaffold) definisce solo gli argomenti di registrazione.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Cpt;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrazione del CPT `punto_qr` (riservato, non pubblico).
 */
class PuntoQr {

	/**
	 * Slug del post type.
	 *
	 * @var string
	 */
	const POST_TYPE = 'punto_qr';

	/**
	 * Registra il post type in WordPress.
	 *
	 * @return void
	 */
	public static function register() {
		register_post_type( self::POST_TYPE, self::args() );
	}

	/**
	 * Argomenti di registrazione del post type.
	 *
	 * Impostazioni deliberatamente restrittive: gestibile solo dall'admin/area
	 * riservata, mai esposto al pubblico o alla REST API core.
	 *
	 * @return array<string,mixed>
	 */
	private static function args() {
		$labels = array(
			'name'               => __( 'Punti QR', 'advertrieste' ),
			'singular_name'      => __( 'Punto QR', 'advertrieste' ),
			'menu_name'          => __( 'Punti QR', 'advertrieste' ),
			'add_new'            => __( 'Aggiungi nuovo', 'advertrieste' ),
			'add_new_item'       => __( 'Aggiungi nuovo punto QR', 'advertrieste' ),
			'edit_item'          => __( 'Modifica punto QR', 'advertrieste' ),
			'new_item'           => __( 'Nuovo punto QR', 'advertrieste' ),
			'view_item'          => __( 'Vedi punto QR', 'advertrieste' ),
			'search_items'       => __( 'Cerca punti QR', 'advertrieste' ),
			'not_found'          => __( 'Nessun punto QR trovato', 'advertrieste' ),
			'not_found_in_trash' => __( 'Nessun punto QR nel cestino', 'advertrieste' ),
			'all_items'          => __( 'Tutti i punti QR', 'advertrieste' ),
		);

		return array(
			'labels'              => $labels,
			'public'              => false,
			'show_in_rest'        => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'hierarchical'        => false,
			// Gestibile in bacheca dall'admin, ma non esposto al pubblico.
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'menu_icon'           => 'dashicons-privacy',
			'menu_position'       => 23,
			'supports'            => array( 'title' ),
			'rewrite'             => false,
			'query_var'           => false,
		);
	}
}
