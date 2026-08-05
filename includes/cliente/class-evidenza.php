<?php
/**
 * Stato del pacchetto "In Evidenza" per una scheda.
 *
 * Il cliente lo consulta, non lo attiva: marker dorato e priorità nei risultati
 * sono ciò che si vende, quindi restano una leva dell'amministratore. Qui c'è
 * solo la lettura, in modo che l'area clienti possa mostrare cosa il cliente ha
 * comprato e fino a quando.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Cliente;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lettura del piano "In Evidenza".
 */
class Evidenza {

	/**
	 * Il piano è attivo adesso? (flag più finestra di date).
	 *
	 * Stessa logica usata da `Rest\Markers::is_in_evidenza()` per il marker
	 * dorato: se le due divergessero, il cliente vedrebbe scritto "attivo"
	 * mentre sulla mappa non lo è.
	 *
	 * @param int $post_id ID della scheda.
	 * @return bool
	 */
	public static function attiva( $post_id ) {
		if ( ! get_post_meta( $post_id, 'advtr_in_evidenza', true ) ) {
			return false;
		}

		$inizio = (string) get_post_meta( $post_id, 'advtr_evidenza_inizio', true );
		$fine   = (string) get_post_meta( $post_id, 'advtr_evidenza_fine', true );
		$oggi   = current_time( 'Y-m-d' );

		if ( '' !== $inizio && $oggi < $inizio ) {
			return false;
		}
		if ( '' !== $fine && $oggi > $fine ) {
			return false;
		}
		return true;
	}

	/**
	 * Il piano è acquistato ma non ancora cominciato?
	 *
	 * @param int $post_id ID della scheda.
	 * @return bool
	 */
	public static function programmata( $post_id ) {
		if ( ! get_post_meta( $post_id, 'advtr_in_evidenza', true ) ) {
			return false;
		}
		$inizio = (string) get_post_meta( $post_id, 'advtr_evidenza_inizio', true );
		return '' !== $inizio && current_time( 'Y-m-d' ) < $inizio;
	}

	/**
	 * Finestra del piano, formattata per la lettura.
	 *
	 * @param int $post_id ID della scheda.
	 * @return array{inizio:string,fine:string}
	 */
	public static function finestra( $post_id ) {
		$fmt = static function ( $valore ) {
			$ts = $valore ? strtotime( (string) $valore ) : 0;
			return $ts ? wp_date( 'j F Y', $ts ) : '';
		};

		return array(
			'inizio' => $fmt( get_post_meta( $post_id, 'advtr_evidenza_inizio', true ) ),
			'fine'   => $fmt( get_post_meta( $post_id, 'advtr_evidenza_fine', true ) ),
		);
	}
}
