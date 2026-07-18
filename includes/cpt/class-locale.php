<?php
/**
 * Custom Post Type `locale` — attività commerciale.
 *
 * Pubblico, visibile sulla mappa a zoom alto. In questa fase (scaffold) definisce
 * solo gli argomenti di registrazione: meta box e campi verranno aggiunti dopo.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Cpt;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrazione del CPT `locale`.
 */
class Locale {

	/**
	 * Slug del post type.
	 *
	 * @var string
	 */
	const POST_TYPE = 'locale';

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
	 * @return array<string,mixed>
	 */
	private static function args() {
		$labels = array(
			'name'               => __( 'Locali', 'advertrieste' ),
			'singular_name'      => __( 'Locale', 'advertrieste' ),
			'menu_name'          => __( 'Locali', 'advertrieste' ),
			'add_new'            => __( 'Aggiungi nuovo', 'advertrieste' ),
			'add_new_item'       => __( 'Aggiungi nuovo locale', 'advertrieste' ),
			'edit_item'          => __( 'Modifica locale', 'advertrieste' ),
			'new_item'           => __( 'Nuovo locale', 'advertrieste' ),
			'view_item'          => __( 'Vedi locale', 'advertrieste' ),
			'search_items'       => __( 'Cerca locali', 'advertrieste' ),
			'not_found'          => __( 'Nessun locale trovato', 'advertrieste' ),
			'not_found_in_trash' => __( 'Nessun locale nel cestino', 'advertrieste' ),
			'all_items'          => __( 'Tutti i locali', 'advertrieste' ),
		);

		return array(
			'labels'              => $labels,
			'public'              => true,
			'show_in_rest'        => true,
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_icon'           => 'dashicons-store',
			'menu_position'       => 20,
			// 'custom-fields' abilita il container `meta` in REST: così i meta
			// registrati con show_in_rest=true sono esposti, quelli riservati no.
			// 'author' abilita la gestione per proprietario (cliente_locale).
			'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'author' ),
			'rewrite'             => array( 'slug' => 'locale' ),
			'exclude_from_search' => false,
			// Capability custom: il cliente gestisce solo le proprie schede.
			'capability_type'     => array( 'advtr_locale', 'advtr_locali' ),
			'map_meta_cap'        => true,
		);
	}
}
