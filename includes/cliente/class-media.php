<?php
/**
 * Logo e galleria dall'area clienti.
 *
 * Upload in front-end senza mai esporre la libreria media del sito: il cliente
 * carica i propri file e può rimuovere solo quelli di cui è autore e che sono
 * collegati alla sua scheda. WordPress da solo non limita la libreria per
 * autore, quindi il filtro va fatto qui (e in `Access\AdminLock` per la bacheca).
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Cliente;

use AdverTrieste\Cpt\Locale;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestione immagini della scheda da front-end.
 */
class Media {

	/**
	 * Tipi MIME accettati.
	 *
	 * @var string[]
	 */
	const MIME_AMMESSI = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );

	/**
	 * Dimensione massima del file, in byte.
	 *
	 * @var int
	 */
	const MAX_BYTE = 5242880; // 5 MB.

	/**
	 * Numero massimo di foto in galleria.
	 *
	 * @var int
	 */
	const MAX_GALLERIA = 12;

	/**
	 * Carica un'immagine e la assegna a logo o galleria.
	 *
	 * @return string Codice di esito.
	 */
	public static function carica() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce già verificato dal controller.
		$post_id = isset( $_POST['advtr_locale_id'] ) ? absint( wp_unslash( $_POST['advtr_locale_id'] ) ) : 0;
		$ruolo   = isset( $_POST['advtr_ruolo'] ) ? sanitize_key( wp_unslash( $_POST['advtr_ruolo'] ) ) : 'galleria';

		if ( ! self::puo_gestire( $post_id ) ) {
			return 'negato';
		}
		if ( empty( $_FILES['advtr_file'] ) || ! isset( $_FILES['advtr_file']['tmp_name'] ) ) {
			return 'immagine_ko';
		}

		// Validazione del file PRIMA di consegnarlo a WordPress.
		$tmp = sanitize_text_field( wp_unslash( $_FILES['advtr_file']['tmp_name'] ) );
		$dim = isset( $_FILES['advtr_file']['size'] ) ? (int) $_FILES['advtr_file']['size'] : 0;
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) || $dim <= 0 || $dim > self::MAX_BYTE ) {
			return 'immagine_ko';
		}
		$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- file non valido: gestito sotto.
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $info || ! in_array( $info['mime'], self::MIME_AMMESSI, true ) ) {
			return 'immagine_ko';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$allegato_id = media_handle_upload(
			'advtr_file',
			$post_id,
			array(),
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'webp'     => 'image/webp',
					'gif'      => 'image/gif',
				),
			)
		);

		if ( is_wp_error( $allegato_id ) ) {
			return 'immagine_ko';
		}

		if ( 'logo' === $ruolo ) {
			update_post_meta( $post_id, 'advtr_logo_id', (int) $allegato_id );
			return 'immagine_ok';
		}

		$galleria = self::galleria( $post_id );
		if ( count( $galleria ) >= self::MAX_GALLERIA ) {
			wp_delete_attachment( $allegato_id, true );
			return 'immagine_ko';
		}
		$galleria[] = (int) $allegato_id;
		update_post_meta( $post_id, 'advtr_galleria_ids', $galleria );

		return 'immagine_ok';
	}

	/**
	 * Rimuove un'immagine da logo o galleria (e cancella il file se è suo).
	 *
	 * @return string Codice di esito.
	 */
	public static function elimina() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce già verificato dal controller.
		$post_id     = isset( $_POST['advtr_locale_id'] ) ? absint( wp_unslash( $_POST['advtr_locale_id'] ) ) : 0;
		$allegato_id = isset( $_POST['advtr_allegato_id'] ) ? absint( wp_unslash( $_POST['advtr_allegato_id'] ) ) : 0;
		$ruolo       = isset( $_POST['advtr_ruolo'] ) ? sanitize_key( wp_unslash( $_POST['advtr_ruolo'] ) ) : 'galleria';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! self::puo_gestire( $post_id ) || ! $allegato_id ) {
			return 'negato';
		}

		// Deve essere un'immagine effettivamente collegata a questa scheda.
		$logo     = (int) get_post_meta( $post_id, 'advtr_logo_id', true );
		$galleria = self::galleria( $post_id );
		if ( $allegato_id !== $logo && ! in_array( $allegato_id, $galleria, true ) ) {
			return 'negato';
		}

		if ( 'logo' === $ruolo && $allegato_id === $logo ) {
			delete_post_meta( $post_id, 'advtr_logo_id' );
		} else {
			update_post_meta( $post_id, 'advtr_galleria_ids', array_values( array_diff( $galleria, array( $allegato_id ) ) ) );
		}

		// Cancella il file solo se l'ha caricato lui: non tocchiamo i media altrui.
		$allegato = get_post( $allegato_id );
		if ( $allegato && get_current_user_id() === (int) $allegato->post_author ) {
			wp_delete_attachment( $allegato_id, true );
		}

		return 'immagine_del';
	}

	/**
	 * Galleria corrente come lista di ID.
	 *
	 * @param int $post_id ID della scheda.
	 * @return int[]
	 */
	public static function galleria( $post_id ) {
		$ids = get_post_meta( $post_id, 'advtr_galleria_ids', true );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	/**
	 * L'utente corrente può gestire le immagini di questa scheda?
	 *
	 * @param int $post_id ID della scheda.
	 * @return bool
	 */
	private static function puo_gestire( $post_id ) {
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || Locale::POST_TYPE !== $post->post_type ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id ) && current_user_can( 'upload_files' );
	}
}
