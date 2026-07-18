<?php
/**
 * Custom Post Type `poi` — punto d'interesse non commerciale (museo, castello…).
 *
 * Pubblico, visibile sulla mappa a zoom basso. In questa fase (scaffold) definisce
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
 * Registrazione del CPT `poi`.
 */
class Poi {

	/**
	 * Slug del post type.
	 *
	 * @var string
	 */
	const POST_TYPE = 'poi';

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
			'name'               => __( 'Punti d\'interesse', 'advertrieste' ),
			'singular_name'      => __( 'Punto d\'interesse', 'advertrieste' ),
			'menu_name'          => __( 'Punti d\'interesse', 'advertrieste' ),
			'add_new'            => __( 'Aggiungi nuovo', 'advertrieste' ),
			'add_new_item'       => __( 'Aggiungi nuovo punto d\'interesse', 'advertrieste' ),
			'edit_item'          => __( 'Modifica punto d\'interesse', 'advertrieste' ),
			'new_item'           => __( 'Nuovo punto d\'interesse', 'advertrieste' ),
			'view_item'          => __( 'Vedi punto d\'interesse', 'advertrieste' ),
			'search_items'       => __( 'Cerca punti d\'interesse', 'advertrieste' ),
			'not_found'          => __( 'Nessun punto d\'interesse trovato', 'advertrieste' ),
			'not_found_in_trash' => __( 'Nessun punto d\'interesse nel cestino', 'advertrieste' ),
			'all_items'          => __( 'Tutti i punti d\'interesse', 'advertrieste' ),
		);

		return array(
			'labels'              => $labels,
			'public'              => true,
			'show_in_rest'        => true,
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_icon'           => 'dashicons-location',
			'menu_position'       => 21,
			// 'custom-fields' abilita il container `meta` in REST (coordinate, zoom).
			'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'rewrite'             => array( 'slug' => 'poi' ),
			'exclude_from_search' => false,
		);
	}
}
