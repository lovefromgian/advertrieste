<?php
/**
 * Attivazione / disattivazione del plugin.
 *
 * All'attivazione registra CPT e tassonomie (necessario perché i rewrite e il
 * seeding dei termini funzionino subito), inserisce i termini di default della
 * tassonomia `categoria` e rigenera le regole di rewrite. Alla disattivazione
 * ripulisce le regole di rewrite.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste;

use AdverTrieste\Cpt\Categoria;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routine di attivazione e disattivazione.
 */
class Activator {

	/**
	 * Eseguita all'attivazione del plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		// I CPT/tassonomie non sono ancora registrati (init non è passato):
		// li registriamo qui così rewrite e seeding sono coerenti.
		Plugin::instance()->register_post_types();
		Plugin::instance()->register_taxonomies();

		Categoria::seed_terms();

		flush_rewrite_rules();
	}

	/**
	 * Eseguita alla disattivazione del plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
