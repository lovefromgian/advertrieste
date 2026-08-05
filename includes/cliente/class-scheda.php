<?php
/**
 * Salvataggio della scheda dall'area clienti.
 *
 * Il cliente modifica solo i campi che gli competono: contenuti, contatti,
 * orari, servizi, categorie e posizione. I campi commerciali e tecnici
 * (validità, badge "in evidenza", soglia di zoom, contatore visite) restano
 * all'amministratore e non sono nemmeno presenti nel form: non basta nasconderli
 * lato interfaccia, qui vengono semplicemente ignorati se arrivassero comunque.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Cliente;

use AdverTrieste\Cpt\Locale;
use AdverTrieste\Cpt\Categoria;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scrittura della scheda locale da front-end.
 */
class Scheda {

	/**
	 * Campi testuali modificabili dal cliente: meta => tipo di sanitizzazione.
	 *
	 * @var array<string,string>
	 */
	const CAMPI = array(
		'advtr_telefono'  => 'text',
		'advtr_email'     => 'email',
		'advtr_sito'      => 'url',
		'advtr_indirizzo' => 'text',
		'advtr_orari'     => 'textarea',
		'advtr_place_id'  => 'text',
	);

	/**
	 * Salva la scheda inviata dal form.
	 *
	 * @return string Codice di esito per l'avviso.
	 */
	public static function salva() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce già verificato dal controller.
		$post_id = isset( $_POST['advtr_locale_id'] ) ? absint( wp_unslash( $_POST['advtr_locale_id'] ) ) : 0;

		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || Locale::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return 'negato';
		}

		// Titolo e descrizione.
		$titolo    = isset( $_POST['advtr_titolo'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_titolo'] ) ) : '';
		$contenuto = isset( $_POST['advtr_descrizione'] ) ? wp_kses_post( wp_unslash( $_POST['advtr_descrizione'] ) ) : '';

		if ( '' !== $titolo ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_title'   => $titolo,
					'post_content' => $contenuto,
				)
			);
		}

		// Contatti e orari.
		foreach ( self::CAMPI as $meta => $tipo ) {
			if ( ! isset( $_POST[ $meta ] ) ) {
				continue;
			}
			$grezzo = wp_unslash( $_POST[ $meta ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizzato subito sotto.
			$valore = self::sanitizza( $grezzo, $tipo );
			if ( '' === $valore ) {
				delete_post_meta( $post_id, $meta );
			} else {
				update_post_meta( $post_id, $meta, $valore );
			}
		}

		// Servizi: una voce per riga.
		if ( isset( $_POST['advtr_servizi'] ) ) {
			$righe   = preg_split( '/\r\n|\r|\n/', (string) wp_unslash( $_POST['advtr_servizi'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizzato subito sotto.
			$servizi = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', (array) $righe ) ) ) );
			update_post_meta( $post_id, 'advtr_servizi', $servizi );
		}

		// Posizione sulla mappa.
		foreach ( array( 'advtr_lat', 'advtr_lng' ) as $coord ) {
			if ( ! isset( $_POST[ $coord ] ) ) {
				continue;
			}
			$val = sanitize_text_field( wp_unslash( $_POST[ $coord ] ) );
			if ( '' === $val || ! is_numeric( $val ) ) {
				continue;
			}
			update_post_meta( $post_id, $coord, (float) $val );
		}

		// Categorie: solo termini esistenti della tassonomia condivisa.
		if ( isset( $_POST['advtr_categorie'] ) && is_array( $_POST['advtr_categorie'] ) ) {
			$slugs  = array_map( 'sanitize_key', wp_unslash( $_POST['advtr_categorie'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizzato con array_map.
			$validi = array();
			foreach ( $slugs as $slug ) {
				if ( term_exists( $slug, Categoria::TAXONOMY ) ) {
					$validi[] = $slug;
				}
			}
			wp_set_object_terms( $post_id, $validi, Categoria::TAXONOMY );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return 'scheda_ok';
	}

	/**
	 * Applica la sanitizzazione del tipo indicato.
	 *
	 * @param mixed  $valore Valore grezzo.
	 * @param string $tipo   Tipo di campo.
	 * @return string
	 */
	private static function sanitizza( $valore, $tipo ) {
		switch ( $tipo ) {
			case 'email':
				return sanitize_email( (string) $valore );
			case 'url':
				return esc_url_raw( (string) $valore );
			case 'textarea':
				return sanitize_textarea_field( (string) $valore );
			default:
				return sanitize_text_field( (string) $valore );
		}
	}
}
