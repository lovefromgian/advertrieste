<?php
/**
 * Endpoint REST degli eventi.
 *
 * - `GET  advertrieste/v1/eventi`           — pubblico: eventi approvati (versione pubblica).
 * - `GET  advertrieste/v1/grandi-eventi`    — pubblico: grandi eventi + locali collegati.
 * - `POST advertrieste/v1/evento/{id}/submit`  — autore/organizzatore: in_revisione.
 * - `POST advertrieste/v1/evento/{id}/approve` — admin: promuove la versione in
 *   lavorazione a versione pubblica.
 *
 * Il pubblico riceve SEMPRE la versione approvata (`Evento\Workflow::public_version`),
 * mai il contenuto in lavorazione del post.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Rest;

use AdverTrieste\Cpt\Evento;
use AdverTrieste\Access\Roles;
use AdverTrieste\Evento\Workflow;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller REST degli eventi e del workflow di revisione.
 */
class Eventi {

	/**
	 * Transient con la mappa dei locali coinvolti in un grande evento in corso.
	 *
	 * @var string
	 */
	const CACHE_LOCALI_IN_EVENTO = 'advtr_locali_in_evento';

	/**
	 * Durata della cache dei locali in evento.
	 *
	 * Breve di proposito: il risultato dipende dall'ora corrente (finestra
	 * inizio–fine dell'evento), quindi la scadenza limita il ritardo con cui un
	 * evento entra o esce dalla finestra. Le modifiche ai contenuti invalidano
	 * comunque la cache subito (vedi `flush_locali_in_evento`).
	 *
	 * @var int
	 */
	const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Aggancia la registrazione delle route e l'invalidazione della cache.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

