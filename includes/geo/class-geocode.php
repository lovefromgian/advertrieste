<?php
/**
 * Geocoding degli indirizzi tramite Nominatim (OpenStreetMap).
 *
 * Scelto Nominatim e non Google: nessuna chiave API, nessun costo a consumo, e
 * coerenza con la mappa del progetto, che è già OSM per motivi di costo
 * (`CLAUDE.md`). In cambio ci sono i vincoli d'uso di Nominatim, che qui sono
 * rispettati alla lettera:
 *
 * - **User-Agent identificabile**: obbligatorio, altrimenti le richieste vengono
 *   rifiutate. Usiamo nome del plugin + URL del sito.
 * - **Massimo 1 richiesta al secondo**: garantito da un lucchetto su transient.
 * - **Niente geocoding di massa**: la ricerca parte solo da un gesto esplicito
 *   dell'amministratore, mai in automatico al salvataggio o in blocco.
 * - **Cache dei risultati**: un indirizzo già risolto non torna sulla rete.
 *
 * Il risultato è un suggerimento, non un dato definitivo: l'indirizzo di una
 * piazza restituisce il suo centroide, che quasi mai coincide con la posizione
 * fisica di un espositore. La correzione fine resta al segnaposto trascinabile.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Geo;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client di geocoding con cache e rispetto dei limiti d'uso.
 */
class Geocode {

	/**
	 * Endpoint di ricerca di Nominatim.
	 *
	 * @var string
	 */
	const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

	/**
	 * Durata della cache di un indirizzo risolto.
	 *
	 * Lunga: la posizione di un civico non cambia. Serve a non ripetere la
	 * chiamata quando si riapre o si risalva lo stesso punto.
	 *
	 * @var int
	 */
	const CACHE_TTL = MONTH_IN_SECONDS;

	/**
	 * Transient usato come lucchetto per il limite di 1 richiesta al secondo.
	 *
	 * @var string
	 */
	const LOCK = 'advtr_geocode_lock';

	/**
	 * Riquadro di preferenza (Trieste e dintorni): ovest,nord,est,sud.
	 *
	 * Non è un vincolo rigido: alza il punteggio dei risultati locali senza
	 * escludere il resto, così "Via Roma" non finisce a Palermo.
	 *
	 * @var string
	 */
	const VIEWBOX = '13.60,45.82,13.95,45.55';

	/**
	 * Risolve un indirizzo in coordinate.
	 *
	 * @param string $indirizzo Indirizzo scritto dall'utente.
	 * @return array{lat:float,lng:float,etichetta:string}|\WP_Error
	 */
	public static function cerca( $indirizzo ) {
		$indirizzo = trim( wp_strip_all_tags( (string) $indirizzo ) );
		if ( '' === $indirizzo ) {
			return new \WP_Error( 'advtr_geocode_vuoto', __( 'Inserisci un indirizzo da cercare.', 'advertrieste' ) );
		}

		$chiave = 'advtr_geo_' . md5( strtolower( $indirizzo ) );
		$cache  = get_transient( $chiave );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		// Limite d'uso di Nominatim: non più di una richiesta al secondo.
		if ( get_transient( self::LOCK ) ) {
			return new \WP_Error(
				'advtr_geocode_troppo_rapido',
				__( 'Troppe ricerche ravvicinate: riprova fra un istante.', 'advertrieste' )
			);
		}
		set_transient( self::LOCK, 1, 1 );

		$url = add_query_arg(
			array(
				'q'               => rawurlencode( $indirizzo ),
				'format'          => 'jsonv2',
				'limit'           => 1,
				'addressdetails'  => 0,
				'countrycodes'    => 'it',
				'viewbox'         => self::VIEWBOX,
				'accept-language' => 'it',
			),
			self::ENDPOINT
		);

		$risposta = wp_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					// Richiesto dalla politica d'uso: identifica l'applicazione.
					'User-Agent' => 'AdverTrieste/' . ADVTR_VERSION . ' (' . home_url( '/' ) . ')',
					'Referer'    => home_url( '/' ),
				),
			)
		);

		if ( is_wp_error( $risposta ) ) {
			return new \WP_Error( 'advtr_geocode_rete', __( 'Servizio di ricerca non raggiungibile.', 'advertrieste' ) );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $risposta ) ) {
			return new \WP_Error( 'advtr_geocode_servizio', __( 'Il servizio di ricerca ha risposto con un errore.', 'advertrieste' ) );
		}

		$dati = json_decode( wp_remote_retrieve_body( $risposta ), true );
		if ( ! is_array( $dati ) || empty( $dati[0]['lat'] ) || empty( $dati[0]['lon'] ) ) {
			return new \WP_Error(
				'advtr_geocode_nessun_risultato',
				__( 'Nessun risultato per questo indirizzo. Prova a essere più specifico, oppure posiziona il segnaposto a mano.', 'advertrieste' )
			);
		}

		$esito = array(
			'lat'       => (float) $dati[0]['lat'],
			'lng'       => (float) $dati[0]['lon'],
			'etichetta' => sanitize_text_field( (string) ( $dati[0]['display_name'] ?? '' ) ),
		);

		set_transient( $chiave, $esito, self::CACHE_TTL );
		return $esito;
	}
}
