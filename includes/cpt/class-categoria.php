<?php
/**
 * Tassonomia `categoria` — condivisa tra `locale` e `poi`.
 *
 * Termini per "intenzione" del turista (mangiare, bere, visitare, shopping,
 * servizi). Gerarchica per avere l'interfaccia a checkbox in bacheca e poter
 * eventualmente aggiungere sottocategorie in futuro.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Cpt;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrazione e seeding della tassonomia `categoria`.
 */
class Categoria {

	/**
	 * Slug della tassonomia.
	 *
	 * @var string
	 */
	const TAXONOMY = 'categoria';

	/**
	 * Post type a cui la tassonomia è collegata.
	 *
	 * @var string[]
	 */
	const OBJECT_TYPES = array( Locale::POST_TYPE, Poi::POST_TYPE );

	/**
	 * Termini d'intenzione di default: slug => etichetta.
	 *
	 * @var array<string,string>
	 */
	const DEFAULT_TERMS = array(
		'mangiare' => 'Mangiare',
		'bere'     => 'Bere',
		'visitare' => 'Visitare',
		'shopping' => 'Shopping',
		'servizi'  => 'Servizi',
	);

	/**
	 * Registra la tassonomia in WordPress.
	 *
	 * @return void
	 */
	public static function register() {
		register_taxonomy( self::TAXONOMY, self::OBJECT_TYPES, self::args() );
	}

	/**
	 * Argomenti di registrazione della tassonomia.
	 *
	 * @return array<string,mixed>
	 */
	private static function args() {
		$labels = array(
			'name'          => __( 'Categorie', 'advertrieste' ),
			'singular_name' => __( 'Categoria', 'advertrieste' ),
			'menu_name'     => __( 'Categorie', 'advertrieste' ),
			'all_items'     => __( 'Tutte le categorie', 'advertrieste' ),
			'edit_item'     => __( 'Modifica categoria', 'advertrieste' ),
			'view_item'     => __( 'Vedi categoria', 'advertrieste' ),
			'update_item'   => __( 'Aggiorna categoria', 'advertrieste' ),
			'add_new_item'  => __( 'Aggiungi nuova categoria', 'advertrieste' ),
			'new_item_name' => __( 'Nome nuova categoria', 'advertrieste' ),
			'search_items'  => __( 'Cerca categorie', 'advertrieste' ),
			'not_found'     => __( 'Nessuna categoria trovata', 'advertrieste' ),
		);

		return array(
			'labels'            => $labels,
			'public'            => true,
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'show_ui'           => true,
			'rewrite'           => array( 'slug' => 'categoria' ),
		);
	}

	/**
	 * Inserisce i termini di default se non presenti (idempotente).
	 *
	 * Da chiamare all'attivazione del plugin, quando la tassonomia è già
	 * registrata.
	 *
	 * @return void
	 */
	public static function seed_terms() {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return;
		}

		foreach ( self::DEFAULT_TERMS as $slug => $label ) {
			if ( term_exists( $slug, self::TAXONOMY ) ) {
				continue;
			}
			wp_insert_term( $label, self::TAXONOMY, array( 'slug' => $slug ) );
		}
	}
}
