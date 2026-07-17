<?php
/**
 * Endpoint REST `GET advertrieste/v1/map/markers`.
 *
 * Restituisce i marker della mappa filtrati per bounding box, livello di zoom e
 * (opzionalmente) categoria. Interroga SOLO `locale` e `poi`.
 *
 * !!! SICUREZZA: questo endpoint è pubblico ma NON deve MAI includere `punto_qr`
 * (dati riservati). Il post type è vincolato esplicitamente alle sole entità
 * pubbliche: non passare mai il parametro post_type da input esterno.
 *
 * Zoom a due livelli: ogni marker ha una soglia `zoom_min`; è incluso solo se lo
 * zoom corrente è >= soglia. I `poi` hanno soglia bassa (visibili da lontano),
 * i `locale` soglia alta (visibili da vicino). Se la soglia non è impostata si
 * usa un default per tipo.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Rest;

use AdverTrieste\Cpt\Locale;
use AdverTrieste\Cpt\Poi;
use AdverTrieste\Cpt\Categoria;
use WP_REST_Request;
use WP_REST_Response;
use WP_Query;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller REST dei marker della mappa.
 */
class Markers {

	/**
	 * Namespace REST del plugin.
	 *
	 * @var string
	 */
	const NAMESPACE = 'advertrieste/v1';

	/**
	 * Soglia di zoom di default per tipo, quando `zoom_min` non è impostato.
	 *
	 * @var array<string,int>
	 */
	const DEFAULT_ZOOM_MIN = array(
		Poi::POST_TYPE    => 0,
		Locale::POST_TYPE => 14,
	);

	/**
	 * Numero massimo di marker restituiti per richiesta.
	 *
	 * @var int
	 */
	const MAX_MARKERS = 500;

	/**
	 * Aggancia la registrazione della route.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registra la route dei marker.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/map/markers',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_markers' ),
				// Dati pubblici (mai `punto_qr`): accesso libero.
				'permission_callback' => '__return_true',
				'args'                => self::args_schema(),
			)
		);
	}

	/**
	 * Schema/validazione dei parametri della richiesta.
	 *
	 * @return array<string,mixed>
	 */
	private static function args_schema() {
		$float = array(
			'required'          => true,
			'type'              => 'number',
			'validate_callback' => static function ( $value ) {
				return is_numeric( $value );
			},
			'sanitize_callback' => static function ( $value ) {
				return (float) $value;
			},
		);

		return array(
			'min_lat'   => $float,
			'min_lng'   => $float,
			'max_lat'   => $float,
			'max_lng'   => $float,
			'zoom'      => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'categoria' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
			),
		);
	}

	/**
	 * Gestisce la richiesta e costruisce la lista dei marker.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response
	 */
	public static function get_markers( WP_REST_Request $request ) {
		$min_lat   = $request->get_param( 'min_lat' );
		$min_lng   = $request->get_param( 'min_lng' );
		$max_lat   = $request->get_param( 'max_lat' );
		$max_lng   = $request->get_param( 'max_lng' );
		$zoom      = (int) $request->get_param( 'zoom' );
		$categoria = $request->get_param( 'categoria' );

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => 'advtr_lat',
				'value'   => array( min( $min_lat, $max_lat ), max( $min_lat, $max_lat ) ),
				'compare' => 'BETWEEN',
				'type'    => 'DECIMAL(10,6)',
			),
			array(
				'key'     => 'advtr_lng',
				'value'   => array( min( $min_lng, $max_lng ), max( $min_lng, $max_lng ) ),
				'compare' => 'BETWEEN',
				'type'    => 'DECIMAL(10,6)',
			),
		);

		$args = array(
			// Vincolo di sicurezza: solo entità pubbliche, MAI punto_qr.
			'post_type'              => array( Locale::POST_TYPE, Poi::POST_TYPE ),
			'post_status'            => 'publish',
			'posts_per_page'         => self::MAX_MARKERS,
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
			'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		if ( $categoria ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Categoria::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $categoria,
				),
			);
		}

		$query   = new WP_Query( $args );
		$markers = array();

		foreach ( $query->posts as $post ) {
			$type     = $post->post_type;
			$zoom_min = get_post_meta( $post->ID, 'advtr_zoom_min', true );
			$zoom_min = ( '' === $zoom_min ) ? self::DEFAULT_ZOOM_MIN[ $type ] : (int) $zoom_min;

			// Zoom a due livelli: mostra il marker solo da questa soglia in poi.
			if ( $zoom < $zoom_min ) {
				continue;
			}

			$markers[] = self::format_marker( $post, $type, $zoom_min );
		}

		return new WP_REST_Response( $markers, 200 );
	}

	/**
	 * Costruisce il payload di un singolo marker.
	 *
	 * @param \WP_Post $post     Post.
	 * @param string   $type     Post type.
	 * @param int      $zoom_min Soglia di zoom effettiva.
	 * @return array<string,mixed>
	 */
	private static function format_marker( $post, $type, $zoom_min ) {
		$terms = wp_get_post_terms( $post->ID, Categoria::TAXONOMY, array( 'fields' => 'slugs' ) );

		$logo_id  = (int) get_post_meta( $post->ID, 'advtr_logo_id', true );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : get_the_post_thumbnail_url( $post->ID, 'thumbnail' );

		return array(
			'id'          => $post->ID,
			'type'        => $type,
			'title'       => get_the_title( $post ),
			'lat'         => (float) get_post_meta( $post->ID, 'advtr_lat', true ),
			'lng'         => (float) get_post_meta( $post->ID, 'advtr_lng', true ),
			'categoria'   => is_array( $terms ) ? $terms : array(),
			'in_evidenza' => ( Locale::POST_TYPE === $type ) ? self::is_in_evidenza( $post->ID ) : false,
			'zoom_min'    => $zoom_min,
			'permalink'   => get_permalink( $post ),
			'logo'        => $logo_url ? $logo_url : '',
		);
	}

	/**
	 * Determina se un locale è "in evidenza" adesso (flag + finestra date).
	 *
	 * @param int $post_id ID del locale.
	 * @return bool
	 */
	private static function is_in_evidenza( $post_id ) {
		if ( ! get_post_meta( $post_id, 'advtr_in_evidenza', true ) ) {
			return false;
		}

		$inizio = get_post_meta( $post_id, 'advtr_evidenza_inizio', true );
		$fine   = get_post_meta( $post_id, 'advtr_evidenza_fine', true );
		$oggi   = current_time( 'Y-m-d' );

		if ( $inizio && $oggi < $inizio ) {
			return false;
		}
		if ( $fine && $oggi > $fine ) {
			return false;
		}
		return true;
	}
}
