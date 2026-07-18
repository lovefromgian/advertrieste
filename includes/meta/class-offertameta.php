<?php
/**
 * Meta e meta box del CPT `offerta`.
 *
 * Collega l'offerta a un `locale`, definisce la finestra temporale (inizio/
 * scadenza), il tipo di coupon (codice o QR) e il codice da validare. I meta non
 * sono esposti in REST core: le offerte pubbliche passano da `Rest\Offerte`.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Meta;

use AdverTrieste\Cpt\Offerta;
use AdverTrieste\Cpt\Locale;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestione dei meta del post type `offerta`.
 */
class OffertaMeta {

	/**
	 * Azione del nonce.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'advtr_save_offerta_meta';

	/**
	 * Nome del campo nonce.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'advtr_offerta_meta_nonce';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_fields' ) );
		add_action( 'add_meta_boxes_' . Offerta::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . Offerta::POST_TYPE, array( __CLASS__, 'save' ) );
	}

	/**
	 * Registra i meta (non in REST core).
	 *
	 * @return void
	 */
	public static function register_fields() {
		$auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};
		$keys = array(
			'advtr_locale_id'     => 'integer',
			'advtr_data_inizio'   => 'string',
			'advtr_data_scadenza' => 'string',
			'advtr_tipo_coupon'   => 'string',
			'advtr_codice'        => 'string',
			'advtr_stato'         => 'string',
		);
		foreach ( $keys as $key => $type ) {
			register_post_meta(
				Offerta::POST_TYPE,
				$key,
				array(
					'single'        => true,
					'type'          => $type,
					'show_in_rest'  => false,
					'auth_callback' => $auth,
				)
			);
		}
	}

	/**
	 * Registra il meta box.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		add_meta_box(
			'advtr_offerta_dati',
			__( 'Dati offerta', 'advertrieste' ),
			array( __CLASS__, 'render' ),
			Offerta::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Disegna il meta box.
	 *
	 * @param \WP_Post $post Post corrente.
	 * @return void
	 */
	public static function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$locale_id = (int) get_post_meta( $post->ID, 'advtr_locale_id', true );
		$inizio    = self::to_input( get_post_meta( $post->ID, 'advtr_data_inizio', true ) );
		$scadenza  = self::to_input( get_post_meta( $post->ID, 'advtr_data_scadenza', true ) );
		$tipo      = get_post_meta( $post->ID, 'advtr_tipo_coupon', true );
		$codice    = get_post_meta( $post->ID, 'advtr_codice', true );

		$locali = get_posts(
			array(
				'post_type'      => Locale::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$tipi   = array(
			'codice' => __( 'Codice', 'advertrieste' ),
			'qr'     => __( 'QR', 'advertrieste' ),
		);

		require ADVTR_PATH . 'templates/admin/offerta-meta-box.php';
	}

	/**
	 * Salva i meta.
	 *
	 * @param int $post_id ID del post.
	 * @return void
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$locale_id = isset( $_POST['advtr_locale_id'] ) ? absint( wp_unslash( $_POST['advtr_locale_id'] ) ) : 0;
		self::update_or_delete( $post_id, 'advtr_locale_id', $locale_id );

		$inizio = isset( $_POST['advtr_data_inizio'] ) ? self::to_mysql( sanitize_text_field( wp_unslash( $_POST['advtr_data_inizio'] ) ) ) : '';
		self::update_or_delete( $post_id, 'advtr_data_inizio', $inizio );

		$scadenza = isset( $_POST['advtr_data_scadenza'] ) ? self::to_mysql( sanitize_text_field( wp_unslash( $_POST['advtr_data_scadenza'] ) ) ) : '';
		self::update_or_delete( $post_id, 'advtr_data_scadenza', $scadenza );

		$tipo = isset( $_POST['advtr_tipo_coupon'] ) ? sanitize_key( wp_unslash( $_POST['advtr_tipo_coupon'] ) ) : '';
		self::update_or_delete( $post_id, 'advtr_tipo_coupon', in_array( $tipo, array( 'codice', 'qr' ), true ) ? $tipo : '' );

		$codice = isset( $_POST['advtr_codice'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_codice'] ) ) : '';
		self::update_or_delete( $post_id, 'advtr_codice', $codice );

		// Se si modifica la scadenza a data futura, riattiva l'offerta.
		if ( $scadenza && $scadenza > current_time( 'mysql' ) ) {
			delete_post_meta( $post_id, 'advtr_stato' );
		}
	}

	/**
	 * Aggiorna o elimina un meta se vuoto.
	 *
	 * @param int    $post_id ID del post.
	 * @param string $key     Chiave meta.
	 * @param mixed  $value   Valore.
	 * @return void
	 */
	private static function update_or_delete( $post_id, $key, $value ) {
		if ( '' === $value || 0 === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Converte un valore datetime-local (Y-m-d\TH:i) in datetime MySQL.
	 *
	 * @param string $value Valore dal form.
	 * @return string 'Y-m-d H:i:s' o '' se non valido.
	 */
	private static function to_mysql( $value ) {
		if ( '' === $value ) {
			return '';
		}
		$value = str_replace( 'T', ' ', $value );
		$ts    = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
	}

	/**
	 * Converte un datetime MySQL nel formato dell'input datetime-local.
	 *
	 * @param string $value 'Y-m-d H:i:s'.
	 * @return string 'Y-m-d\TH:i' o ''.
	 */
	private static function to_input( $value ) {
		if ( '' === $value ) {
			return '';
		}
		$ts = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d\TH:i', $ts ) : '';
	}
}
