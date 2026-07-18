<?php
/**
 * Front-end mappa: shortcode `[advtr_map]` e caricamento asset.
 *
 * Registra Leaflet (bundle locale, niente CDN) e lo script della mappa, che
 * consuma l'endpoint `advertrieste/v1/map/markers`. Gli asset vengono caricati
 * solo quando lo shortcode è presente nella pagina.
 *
 * Attributi shortcode:
 *   lat, lng  — centro iniziale (default: Trieste)
 *   zoom      — zoom iniziale (default: 13)
 *   height    — altezza del contenitore in px (default: 500)
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Frontend;

use AdverTrieste\Cpt\Categoria;
use AdverTrieste\Rest\Markers;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestione dello shortcode e degli asset della mappa pubblica.
 */
class Map {

	/**
	 * Handle degli script/style.
	 *
	 * @var string
	 */
	const HANDLE = 'advtr-map';

	/**
	 * Centro di default: Trieste.
	 *
	 * @var array<string,float>
	 */
	const DEFAULT_CENTER = array(
		'lat' => 45.6495,
		'lng' => 13.7768,
	);

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'advtr_map', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Registra (senza accodare) gli asset: Leaflet + mappa.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style(
			'leaflet',
			ADVTR_URL . 'assets/vendor/leaflet/leaflet.css',
			array(),
			'1.9.4'
		);
		wp_register_script(
			'leaflet',
			ADVTR_URL . 'assets/vendor/leaflet/leaflet.js',
			array(),
			'1.9.4',
			true
		);

		wp_register_style(
			self::HANDLE,
			ADVTR_URL . 'assets/src/map/map.css',
			array( 'leaflet' ),
			ADVTR_VERSION
		);
		wp_register_script(
			self::HANDLE,
			ADVTR_URL . 'assets/src/map/map.js',
			array( 'leaflet' ),
			ADVTR_VERSION,
			true
		);
	}

	/**
	 * Rende lo shortcode `[advtr_map]`.
	 *
	 * @param array<string,mixed> $atts Attributi dello shortcode.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'lat'    => self::DEFAULT_CENTER['lat'],
				'lng'    => self::DEFAULT_CENTER['lng'],
				'zoom'   => 13,
				'height' => 500,
			),
			$atts,
			'advtr_map'
		);

		wp_enqueue_style( 'leaflet' );
		wp_enqueue_script( 'leaflet' );
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );

		wp_localize_script(
			self::HANDLE,
			'advtrMap',
			array(
				'endpoint'  => rest_url( Markers::NAMESPACE . '/map/markers' ),
				'trackBase' => rest_url( Markers::NAMESPACE . '/locale/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'center'    => array( (float) $atts['lat'], (float) $atts['lng'] ),
				'zoom'      => (int) $atts['zoom'],
				'categorie' => self::categorie_list(),
				'i18n'      => array(
					'tutte'       => __( 'Tutte', 'advertrieste' ),
					'apri'        => __( 'Apri scheda', 'advertrieste' ),
					'caricamento' => __( 'Caricamento…', 'advertrieste' ),
					'novita'      => __( 'Novità', 'advertrieste' ),
					'indicazioni' => __( 'Indicazioni', 'advertrieste' ),
					'inEvento'    => __( 'Evento in corso', 'advertrieste' ),
				),
			)
		);

		$dom_id = 'advtr-map-' . wp_unique_id();
		$height = (int) $atts['height'];

		ob_start();
		require ADVTR_PATH . 'templates/map.php';
		return (string) ob_get_clean();
	}

	/**
	 * Elenco delle categorie (slug => nome) per il filtro in mappa.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function categorie_list() {
		$terms = get_terms(
			array(
				'taxonomy'   => Categoria::TAXONOMY,
				'hide_empty' => false,
			)
		);

		$out = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$out[] = array(
					'slug' => $term->slug,
					'name' => $term->name,
				);
			}
		}
		return $out;
	}
}
