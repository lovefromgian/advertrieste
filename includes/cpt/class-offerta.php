<?php
/**
 * Custom Post Type `offerta` — promozione a tempo collegata a un `locale`.
 *
 * Pubblica (mostrata sul front-end con countdown fino alla scadenza). Il codice
 * coupon associato è validabile dall'esercente sul posto (vedi Rest\Offerte).
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Cpt;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrazione del CPT `offerta`.
 */
class Offerta {

	/**
	 * Slug del post type.
	 *
	 * @var string
	 */
	const POST_TYPE = 'offerta';

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
			'name'               => __( 'Offerte', 'advertrieste' ),
			'singular_name'      => __( 'Offerta', 'advertrieste' ),
			'menu_name'          => __( 'Offerte', 'advertrieste' ),
			'add_new'            => __( 'Aggiungi nuova', 'advertrieste' ),
			'add_new_item'       => __( 'Aggiungi nuova offerta', 'advertrieste' ),
			'edit_item'          => __( 'Modifica offerta', 'advertrieste' ),
			'new_item'           => __( 'Nuova offerta', 'advertrieste' ),
			'view_item'          => __( 'Vedi offerta', 'advertrieste' ),
			'search_items'       => __( 'Cerca offerte', 'advertrieste' ),
			'not_found'          => __( 'Nessuna offerta trovata', 'advertrieste' ),
			'not_found_in_trash' => __( 'Nessuna offerta nel cestino', 'advertrieste' ),
			'all_items'          => __( 'Tutte le offerte', 'advertrieste' ),
		);

		return array(
			'labels'              => $labels,
			'public'              => true,
			'show_in_rest'        => true,
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_icon'           => 'dashicons-tag',
			'menu_position'       => 24,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'rewrite'             => array( 'slug' => 'offerta' ),
			'exclude_from_search' => false,
		);
	}
}
