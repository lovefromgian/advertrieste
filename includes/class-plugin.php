<?php
/**
 * Classe principale del plugin: orchestra il bootstrap dei componenti.
 *
 * In questa fase (scaffold) registra soltanto i Custom Post Type e il text
 * domain. La logica di business (meta, REST, cron, access) verrà agganciata qui
 * nelle sessioni successive.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste;

use AdverTrieste\Cpt\Locale;
use AdverTrieste\Cpt\Poi;
use AdverTrieste\Cpt\Evento;
use AdverTrieste\Cpt\PuntoQr;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton di avvio del plugin.
 */
final class Plugin {

	/**
	 * Istanza singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Elenco delle classi CPT da registrare.
	 *
	 * @var string[]
	 */
	private const POST_TYPES = array(
		Locale::class,
		Poi::class,
		Evento::class,
		PuntoQr::class,
	);

	/**
	 * Costruttore privato: usare instance().
	 */
	private function __construct() {}

	/**
	 * Restituisce l'istanza singleton del plugin.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Aggancia gli hook di WordPress.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * Registra tutti i Custom Post Type del plugin.
	 *
	 * @return void
	 */
	public function register_post_types() {
		foreach ( self::POST_TYPES as $cpt ) {
			$cpt::register();
		}
	}
}