		// Invalidazione della cache dei locali in evento: cambio di contenuto,
		// cancellazione o transizione di workflow.
		add_action( 'save_post_' . Evento::POST_TYPE, array( __CLASS__, 'flush_locali_in_evento' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_locali_in_evento_se_evento' ), 10, 2 );
		add_action( 'advtr_evento_workflow_cambiato', array( __CLASS__, 'flush_locali_in_evento' ) );
	}

	/**
	 * Svuota la cache dei locali in evento.
	 *
	 * @return void
	 */
	public static function flush_locali_in_evento() {
		delete_transient( self::CACHE_LOCALI_IN_EVENTO );
	}

	/**
	 * Svuota la cache solo se il post eliminato era un evento.
	 *
	 * @param int           $post_id ID del post eliminato.
	 * @param \WP_Post|null $post    Post eliminato, se passato dall'hook.
	 * @return void
	 */
	public static function flush_locali_in_evento_se_evento( $post_id, $post = null ) {
		$type = ( $post instanceof \WP_Post ) ? $post->post_type : get_post_type( $post_id );
		if ( Evento::POST_TYPE === $type ) {
			self::flush_locali_in_evento();
		}
	}

	/**
	 * Registra le route.
	 *
	 * @return void
	 */
	public static function register_routes() {
		$ns = Markers::NAMESPACE;

		register_rest_route(
			$ns,
			'/eventi',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_eventi' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$ns,
			'/grandi-eventi',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_grandi_eventi' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$ns,
			'/evento/(?P<id>\d+)/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit' ),
				'permission_callback' => array( __CLASS__, 'can_submit' ),
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);
		register_rest_route(
			$ns,
			'/evento/(?P<id>\d+)/approve',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'approve' ),
				'permission_callback' => array( __CLASS__, 'can_approve' ),
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);
	}

	/**
	 * Query degli eventi con versione pubblica approvata.
	 *
	 * @return int[] ID degli eventi approvati.
	 */
	private static function approved_ids() {
		return get_posts(
			array(
				'post_type'      => Evento::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- elenco eventi.
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => Workflow::META_PUBBLICA,
						'compare' => 'EXISTS',
					),
				),
			)
		);
	}

	/**
	 * Locali attualmente collegati a un grande evento in corso.
	 *
	 * Un grande evento è "in corso" se approvato e la data corrente è nella
	 * finestra inizio–fine della versione pubblica.
	 *
	 * Cachata: viene interrogata a ogni richiesta di `/map/markers`, cioè a ogni
	 * pan/zoom della mappa pubblica, e sotto scorre una query su tutti gli eventi
	 * approvati più una lettura meta per ciascuno.
	 *
	 * @return array<int,bool> Mappa locale_id => true.
	 */
	public static function locali_in_evento() {
		$cached = get_transient( self::CACHE_LOCALI_IN_EVENTO );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$now = current_time( 'mysql' );
		$out = array();
		foreach ( self::approved_ids() as $id ) {
			$v = Workflow::public_version( $id );
			if ( ! $v || 'grande' !== ( $v['tipo_evento'] ?? '' ) ) {
				continue;
			}
			$inizio = (string) ( $v['data_inizio'] ?? '' );
			$fine   = (string) ( $v['data_fine'] ?? '' );
			if ( $inizio && $now < $inizio ) {
				continue;
			}
			if ( $fine && $now > $fine ) {
				continue;
			}
			foreach ( (array) ( $v['locali_collegati'] ?? array() ) as $lid ) {
				$out[ (int) $lid ] = true;
			}
		}

		set_transient( self::CACHE_LOCALI_IN_EVENTO, $out, self::CACHE_TTL );
		return $out;
	}

	/**
	 * GET /eventi — eventi approvati (versione pubblica).
	 *
	 * @return WP_REST_Response
	 */
	public static function get_eventi() {
		$out = array();
		foreach ( self::approved_ids() as $id ) {
			$v = Workflow::public_version( $id );
			if ( ! $v ) {
				continue;
			}
			$out[] = self::format_public( $id, $v );
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( (string) $a['data_inizio'], (string) $b['data_inizio'] );
			}
		);
		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * GET /grandi-eventi — grandi eventi + locali collegati risolti.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_grandi_eventi() {
		$out = array();
		foreach ( self::approved_ids() as $id ) {
			$v = Workflow::public_version( $id );
			if ( ! $v || 'grande' !== ( $v['tipo_evento'] ?? '' ) ) {
				continue;
			}
			$item           = self::format_public( $id, $v );
			$item['locali'] = self::resolve_locali( $v['locali_collegati'] ?? array() );
			$out[]          = $item;
		}
		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * Formatta la versione pubblica di un evento per l'output.
	 *
	 * @param int                 $id ID evento.
	 * @param array<string,mixed> $v  Versione pubblica.
	 * @return array<string,mixed>
	 */
	private static function format_public( $id, $v ) {
		$thumb = ! empty( $v['thumbnail_id'] ) ? wp_get_attachment_image_url( (int) $v['thumbnail_id'], 'medium' ) : '';
		return array(
			'id'          => (int) $id,
			'titolo'      => (string) ( $v['titolo'] ?? '' ),
			'contenuto'   => wp_kses_post( (string) ( $v['contenuto'] ?? '' ) ),
			'tipo_evento' => (string) ( $v['tipo_evento'] ?? '' ),
			'data_inizio' => (string) ( $v['data_inizio'] ?? '' ),
			'data_fine'   => (string) ( $v['data_fine'] ?? '' ),
			'immagine'    => $thumb ? $thumb : '',
		);
	}

	/**
	 * Risolve gli ID dei locali collegati in dati per la mappa.
	 *
	 * @param int[] $ids ID locali.
	 * @return array<int,array<string,mixed>>
	 */
	private static function resolve_locali( $ids ) {
		$out = array();
		foreach ( (array) $ids as $lid ) {
			$lid  = (int) $lid;
			$post = get_post( $lid );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}
			$out[] = array(
				'id'     => $lid,
				'titolo' => get_the_title( $lid ),
				'lat'    => (float) get_post_meta( $lid, 'advtr_lat', true ),
				'lng'    => (float) get_post_meta( $lid, 'advtr_lng', true ),
			);
		}
		return $out;
	}

	/**
	 * Permesso di inviare in revisione: autore dell'evento o admin.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return true|WP_Error
	 */
	public static function can_submit( WP_REST_Request $request ) {
		$post = self::get_evento( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'advtr_evento_unauthenticated', __( 'Accesso richiesto.', 'advertrieste' ), array( 'status' => 401 ) );
		}
		if ( current_user_can( 'manage_options' ) || get_current_user_id() === (int) $post->post_author ) {
			return true;
		}
		return new WP_Error( 'advtr_evento_forbidden', __( 'Non puoi inviare questo evento.', 'advertrieste' ), array( 'status' => 403 ) );
	}

	/**
	 * Permesso di approvare: capability dedicata (admin).
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return true|WP_Error
	 */
	public static function can_approve( WP_REST_Request $request ) {
		$post = self::get_evento( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( Roles::CAP_APPROVE_EVENTO ) ) {
			return new WP_Error( 'advtr_evento_forbidden', __( 'Non hai i permessi per approvare.', 'advertrieste' ), array( 'status' => is_user_logged_in() ? 403 : 401 ) );
		}
		return true;
	}

	/**
	 * Recupera l'evento validandone il tipo.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return \WP_Post|WP_Error
	 */
	private static function get_evento( WP_REST_Request $request ) {
		$post = get_post( absint( $request->get_param( 'id' ) ) );
		if ( ! $post || Evento::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'advtr_evento_not_found', __( 'Evento non trovato.', 'advertrieste' ), array( 'status' => 404 ) );
		}
		return $post;
	}

	/**
	 * POST submit → in_revisione.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response
	 */
	public static function submit( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		Workflow::submit( $id );
		return new WP_REST_Response(
			array(
				'ok'    => true,
				'stato' => Workflow::stato( $id ),
			),
			200
		);
	}

	/**
	 * POST approve → versione pubblica + pubblicato.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response
	 */
	public static function approve( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		Workflow::approve( $id );
		return new WP_REST_Response(
			array(
				'ok'    => true,
				'stato' => Workflow::stato( $id ),
			),
			200
		);
	}
}
