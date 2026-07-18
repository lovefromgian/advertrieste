<?php
/**
 * Meta e meta box del CPT `evento`.
 *
 * Campi dell'evento (tipo, date, locali collegati) e box del workflow di
 * revisione (stato + azioni Invia/Approva, che chiamano gli endpoint REST).
 * Al salvataggio, un evento già pubblicato/in revisione torna in bozza: le
 * modifiche devono essere ri-approvate prima di andare online.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Meta;

use AdverTrieste\Cpt\Evento;
use AdverTrieste\Cpt\Locale;
use AdverTrieste\Evento\Workflow;
use AdverTrieste\Access\Roles;
use AdverTrieste\Rest\Markers;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestione dei meta e del workflow del post type `evento`.
 */
class EventoMeta {

	const NONCE_ACTION = 'advtr_save_evento_meta';
	const NONCE_NAME   = 'advtr_evento_meta_nonce';
	const HANDLE       = 'advtr-evento-workflow';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_fields' ) );
		add_action( 'add_meta_boxes_' . Evento::POST_TYPE, array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Evento::POST_TYPE, array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
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
			'advtr_tipo_evento'      => 'string',
			'advtr_data_inizio'      => 'string',
			'advtr_data_fine'        => 'string',
			'advtr_locali_collegati' => 'array',
			Workflow::META_STATO     => 'string',
		);
		foreach ( $keys as $key => $type ) {
			register_post_meta(
				Evento::POST_TYPE,
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
	 * Registra i meta box (dati + workflow).
	 *
	 * @return void
	 */
	public static function add_meta_boxes() {
		add_meta_box( 'advtr_evento_dati', __( 'Dati evento', 'advertrieste' ), array( __CLASS__, 'render_dati' ), Evento::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'advtr_evento_workflow', __( 'Workflow revisione', 'advertrieste' ), array( __CLASS__, 'render_workflow' ), Evento::POST_TYPE, 'side', 'high' );
	}

	/**
	 * Meta box dei dati evento.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function render_dati( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$tipo      = get_post_meta( $post->ID, 'advtr_tipo_evento', true );
		$inizio    = self::to_input( get_post_meta( $post->ID, 'advtr_data_inizio', true ) );
		$fine      = self::to_input( get_post_meta( $post->ID, 'advtr_data_fine', true ) );
		$collegati = get_post_meta( $post->ID, 'advtr_locali_collegati', true );
		$collegati = is_array( $collegati ) ? array_map( 'absint', $collegati ) : array();
		$tipi      = array(
			'grande'        => __( 'Grande evento', 'advertrieste' ),
			'organizzatore' => __( 'Organizzatore terzo', 'advertrieste' ),
		);
		$locali    = get_posts(
			array(
				'post_type'      => Locale::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		require ADVTR_PATH . 'templates/admin/evento-meta-box.php';
	}

	/**
	 * Meta box del workflow (stato + azioni).
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function render_workflow( $post ) {
		$stato      = Workflow::stato( $post->ID );
		$pubblicata = Workflow::is_published( $post->ID );
		require ADVTR_PATH . 'templates/admin/evento-workflow-box.php';
	}

	/**
	 * Salva i meta dell'evento.
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

		$tipo = isset( $_POST['advtr_tipo_evento'] ) ? sanitize_key( wp_unslash( $_POST['advtr_tipo_evento'] ) ) : '';
		update_post_meta( $post_id, 'advtr_tipo_evento', in_array( $tipo, array( 'grande', 'organizzatore' ), true ) ? $tipo : 'organizzatore' );

		$inizio = isset( $_POST['advtr_data_inizio'] ) ? self::to_mysql( sanitize_text_field( wp_unslash( $_POST['advtr_data_inizio'] ) ) ) : '';
		self::update_or_delete( $post_id, 'advtr_data_inizio', $inizio );

		$fine = isset( $_POST['advtr_data_fine'] ) ? self::to_mysql( sanitize_text_field( wp_unslash( $_POST['advtr_data_fine'] ) ) ) : '';
		self::update_or_delete( $post_id, 'advtr_data_fine', $fine );

		$collegati = isset( $_POST['advtr_locali_collegati'] )
			? array_map( 'absint', (array) wp_unslash( $_POST['advtr_locali_collegati'] ) )
			: array();
		$collegati = array_values( array_filter( $collegati ) );
		if ( $collegati ) {
			update_post_meta( $post_id, 'advtr_locali_collegati', $collegati );
		} else {
			delete_post_meta( $post_id, 'advtr_locali_collegati' );
		}

		// Le modifiche a un evento pubblicato/in revisione tornano in bozza:
		// devono essere ri-approvate prima di andare online.
		Workflow::mark_dirty( $post_id );
	}

	/**
	 * Carica lo script del workflow nella schermata dell'evento.
	 *
	 * @param string $hook Hook della schermata.
	 * @return void
	 */
	public static function enqueue_admin( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || Evento::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script( self::HANDLE, ADVTR_URL . 'assets/src/admin/evento-workflow.js', array(), ADVTR_VERSION, true );
		wp_localize_script(
			self::HANDLE,
			'advtrEventoWf',
			array(
				'base'       => rest_url( Markers::NAMESPACE . '/evento/' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'canApprove' => current_user_can( Roles::CAP_APPROVE_EVENTO ),
				'i18n'       => array(
					'inviato'   => __( 'Inviato in revisione.', 'advertrieste' ),
					'approvato' => __( 'Evento approvato e pubblicato.', 'advertrieste' ),
					'errore'    => __( 'Operazione non riuscita.', 'advertrieste' ),
				),
			)
		);
	}

	/**
	 * Aggiorna o elimina un meta se vuoto.
	 *
	 * @param int    $post_id ID.
	 * @param string $key     Chiave.
	 * @param string $value   Valore.
	 * @return void
	 */
	private static function update_or_delete( $post_id, $key, $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Converte datetime-local in datetime MySQL.
	 *
	 * @param string $value Valore.
	 * @return string
	 */
	private static function to_mysql( $value ) {
		if ( '' === $value ) {
			return '';
		}
		$ts = strtotime( str_replace( 'T', ' ', $value ) );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
	}

	/**
	 * Converte datetime MySQL nel formato datetime-local.
	 *
	 * @param string $value Valore.
	 * @return string
	 */
	private static function to_input( $value ) {
		if ( '' === $value ) {
			return '';
		}
		$ts = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d\TH:i', $ts ) : '';
	}
}
