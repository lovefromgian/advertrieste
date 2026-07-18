<?php
/**
 * Workflow di revisione degli eventi (§4.3).
 *
 * Modello a doppia versione:
 * - il post WP è la versione IN LAVORAZIONE (ciò che l'organizzatore modifica);
 * - `advtr_versione_pubblica` è lo snapshot dell'ultima versione APPROVATA, ed è
 *   l'unico contenuto mostrato al pubblico.
 *
 * Stati (`advtr_stato_workflow`): bozza → in_revisione → pubblicato.
 * L'approvazione copia lo stato attuale del post nella versione pubblica.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Evento;

use AdverTrieste\Cpt\Evento;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transizioni e snapshot del workflow eventi.
 */
class Workflow {

	const STATO_BOZZA        = 'bozza';
	const STATO_IN_REVISIONE = 'in_revisione';
	const STATO_PUBBLICATO   = 'pubblicato';

	const META_STATO    = 'advtr_stato_workflow';
	const META_PUBBLICA = 'advtr_versione_pubblica';

	/**
	 * Stato workflow corrente (default: bozza).
	 *
	 * @param int $post_id ID evento.
	 * @return string
	 */
	public static function stato( $post_id ) {
		$stato = get_post_meta( $post_id, self::META_STATO, true );
		return $stato ? $stato : self::STATO_BOZZA;
	}

	/**
	 * Imposta lo stato workflow.
	 *
	 * @param int    $post_id ID evento.
	 * @param string $stato   Nuovo stato.
	 * @return void
	 */
	public static function set_stato( $post_id, $stato ) {
		update_post_meta( $post_id, self::META_STATO, $stato );
	}

	/**
	 * Snapshot dello stato attuale del post (versione in lavorazione).
	 *
	 * @param int $post_id ID evento.
	 * @return array<string,mixed>
	 */
	public static function snapshot( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}
		$locali = get_post_meta( $post_id, 'advtr_locali_collegati', true );
		return array(
			'titolo'           => $post->post_title,
			'contenuto'        => $post->post_content,
			'tipo_evento'      => (string) get_post_meta( $post_id, 'advtr_tipo_evento', true ),
			'data_inizio'      => (string) get_post_meta( $post_id, 'advtr_data_inizio', true ),
			'data_fine'        => (string) get_post_meta( $post_id, 'advtr_data_fine', true ),
			'locali_collegati' => is_array( $locali ) ? array_map( 'absint', $locali ) : array(),
			'thumbnail_id'     => (int) get_post_thumbnail_id( $post_id ),
			'approvata_il'     => current_time( 'mysql' ),
		);
	}

	/**
	 * Versione pubblica approvata (o null se mai approvata).
	 *
	 * @param int $post_id ID evento.
	 * @return array<string,mixed>|null
	 */
	public static function public_version( $post_id ) {
		$v = get_post_meta( $post_id, self::META_PUBBLICA, true );
		return is_array( $v ) && ! empty( $v ) ? $v : null;
	}

	/**
	 * È stato approvato almeno una volta (esiste una versione pubblica)?
	 *
	 * @param int $post_id ID evento.
	 * @return bool
	 */
	public static function is_published( $post_id ) {
		return null !== self::public_version( $post_id );
	}

	/**
	 * Invia in revisione (bozza → in_revisione).
	 *
	 * @param int $post_id ID evento.
	 * @return void
	 */
	public static function submit( $post_id ) {
		self::set_stato( $post_id, self::STATO_IN_REVISIONE );
	}

	/**
	 * Approva: copia lo stato attuale nella versione pubblica; stato pubblicato.
	 *
	 * @param int $post_id ID evento.
	 * @return void
	 */
	public static function approve( $post_id ) {
		update_post_meta( $post_id, self::META_PUBBLICA, self::snapshot( $post_id ) );
		self::set_stato( $post_id, self::STATO_PUBBLICATO );
	}

	/**
	 * Segna che ci sono modifiche non ancora inviate (→ bozza) se era pubblicato
	 * o in revisione. Da chiamare al salvataggio del post.
	 *
	 * @param int $post_id ID evento.
	 * @return void
	 */
	public static function mark_dirty( $post_id ) {
		$stato = self::stato( $post_id );
		if ( self::STATO_PUBBLICATO === $stato || self::STATO_IN_REVISIONE === $stato ) {
			self::set_stato( $post_id, self::STATO_BOZZA );
		}
	}
}
