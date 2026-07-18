<?php
/**
 * Endpoint REST `GET advertrieste/v1/qr-map` — mappa dei punti QR (RISERVATA).
 *
 * !!! SICUREZZA — CRITICO !!!
 * Le coordinate dei `punto_qr` sono dati riservati: questo endpoint le serve
 * SOLO a utenti autenticati con capability `advtr_view_qr_map`. Il controllo è
 * lato server nel `permission_callback` (non ci si affida a nascondere il link
 * o a CSS). L'endpoint pubblico dei marker (`/map/markers`) non tocca mai i
 * punti QR; questo è l'unico punto in cui le loro coordinate lasciano il server.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Rest;

use AdverTrieste\Cpt\PuntoQr;
use AdverTrieste\Access\Access;
use WP_REST_Response;
use WP_Query;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller REST della mappa dei punti QR riservata.
 */
class QrMap {

	/**
	 * Numero massimo di punti restituiti.
	 *
	 * @var int
	 */
	const MAX_POINTS = 2000;

	/**
	 * Aggancia la registrazione della route.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registra la route riservata.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			Markers::NAMESPACE,
			'/qr-map',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_points' ),
				// Accesso riservato: autenticato + capability dedicata.
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);
	}

	/**
	 * Controllo di accesso: solo utenti con capability `advtr_view_qr_map`.
	 *
	 * @return true|\WP_Error
	 */
	public static function permission() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'advtr_qr_unauthenticated',
				__( 'Devi effettuare l\'accesso per vedere la mappa dei punti QR.', 'advertrieste' ),
				array( 'status' => 401 )
			);
		}
		if ( ! Access::can_view_qr_map() ) {
			return new \WP_Error(
				'advtr_qr_forbidden',
				__( 'Non hai i permessi per vedere la mappa dei punti QR.', 'advertrieste' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Restituisce i punti QR con coordinate (solo se autorizzato).
	 *
	 * @return WP_REST_Response
	 */
	public static function get_points() {
		// Difesa in profondità: ricontrolla anche qui, oltre al permission_callback.
		if ( ! Access::can_view_qr_map() ) {
			return new WP_REST_Response( array(), 403 );
		}

		$query = new WP_Query(
			array(
				'post_type'              => PuntoQr::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => self::MAX_POINTS,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'advtr_lat',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$points = array();
		foreach ( $query->posts as $post ) {
			$lat = get_post_meta( $post->ID, 'advtr_lat', true );
			$lng = get_post_meta( $post->ID, 'advtr_lng', true );
			if ( '' === $lat || '' === $lng ) {
				continue;
			}
			$points[] = array(
				'id'        => $post->ID,
				'lat'       => (float) $lat,
				'lng'       => (float) $lng,
				'etichetta' => get_the_title( $post ),
				'stato'     => (string) get_post_meta( $post->ID, 'advtr_stato', true ),
			);
		}

		return new WP_REST_Response( $points, 200 );
	}
}
