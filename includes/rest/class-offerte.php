<?php
/**
 * Endpoint REST delle offerte/coupon.
 *
 * - `GET  advertrieste/v1/offerte`            — pubblico: offerte attive (con countdown).
 * - `POST advertrieste/v1/offerta/{id}/redeem` — esercente/admin: valida un coupon.
 *
 * La validazione del coupon è riservata al proprietario del `locale` collegato
 * (o all'admin): registra un riscatto e traccia l'evento `coupon` nelle statistiche.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Rest;

use AdverTrieste\Cpt\Offerta;
use AdverTrieste\Cpt\Locale;
use AdverTrieste\Coupon\Coupon;
use AdverTrieste\Stats\Stats;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller REST di offerte e riscatti coupon.
 */
class Offerte {

	/**
	 * Aggancia la registrazione delle route.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registra le route.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			Markers::NAMESPACE,
			'/offerte',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_offerte' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'locale' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			Markers::NAMESPACE,
			'/offerta/(?P<id>\d+)/redeem',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'redeem' ),
				'permission_callback' => array( __CLASS__, 'can_redeem' ),
				'args'                => array(
					'id'     => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'codice' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Elenco delle offerte attive (opzionalmente per locale).
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response
	 */
	public static function get_offerte( WP_REST_Request $request ) {
		$locale = absint( $request->get_param( 'locale' ) );

		$args = array(
			'post_type'      => Offerta::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $locale ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'advtr_locale_id',
					'value' => $locale,
				),
			);
		}

		$offerte = array();
		foreach ( get_posts( $args ) as $post ) {
			if ( ! Coupon::is_offer_active( $post->ID ) ) {
				continue;
			}
			$locale_id = (int) get_post_meta( $post->ID, 'advtr_locale_id', true );
			$offerte[] = array(
				'id'            => $post->ID,
				'titolo'        => get_the_title( $post ),
				'descrizione'   => wp_kses_post( $post->post_content ),
				'locale_id'     => $locale_id,
				'locale_titolo' => $locale_id ? get_the_title( $locale_id ) : '',
				'data_scadenza' => (string) get_post_meta( $post->ID, 'advtr_data_scadenza', true ),
				'tipo_coupon'   => (string) get_post_meta( $post->ID, 'advtr_tipo_coupon', true ),
				'codice'        => (string) get_post_meta( $post->ID, 'advtr_codice', true ),
			);
		}

		return new WP_REST_Response( $offerte, 200 );
	}

	/**
	 * Permesso di validare un coupon: proprietario del locale collegato o admin.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return true|WP_Error
	 */
	public static function can_redeem( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'advtr_redeem_unauthenticated',
				__( 'Accesso richiesto.', 'advertrieste' ),
				array( 'status' => 401 )
			);
		}

		$offerta = get_post( absint( $request->get_param( 'id' ) ) );
		if ( ! $offerta || Offerta::POST_TYPE !== $offerta->post_type ) {
			return new WP_Error(
				'advtr_redeem_not_found',
				__( 'Offerta non trovata.', 'advertrieste' ),
				array( 'status' => 404 )
			);
		}

		if ( ! self::owns_offer( $offerta ) ) {
			return new WP_Error(
				'advtr_redeem_forbidden',
				__( 'Non puoi validare coupon per questa offerta.', 'advertrieste' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * L'utente corrente è l'esercente del locale collegato (o admin)?
	 *
	 * @param \WP_Post $offerta Offerta.
	 * @return bool
	 */
	private static function owns_offer( $offerta ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$uid = get_current_user_id();
		if ( (int) $offerta->post_author === $uid ) {
			return true;
		}
		$locale_id = (int) get_post_meta( $offerta->ID, 'advtr_locale_id', true );
		if ( $locale_id ) {
			$locale = get_post( $locale_id );
			if ( $locale && Locale::POST_TYPE === $locale->post_type && (int) $locale->post_author === $uid ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Valida un coupon: verifica codice e finestra, registra il riscatto.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function redeem( WP_REST_Request $request ) {
		$offerta_id = absint( $request->get_param( 'id' ) );
		$codice     = (string) $request->get_param( 'codice' );

		if ( ! Coupon::is_offer_active( $offerta_id ) ) {
			return new WP_Error(
				'advtr_redeem_inactive',
				__( 'Offerta non attiva o scaduta.', 'advertrieste' ),
				array( 'status' => 409 )
			);
		}

		$atteso = (string) get_post_meta( $offerta_id, 'advtr_codice', true );
		if ( '' === $atteso || ! hash_equals( $atteso, $codice ) ) {
			return new WP_Error(
				'advtr_redeem_bad_code',
				__( 'Codice coupon non valido.', 'advertrieste' ),
				array( 'status' => 422 )
			);
		}

		Coupon::record_redemption( $offerta_id, $codice );

		// Traccia il riscatto nelle statistiche del locale collegato.
		$locale_id = (int) get_post_meta( $offerta_id, 'advtr_locale_id', true );
		if ( $locale_id ) {
			Stats::record( $locale_id, 'coupon' );
		}

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'riscatti' => Coupon::redemptions_count( $offerta_id ),
			),
			200
		);
	}
}
