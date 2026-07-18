<?php
/**
 * Endpoint REST `POST advertrieste/v1/locale/{id}/track`.
 *
 * Registra un evento statistico pubblico (view/map_click/coupon/contact) per una
 * scheda `locale`. Protezioni: nonce REST + rate-limit per IP/scheda/tipo, così
 * da evitare conteggi gonfiati (specifiche §1.6). Valida che la scheda esista,
 * sia un `locale` pubblicato e che il tipo sia ammesso.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Rest;

use AdverTrieste\Cpt\Locale;
use AdverTrieste\Stats\Stats;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller REST per la registrazione degli eventi statistici.
 */
class Track {

	/**
	 * Finestra di rate-limit in secondi (per IP + scheda + tipo).
	 *
	 * @var int
	 */
	const RATE_LIMIT_SECONDS = 60;

	/**
	 * Aggancia la registrazione della route.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registra la route.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			Markers::NAMESPACE,
			'/locale/(?P<id>\d+)/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'track' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'args'                => array(
					'id'   => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'tipo' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => Stats::TIPI,
						'sanitize_callback' => 'sanitize_key',
					),
					'meta' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Permission: richiede un nonce REST valido (anti-CSRF di base).
	 *
	 * L'endpoint è pubblico (traccia anche utenti anonimi) ma il nonce evita
	 * invii da origini esterne; il rate-limit nel callback evita l'inflazione.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return true|WP_Error
	 */
	public static function permission( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'advtr_track_bad_nonce',
				__( 'Nonce non valido.', 'advertrieste' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Registra l'evento se la scheda è valida e il rate-limit lo consente.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function track( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$tipo    = $request->get_param( 'tipo' );
		$meta    = (string) $request->get_param( 'meta' );

		$post = get_post( $post_id );
		if ( ! $post || Locale::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error(
				'advtr_track_not_found',
				__( 'Scheda non valida.', 'advertrieste' ),
				array( 'status' => 404 )
			);
		}

		// Rate-limit per IP + scheda + tipo: silenziosamente "ok" ma non registra.
		$key = 'advtr_rl_' . md5( self::client_ip() . "|{$post_id}|{$tipo}" );
		if ( get_transient( $key ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => true,
					'counted' => false,
				),
				200
			);
		}
		set_transient( $key, 1, self::RATE_LIMIT_SECONDS );

		$counted = Stats::record( $post_id, $tipo, $meta );

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'counted' => (bool) $counted,
			),
			200
		);
	}

	/**
	 * IP del client (per il rate-limit). Non persistito: usato solo in hash.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return '' === $ip ? '0.0.0.0' : $ip;
	}
}
