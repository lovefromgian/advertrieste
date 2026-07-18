<?php
/**
 * Scheda attività completa: pagina singola del CPT `locale` (§1.3).
 *
 * Sostituisce il template single del tema con un layout ricco (logo, descrizione,
 * servizi, galleria, contatti, orari, mini-mappa). Registra la visita nelle
 * statistiche al caricamento (chiude il tracking del contatore visite, §1.6) e
 * traccia i click sui contatti.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Frontend;

use AdverTrieste\Cpt\Locale;
use AdverTrieste\Rest\Markers;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template e asset della scheda locale.
 */
class Scheda {

	/**
	 * Handle asset.
	 *
	 * @var string
	 */
	const HANDLE = 'advtr-scheda';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Usa il template del plugin per la scheda singola del locale.
	 *
	 * @param string $template Percorso template scelto dal tema.
	 * @return string
	 */
	public static function template( $template ) {
		if ( is_singular( Locale::POST_TYPE ) ) {
			return ADVTR_PATH . 'templates/single-locale.php';
		}
		return $template;
	}

	/**
	 * Accoda gli asset e la configurazione solo sulla scheda del locale.
	 *
	 * @return void
	 */
	public static function assets() {
		if ( ! is_singular( Locale::POST_TYPE ) ) {
			return;
		}
		$post_id = get_queried_object_id();

		// Leaflet + stile marker riusati dalla mappa (registrati da Frontend\Map).
		wp_enqueue_style( 'leaflet' );
		wp_enqueue_script( 'leaflet' );
		wp_enqueue_style( 'advtr-map' );

		wp_enqueue_style( self::HANDLE, ADVTR_URL . 'assets/src/scheda/scheda.css', array(), ADVTR_VERSION );
		wp_enqueue_script( self::HANDLE, ADVTR_URL . 'assets/src/scheda/scheda.js', array( 'leaflet' ), ADVTR_VERSION, true );

		$lat = (float) get_post_meta( $post_id, 'advtr_lat', true );
		$lng = (float) get_post_meta( $post_id, 'advtr_lng', true );

		wp_localize_script(
			self::HANDLE,
			'advtrScheda',
			array(
				'id'        => $post_id,
				'trackBase' => rest_url( Markers::NAMESPACE . '/locale/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'lat'       => $lat,
				'lng'       => $lng,
			)
		);
	}
}
