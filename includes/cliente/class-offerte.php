<?php
/**
 * Offerte gestite dal cliente nell'area riservata (§2.3).
 *
 * Il cliente crea, modifica ed elimina le proprie offerte, sempre collegate a
 * una scheda di cui è autore. Le offerte vanno online subito: la moderazione
 * dell'amministratore è a posteriori.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Cliente;

use AdverTrieste\Cpt\Offerta;
use AdverTrieste\Cpt\Locale;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD delle offerte da front-end.
 */
class Offerte {

	/**
	 * Offerte del cliente corrente.
	 *
	 * @return \WP_Post[]
	 */
	public static function mie() {
		return get_posts(
			array(
				'post_type'      => Offerta::POST_TYPE,
				'post_status'    => array( 'publish', 'pending', 'draft' ),
				'posts_per_page' => 50,
				'author'         => get_current_user_id(),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Crea o aggiorna un'offerta.
	 *
	 * @return string Codice di esito.
	 */
	public static function salva() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce già verificato dal controller.
		$offerta_id = isset( $_POST['advtr_offerta_id'] ) ? absint( wp_unslash( $_POST['advtr_offerta_id'] ) ) : 0;
		$locale_id  = isset( $_POST['advtr_locale_id'] ) ? absint( wp_unslash( $_POST['advtr_locale_id'] ) ) : 0;
		$titolo     = isset( $_POST['advtr_titolo'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_titolo'] ) ) : '';
		$testo      = isset( $_POST['advtr_descrizione'] ) ? wp_kses_post( wp_unslash( $_POST['advtr_descrizione'] ) ) : '';
		$inizio     = isset( $_POST['advtr_data_inizio'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_data_inizio'] ) ) : '';
		$scadenza   = isset( $_POST['advtr_data_scadenza'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_data_scadenza'] ) ) : '';
		$tipo       = isset( $_POST['advtr_tipo_coupon'] ) ? sanitize_key( wp_unslash( $_POST['advtr_tipo_coupon'] ) ) : 'codice';
		$codice     = isset( $_POST['advtr_codice'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_codice'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// L'offerta deve agganciarsi a una scheda dell'utente.
		$locale = $locale_id ? get_post( $locale_id ) : null;
		if ( ! $locale || Locale::POST_TYPE !== $locale->post_type || ! current_user_can( 'edit_post', $locale_id ) ) {
			return 'negato';
		}
		if ( '' === $titolo ) {
			return 'negato';
		}

		// Coerenza delle date: una finestra al contrario non è salvabile.
		$ts_inizio   = $inizio ? strtotime( $inizio ) : 0;
		$ts_scadenza = $scadenza ? strtotime( $scadenza ) : 0;
		if ( $ts_inizio && $ts_scadenza && $ts_scadenza <= $ts_inizio ) {
			return 'offerta_date';
		}

		if ( $offerta_id ) {
			$esistente = get_post( $offerta_id );
			if ( ! $esistente || Offerta::POST_TYPE !== $esistente->post_type || ! current_user_can( 'edit_post', $offerta_id ) ) {
				return 'negato';
			}
			wp_update_post(
				array(
					'ID'           => $offerta_id,
					'post_title'   => $titolo,
					'post_content' => $testo,
				)
			);
		} else {
			$offerta_id = wp_insert_post(
				array(
					'post_type'    => Offerta::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $titolo,
					'post_content' => $testo,
					'post_author'  => get_current_user_id(),
				),
				true
			);
			if ( is_wp_error( $offerta_id ) ) {
				return 'negato';
			}
		}

		update_post_meta( $offerta_id, 'advtr_locale_id', $locale_id );
		update_post_meta( $offerta_id, 'advtr_tipo_coupon', 'qr' === $tipo ? 'qr' : 'codice' );
		update_post_meta( $offerta_id, 'advtr_codice', $codice );
		update_post_meta( $offerta_id, 'advtr_data_inizio', $ts_inizio ? gmdate( 'Y-m-d H:i:s', $ts_inizio ) : '' );
		update_post_meta( $offerta_id, 'advtr_data_scadenza', $ts_scadenza ? gmdate( 'Y-m-d H:i:s', $ts_scadenza ) : '' );
		// Una modifica riapre l'offerta: lo stato "scaduto" lo rimette il cron.
		delete_post_meta( $offerta_id, 'advtr_stato' );

		return 'offerta_ok';
	}

	/**
	 * Elimina un'offerta del cliente.
	 *
	 * @return string Codice di esito.
	 */
	public static function elimina() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce già verificato dal controller.
		$offerta_id = isset( $_POST['advtr_offerta_id'] ) ? absint( wp_unslash( $_POST['advtr_offerta_id'] ) ) : 0;

		$offerta = $offerta_id ? get_post( $offerta_id ) : null;
		if ( ! $offerta || Offerta::POST_TYPE !== $offerta->post_type || ! current_user_can( 'delete_post', $offerta_id ) ) {
			return 'negato';
		}
		wp_trash_post( $offerta_id );

		return 'offerta_del';
	}
}
