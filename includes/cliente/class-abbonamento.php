<?php
/**
 * Stato dell'abbonamento di una scheda, letto dalle date di validità.
 *
 * Non introduce un modello nuovo: la validità è già `advtr_data_inizio` e
 * `advtr_data_fine`, le stesse date su cui lavora il cron delle scadenze. Qui
 * vengono solo interpretate per il cliente, che deve capire a colpo d'occhio
 * quanto gli resta.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Cliente;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lettura dello stato di abbonamento.
 */
class Abbonamento {

	/**
	 * Giorni mancanti alla scadenza.
	 *
	 * @param int $post_id ID della scheda.
	 * @return int|null Giorni (negativi se scaduto), null se non c'è una scadenza.
	 */
	public static function giorni_alla_scadenza( $post_id ) {
		$fine = (string) get_post_meta( $post_id, 'advtr_data_fine', true );
		if ( '' === $fine ) {
			return null;
		}
		// Differenza in GIORNI DI CALENDARIO, non in ore. Contando le ore che
		// mancano alla fine dell'ultimo giorno valido e arrotondando per eccesso,
		// una scadenza fra dodici giorni verrebbe annunciata come tredici.
		// `round` invece di `floor` perché i cambi di ora legale spostano il
		// divario di un'ora e falserebbero il conteggio.
		$fine_ts = strtotime( $fine . ' 00:00:00' );
		$oggi_ts = strtotime( current_time( 'Y-m-d' ) . ' 00:00:00' );
		if ( ! $fine_ts || ! $oggi_ts ) {
			return null;
		}
		return (int) round( ( $fine_ts - $oggi_ts ) / DAY_IN_SECONDS );
	}

	/**
	 * Data di scadenza formattata per la lettura.
	 *
	 * @param int $post_id ID della scheda.
	 * @return string Stringa vuota se non impostata.
	 */
	public static function data_scadenza( $post_id ) {
		$fine = (string) get_post_meta( $post_id, 'advtr_data_fine', true );
		if ( '' === $fine ) {
			return '';
		}
		$ts = strtotime( $fine );
		return $ts ? wp_date( 'j F Y', $ts ) : '';
	}

	/**
	 * Data di inizio validità formattata.
	 *
	 * @param int $post_id ID della scheda.
	 * @return string
	 */
	public static function data_inizio( $post_id ) {
		$inizio = (string) get_post_meta( $post_id, 'advtr_data_inizio', true );
		if ( '' === $inizio ) {
			return '';
		}
		$ts = strtotime( $inizio );
		return $ts ? wp_date( 'j F Y', $ts ) : '';
	}

	/**
	 * Stato sintetico: attivo, in scadenza, scaduto, sospeso o senza termine.
	 *
	 * @param int $post_id ID della scheda.
	 * @return array{codice:string,etichetta:string,pill:string}
	 */
	public static function stato( $post_id ) {
		if ( get_post_meta( $post_id, 'advtr_sospesa', true ) ) {
			return array(
				'codice'    => 'sospesa',
				'etichetta' => __( 'Sospesa', 'advertrieste' ),
				'pill'      => 'attesa',
			);
		}

		$giorni = self::giorni_alla_scadenza( $post_id );
		if ( null === $giorni ) {
			return array(
				'codice'    => 'senza_termine',
				'etichetta' => __( 'Senza scadenza', 'advertrieste' ),
				'pill'      => 'ok',
			);
		}
		if ( $giorni <= 0 ) {
			return array(
				'codice'    => 'scaduto',
				'etichetta' => __( 'Scaduto', 'advertrieste' ),
				'pill'      => 'attesa',
			);
		}
		if ( $giorni <= 30 ) {
			return array(
				'codice'    => 'in_scadenza',
				'etichetta' => __( 'In scadenza', 'advertrieste' ),
				'pill'      => 'attesa',
			);
		}
		return array(
			'codice'    => 'attivo',
			'etichetta' => __( 'Attivo', 'advertrieste' ),
			'pill'      => 'ok',
		);
	}
}
