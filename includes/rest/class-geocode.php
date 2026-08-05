<?php
/**
 * Endpoint REST `GET advertrieste/v1/geocode` — indirizzo → coordinate.
 *
 * Riservato a chi può modificare contenuti: non è un servizio pubblico e non
 * deve diventare un proxy aperto verso Nominatim, che ne vieta l'uso di massa.
 * Il `permission_callback` è quindi un controllo reale, non `__return_true`.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Rest;

use AdverTrieste\Geo\Geocode as Servizio;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller REST del geocoding.
 */
class Geocode {

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
			'/geocode',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'cerca' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'args'                => array(
					'indirizzo' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Solo utenti autenticati che possono modificare contenuti.
	 *
	 * @return true|WP_Error
	 */
	public static function permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'advtr_geocode_unauthenticated',
				__( 'Accesso richiesto.', 'advertrieste' ),
				array( 'status' => 401 )
			);
		}
		// Basta poter modificare qualcosa: la usano il meta box dei punti QR
		// (admin) e, in prospettiva, l'area clienti per la propria scheda.
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'advtr_edit_own_locale' ) ) {
			return new WP_Error(
				'advtr_geocode_forbidden',
				__( 'Non hai i permessi per usare la ricerca indirizzi.', 'advertrieste' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Restituisce le coordinate dell'indirizzo richiesto.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function cerca( WP_REST_Request $request ) {
		$esito = Servizio::cerca( (string) $request->get_param( 'indirizzo' ) );

		if ( is_wp_error( $esito ) ) {
			$stato = 'advtr_geocode_nessun_risultato' === $esito->get_error_code() ? 404 : 502;
			if ( 'advtr_geocode_troppo_rapido' === $esito->get_error_code() ) {
				$stato = 429;
			}
			if ( 'advtr_geocode_vuoto' === $esito->get_error_code() ) {
				$stato = 400;
			}
			return new WP_Error( $esito->get_error_code(), $esito->get_error_message(), array( 'status' => $stato ) );
		}

		return new WP_REST_Response( $esito, 200 );
	}
}
