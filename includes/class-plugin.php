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
use AdverTrieste\Cpt\Categoria;
use AdverTrieste\Cpt\Offerta;
use AdverTrieste\Meta\LocaleMeta;
use AdverTrieste\Meta\PuntoQrMeta;
use AdverTrieste\Meta\OffertaMeta;
use AdverTrieste\Meta\EventoMeta;
use AdverTrieste\Rest\Markers;
use AdverTrieste\Rest\QrMap;
use AdverTrieste\Rest\Track;
use AdverTrieste\Rest\Stats as StatsEndpoint;
use AdverTrieste\Rest\Offerte as OfferteEndpoint;
use AdverTrieste\Rest\Eventi as EventiEndpoint;
use AdverTrieste\Frontend\Map;
use AdverTrieste\Frontend\Scheda;
use AdverTrieste\Frontend\ReservedArea;
use AdverTrieste\Frontend\StatsDashboard;
use AdverTrieste\Frontend\Offerte as OfferteView;
use AdverTrieste\Frontend\Eventi as EventiView;
use AdverTrieste\Cron\Cron;

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
		Offerta::class,
	);

	/**
	 * Elenco delle classi tassonomia da registrare.
	 *
	 * @var string[]
	 */
	private const TAXONOMIES = array(
		Categoria::class,
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
		add_action( 'init', array( $this, 'register_taxonomies' ) );

		LocaleMeta::init();
		PuntoQrMeta::init();
		OffertaMeta::init();
		EventoMeta::init();
		Markers::init();
		QrMap::init();
		Track::init();
		StatsEndpoint::init();
		OfferteEndpoint::init();
		EventiEndpoint::init();
		Map::init();
		Scheda::init();
		ReservedArea::init();
		StatsDashboard::init();
		OfferteView::init();
		EventiView::init();
		Cron::init();
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

	/**
	 * Registra tutte le tassonomie del plugin.
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		foreach ( self::TAXONOMIES as $taxonomy ) {
			$taxonomy::register();
		}
	}
}
