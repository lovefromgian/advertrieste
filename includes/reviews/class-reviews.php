<?php
/**
 * Recensioni Google Places (§1.5, ◐ condizionata).
 *
 * Recupera voto e recensioni da Google Places (Place Details) per il `place_id`
 * del locale, con cache server-side (transient) di alcuni giorni per rispettare
 * i limiti Google. La funzione è inerte se manca la chiave API o se è stata
 * disattivata (interruttore), così il resto del sito non ne risente.
 *
 * Chiave API: costante `ADVTR_GOOGLE_PLACES_KEY` (consigliata, in wp-config) o
 * opzione `advtr_google_places_key`. Interruttore: opzione `advtr_reviews_disabled`.
 * Il tetto di spesa va impostato lato Google Cloud.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Reviews;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recupero e cache delle recensioni Google.
 */
class Reviews {

	/**
	 * Durata della cache delle recensioni (secondi).
	 *
	 * @var int
	 */
	const CACHE_TTL = 3 * DAY_IN_SECONDS;

	/**
	 * Chiave API Places (costante o opzione), stringa vuota se assente.
	 *
	 * @return string
	 */
	private static function key() {
		if ( defined( 'ADVTR_GOOGLE_PLACES_KEY' ) && ADVTR_GOOGLE_PLACES_KEY ) {
			return (string) ADVTR_GOOGLE_PLACES_KEY;
		}
		return (string) get_option( 'advtr_google_places_key', '' );
	}

	/**
	 * La funzionalità recensioni è attiva? (chiave presente e non disattivata).
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( get_option( 'advtr_reviews_disabled' ) ) {
			return false;
		}
		return '' !== self::key();
	}

	/**
	 * Recensioni + voto per una scheda (dalla cache o da Google).
	 *
	 * @param int $post_id ID del locale.
	 * @return array<string,mixed> { enabled, media_rating, totale, recensioni[] }
	 */
	public static function for_locale( $post_id ) {
		$vuoto = array(
			'enabled'      => self::is_enabled(),
			'media_rating' => 0,
			'totale'       => 0,
			'recensioni'   => array(),
		);

		$place = (string) get_post_meta( $post_id, 'advtr_place_id', true );
		if ( ! self::is_enabled() || '' === $place ) {
			return $vuoto;
		}

		$cache_key = 'advtr_rev_' . md5( $place );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$data = self::fetch( $place );
		if ( null === $data ) {
			// Errore/limite: cache breve per non martellare l'API.
			set_transient( $cache_key, $vuoto, HOUR_IN_SECONDS );
			return $vuoto;
		}

		set_transient( $cache_key, $data, self::CACHE_TTL );
		return $data;
	}

	/**
	 * Interroga Google Places Details. Restituisce null in caso di errore.
	 *
	 * @param string $place_id Place ID Google.
	 * @return array<string,mixed>|null
	 */
	private static function fetch( $place_id ) {
		$url = add_query_arg(
			array(
				'place_id' => rawurlencode( $place_id ),
				'fields'   => 'rating,user_ratings_total,reviews',
				'language' => 'it',
				'key'      => self::key(),
			),
			'https://maps.googleapis.com/maps/api/place/details/json'
		);

		$res = wp_remote_get( $url, array( 'timeout' => 8 ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) || 'OK' !== ( $body['status'] ?? '' ) ) {
			return null;
		}

		$result      = $body['result'] ?? array();
		$recensioni  = array();
		foreach ( (array) ( $result['reviews'] ?? array() ) as $r ) {
			$recensioni[] = array(
				'autore'   => sanitize_text_field( (string) ( $r['author_name'] ?? '' ) ),
				'rating'   => (float) ( $r['rating'] ?? 0 ),
				'testo'    => wp_kses_post( (string) ( $r['text'] ?? '' ) ),
				'quando'   => sanitize_text_field( (string) ( $r['relative_time_description'] ?? '' ) ),
			);
		}

		return array(
			'enabled'      => true,
			'media_rating' => (float) ( $result['rating'] ?? 0 ),
			'totale'       => (int) ( $result['user_ratings_total'] ?? 0 ),
			'recensioni'   => $recensioni,
		);
	}
}
