<?php
/**
 * Custom Post Type `evento` — grande evento cittadino o evento di organizzatore terzo.
 *
 * !!! IMPORTANTE — modello a doppia versione !!!
 * Il post WP è SEMPRE la versione "in lavorazione" (ciò che l'organizzatore
 * modifica). Il pubblico non vede mai il post direttamente: vede solo lo snapshot
 * `versione_pubblica` (l'ultima versione APPROVATA), servito da `Rest\Eventi` e
 * dagli shortcode. Per questo il CPT è NON pubblico e NON esposto in REST core:
 * evita che le modifiche non ancora approvate finiscano online.
 * Workflow: bozza → in_revisione → pubblicato (vedi Evento\Workflow).
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
			// Controllato: gestibile in bacheca ma non esposto direttamente al
			// pubblico né alla REST core (il pubblico vede solo versione_pubblica).
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_icon'           => 'dashicons-calendar-alt',
			'menu_position'       => 22,
			// 'author' abilita la gestione per proprietario (organizzatore_evento).
			'supports'            => array( 'title', 'editor', 'thumbnail', 'author' ),
			'rewrite'             => false,
			'query_var'           => false,
			// Capability custom: l'organizzatore gestisce solo i propri eventi.
			'capability_type'     => array( 'advtr_evento', 'advtr_eventi' ),
			'map_meta_cap'        => true,
		);
	}
}
