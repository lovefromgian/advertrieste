<?php
/**
 * Endpoint REST `POST advertrieste/v1/locale/{id}/track`.
 *
 * Registra un evento statistico pubblico (view/map_click/contact) per una scheda
 * `locale`. Protezioni: nonce REST + rate-limit per IP/scheda/tipo, così da
 * evitare conteggi gonfiati (specifiche §1.6). Valida che la scheda esista, sia
 * un `locale` pubblicato e che il tipo sia fra quelli pubblici.
 *
 * !!! SICUREZZA: il tipo `coupon` NON è accettabile da qui (vedi
 * `Stats::TIPI_PUBBLICI`): è la metrica di ritorno commerciale mostrata al
 * cliente e può nascere solo dalla validazione autenticata dell'esercente
 * (`POST /offerta/{id}/redeem`).
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
						// Solo i tipi pubblici: `coupon` nasce dalla validazione
						// autenticata dell'esercente, non da qui.
						'enum'              => Stats::TIPI_PUBBLICI,
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
	 * Permission: verifica che la richiesta provenga dal sito.
	 *
	 * NON richiede un nonce per i visitatori anonimi, e la ragione è concreta.
	 * Un nonce viene generato quando la pagina è costruita e vale fra 12 e 24
	 * ore; una page cache serve la stessa pagina per tutta la durata configurata
	 * (24 ore, nell'installazione di riferimento). Appena il nonce scade, il core
	 * risponde 403 `rest_cookie_invalid_nonce` — prima ancora di arrivare qui — e
	 * il tracciamento smette di contare **in silenzio**: nessun errore visibile,
	 * solo grafici che si appiattiscono.
	 *
	 * Per un anonimo quel nonce non proteggeva granché: è identico per tutti i
	 * visitatori e chiunque può prenderne uno fresco da qualsiasi pagina. Al suo
	 * posto verifichiamo l'origine della richiesta, che non scade.
	 *
	 * Onestà su cosa questo ferma: l'inclusione della chiamata da un altro sito,
	 * non uno script determinato, che può inviare qualsiasi intestazione. Contro
	 * l'inflazione dei conteggi la difesa vera resta il rate-limit, insieme al
	 * fatto che il tipo `coupon` non è accettato da qui (`Stats::TIPI_PUBBLICI`).
	 *
	 * Se un nonce c'è, il core l'ha già validato prima di questo callback: agli
	 * utenti autenticati continua quindi ad applicarsi la protezione CSRF piena.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return true|WP_Error
	 */
	public static function permission( WP_REST_Request $request ) {
		if ( ! self::origine_attendibile( $request ) ) {
			return new WP_Error(
				'advtr_track_origine',
				__( 'Richiesta proveniente da un\'origine esterna.', 'advertrieste' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * La richiesta arriva da una pagina di questo sito?
	 *
	 * Confronta `Origin` e, in mancanza, `Referer` con l'host del sito. Se non
	 * è presente nessuno dei due la richiesta passa: alcuni browser e estensioni
	 * per la privacy li rimuovono, e rifiutare significherebbe ricadere nello
	 * stesso problema che stiamo correggendo — perdere dati in silenzio.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return bool
	 */
	private static function origine_attendibile( WP_REST_Request $request ) {
		$sito = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( array( 'Origin', 'Referer' ) as $intestazione ) {
			$valore = $request->get_header( $intestazione );
			if ( ! $valore ) {
				continue;
			}
			$host = wp_parse_url( $valore, PHP_URL_HOST );
			// La prima intestazione presente decide: se c'è ed è di un altro
			// sito, la richiesta non nasce da una nostra pagina.
			return $host && strtolower( $host ) === strtolower( (string) $sito );
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

		// Difesa in profondità: il tipo è già vincolato dallo schema (enum), ma lo
		// ricontrolliamo qui perché `coupon` non deve mai entrare da questa via.
		if ( ! in_array( $tipo, Stats::TIPI_PUBBLICI, true ) ) {
			return new WP_Error(
				'advtr_track_tipo_non_ammesso',
				__( 'Tipo di evento non tracciabile pubblicamente.', 'advertrieste' ),
				array( 'status' => 400 )
			);
		}

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
