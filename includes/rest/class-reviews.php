<?php
/**
 * Endpoint REST `GET advertrieste/v1/locale/{id}/reviews`.
 *
 * Pubblico: voto e recensioni Google (dalla cache server-side). Inerte se la
 * funzione recensioni è disattivata o senza chiave API.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Rest;

use AdverTrieste\Cpt\Locale;
use AdverTrieste\Reviews\Reviews as ReviewsModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller REST delle recensioni.
 */
class Reviews {

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
			'/locale/(?P<id>\d+)/reviews',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_reviews' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);
	}

	/**
	 * Restituisce le recensioni della scheda.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_reviews( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post    = get_post( $post_id );
		if ( ! $post || Locale::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'advtr_reviews_not_found', __( 'Scheda non valida.', 'advertrieste' ), array( 'status' => 404 ) );
		}

		$data           = ReviewsModel::for_locale( $post_id );
		$place          = (string) get_post_meta( $post_id, 'advtr_place_id', true );
		$data['scrivi'] = $place ? 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $place ) : '';

		return new WP_REST_Response( $data, 200 );
	}
}
