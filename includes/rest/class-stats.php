<?php
/**
 * Endpoint REST `GET advertrieste/v1/stats/{post_id}` — dati dashboard scheda.
 *
 * Riservato al proprietario della scheda (autore) o all'amministratore. Ritorna
 * i totali per tipo, la serie giornaliera delle visite e lo stato soglia (§1.6).
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Rest;

use AdverTrieste\Cpt\Locale;
use AdverTrieste\Stats\Stats as StatsModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller REST della dashboard statistiche.
 */
class Stats {

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
			'/stats/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_stats' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'args'                => array(
					'id'   => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'days' => array(
						'required'          => false,
						'default'           => 30,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission: proprietario della scheda o amministratore.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return true|WP_Error
	 */
	public static function permission( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'advtr_stats_unauthenticated',
				__( 'Accesso non consentito.', 'advertrieste' ),
				array( 'status' => 401 )
			);
		}

		$post = get_post( absint( $request->get_param( 'id' ) ) );
		if ( ! $post || Locale::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'advtr_stats_not_found',
				__( 'Scheda non trovata.', 'advertrieste' ),
				array( 'status' => 404 )
			);
		}

		if ( ! self::can_view( $post ) ) {
			return new WP_Error(
				'advtr_stats_forbidden',
				__( 'Non hai i permessi per queste statistiche.', 'advertrieste' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * L'utente corrente può vedere le statistiche di questa scheda?
	 *
	 * @param \WP_Post $post Scheda.
	 * @return bool
	 */
	private static function can_view( $post ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return get_current_user_id() === (int) $post->post_author;
	}

	/**
	 * Restituisce i dati della dashboard.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response
	 */
	public static function get_stats( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$days    = absint( $request->get_param( 'days' ) );
		$days    = $days > 0 ? $days : 30;

		return new WP_REST_Response(
			array(
				'post_id'          => $post_id,
				'titolo'           => get_the_title( $post_id ),
				'totali'           => StatsModel::totals_by_type( $post_id ),
				'serie_visite'     => StatsModel::daily_series( $post_id, 'view', $days ),
				'visite_reali'     => StatsModel::visite_reali( $post_id ),
				'soglia'           => StatsModel::SOGLIA_VISITE,
				'soglia_raggiunta' => StatsModel::soglia_raggiunta( $post_id ),
				'novita'           => StatsModel::is_novita( $post_id ),
			),
			200
		);
	}
}
