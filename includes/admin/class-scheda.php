<?php
/**
 * Salvataggio di una scheda dalla console amministratore.
 *
 * Riusa `Cliente\Scheda::salva()` per i campi di contenuto — descrizione,
 * servizi, contatti, orari, categorie, posizione — e aggiunge quelli che al
 * cliente restano preclusi: proprietario, validità, badge "In Evidenza" con la
 * sua priorità, soglia di zoom e stato di pubblicazione.
 *
 * La separazione è voluta: la regola "il cliente non tocca i campi
 * commerciali" continua a vivere in un punto solo, e qui si aggiunge invece di
 * riscrivere. Se un domani cambia il perimetro del cliente, cambia lì.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Admin;

use AdverTrieste\Cliente\Scheda as SchedaCliente;
use AdverTrieste\Cpt\Locale;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scrittura completa della scheda, riservata all'amministratore.
 */
class Scheda {

	/**
	 * Salva contenuti e campi commerciali.
	 *
	 * @return string Codice di esito.
	 */
	public static function salva() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return 'negato';
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verificato dal controller.
		$post_id = isset( $_POST['advtr_locale_id'] ) ? absint( wp_unslash( $_POST['advtr_locale_id'] ) ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || Locale::POST_TYPE !== $post->post_type ) {
			return 'negato';
		}

		// Prima i campi comuni, con le stesse regole di sanitizzazione.
		$esito = SchedaCliente::salva();
		if ( 'scheda_ok' !== $esito ) {
			return $esito;
		}

		// Proprietario: è ciò che determina chi vede la scheda nell'area clienti.
		$autore = isset( $_POST['advtr_autore'] ) ? absint( wp_unslash( $_POST['advtr_autore'] ) ) : 0;
		if ( $autore && get_userdata( $autore ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_author' => $autore,
				)
			);
		}

		// Stato di pubblicazione.
		$stato = isset( $_POST['advtr_stato_post'] ) ? sanitize_key( wp_unslash( $_POST['advtr_stato_post'] ) ) : '';
		if ( in_array( $stato, array( 'publish', 'draft' ), true ) && $stato !== $post->post_status ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $stato,
				)
			);
			if ( 'publish' === $stato ) {
				// Ripubblicare a mano annulla la sospensione automatica, altrimenti
				// il cron la considererebbe "già sospesa" e non agirebbe più.
				delete_post_meta( $post_id, 'advtr_sospesa' );
			}
		}

		// Validità e soglia di zoom.
		foreach ( array( 'advtr_data_inizio', 'advtr_data_fine' ) as $campo ) {
			if ( ! isset( $_POST[ $campo ] ) ) {
				continue;
			}
			$valore = sanitize_text_field( wp_unslash( $_POST[ $campo ] ) );
			if ( '' === $valore ) {
				delete_post_meta( $post_id, $campo );
			} else {
				update_post_meta( $post_id, $campo, $valore );
			}
		}

		if ( isset( $_POST['advtr_zoom_min'] ) ) {
			$zoom = sanitize_text_field( wp_unslash( $_POST['advtr_zoom_min'] ) );
			if ( '' === $zoom ) {
				delete_post_meta( $post_id, 'advtr_zoom_min' );
			} else {
				update_post_meta( $post_id, 'advtr_zoom_min', max( 0, min( 22, (int) $zoom ) ) );
			}
		}

		// Piano In Evidenza.
		$evidenza = ! empty( $_POST['advtr_in_evidenza'] );
		update_post_meta( $post_id, 'advtr_in_evidenza', $evidenza ? 1 : 0 );

		foreach ( array( 'advtr_evidenza_inizio', 'advtr_evidenza_fine' ) as $campo ) {
			if ( ! isset( $_POST[ $campo ] ) ) {
				continue;
			}
			$valore = sanitize_text_field( wp_unslash( $_POST[ $campo ] ) );
			if ( '' === $valore ) {
				delete_post_meta( $post_id, $campo );
			} else {
				update_post_meta( $post_id, $campo, $valore );
			}
		}

		if ( isset( $_POST['advtr_evidenza_priorita'] ) ) {
			$pri = absint( wp_unslash( $_POST['advtr_evidenza_priorita'] ) );
			update_post_meta( $post_id, 'advtr_evidenza_priorita', $pri );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return 'scheda_ok';
	}
}
