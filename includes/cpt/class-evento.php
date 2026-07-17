<?php
/**
 * Custom Post Type `evento` — grande evento cittadino o evento di organizzatore terzo.
 *
 * Pubblico solo quando `pubblicato` (lo stato è gestito da un workflow di revisione
 * che verrà implementato in seguito: bozza → in_revisione → pubblicato). In questa
 * fase (scaffold) definisce solo gli argomenti di registrazione.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Cpt;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrazione del CPT `evento`.
 */
class Evento {

	/**
	 * Slug del post type.
	 *
	 * @var string
	 */
	const POST_TYPE = 'evento';

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
			'name'               => __( 'Eventi', 'advertrieste' ),
			'singular_name'      => __( 'Evento', 'advertrieste' ),
			'menu_name'          => __( 'Eventi', 'advertrieste' ),
			'add_new'            => __( 'Aggiungi nuovo', 'advertrieste' ),
			'add_new_item'       => __( 'Aggiungi nuovo evento', 'advertrieste' ),
			'edit_item'          => __( 'Modifica evento', 'advertrieste' ),
			'new_item'           => __( 'Nuovo evento', 'advertrieste' ),
			'view_item'          => __( 'Vedi evento', 'advertrieste' ),
			'search_items'       => __( 'Cerca eventi', 'advertrieste' ),
			'not_found'          => __( 'Nessun evento trovato', 'advertrieste' ),
			'not_found_in_trash' => __( 'Nessun evento nel cestino', 'advertrieste' ),
			'all_items'          => __( 'Tutti gli eventi', 'advertrieste' ),
		);

		return array(
			'labels'              => $labels,
			'public'              => true,
			'show_in_rest'        => true,
			'has_archive'         => true,
			'hierarchical'        => false,
			'menu_icon'           => 'dashicons-calendar-alt',
			'menu_position'       => 22,
			'supports'            => array( 'title', 'editor', 'thumbnail' ),
			'rewrite'             => array( 'slug' => 'eventi' ),
			'exclude_from_search' => false,
		);
	}
}
