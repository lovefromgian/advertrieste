<?php
/**
 * Meta e meta box del CPT `locale`.
 *
 * Registra i campi (`register_post_meta`), disegna il meta box "Dati locale" in
 * bacheca e ne gestisce il salvataggio (nonce + sanitizzazione). La descrizione
 * lunga usa l'editor nativo (`post_content`), quindi non è duplicata come meta.
 *
 * Alcuni campi non sono esposti in REST perché interni o riservati
 * (`place_id`, `visite_reali`, `visite_soglia_raggiunta`, validità abbonamento):
 * verranno serviti da endpoint dedicati con controllo di capability.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Meta;

use AdverTrieste\Cpt\Locale;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestione dei meta del post type `locale`.
 */
class LocaleMeta {

	/**
	 * Azione del nonce del meta box.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'advtr_save_locale_meta';

	/**
	 * Nome del campo nonce.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'advtr_locale_meta_nonce';

	/**
	 * Aggancia gli hook necessari.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_fields' ) );
		add_action( 'add_meta_boxes_' . Locale::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . Locale::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
	}

	/**
	 * Definizione dei campi meta.
	 *
	 * Ogni voce: type (php cast), rest (esposto in REST core), sanitize (callable).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function fields() {
		return array(
			'lat'                     => array(
				'type' => 'number',
				'rest' => true,
			),
			'lng'                     => array(
				'type' => 'number',
				'rest' => true,
			),
			'zoom_min'                => array(
				'type' => 'integer',
				'rest' => true,
			),
			'data_inizio'             => array(
				'type' => 'date',
				'rest' => false,
			),
			'data_fine'               => array(
				'type' => 'date',
				'rest' => false,
			),
			'in_evidenza'             => array(
				'type' => 'boolean',
				'rest' => true,
			),
			'evidenza_inizio'         => array(
				'type' => 'date',
				'rest' => true,
			),
			'evidenza_fine'           => array(
				'type' => 'date',
				'rest' => true,
			),
			'evidenza_priorita'       => array(
				'type' => 'integer',
				'rest' => true,
			),
			'servizi'                 => array(
				'type' => 'string_list',
				'rest' => false,
			),
			'telefono'                => array(
				'type' => 'text',
				'rest' => false,
			),
			'email'                   => array(
				'type' => 'email',
				'rest' => false,
			),
			'sito'                    => array(
				'type' => 'url',
				'rest' => false,
			),
			'indirizzo'               => array(
				'type' => 'text',
				'rest' => false,
			),
			'orari'                   => array(
				'type' => 'textarea',
				'rest' => false,
			),
			'place_id'                => array(
				'type' => 'text',
				'rest' => false,
			),
			'logo_id'                 => array(
				'type' => 'attachment',
				'rest' => false,
			),
			'galleria_ids'            => array(
				'type' => 'int_list',
				'rest' => false,
			),
			'visite_reali'            => array(
				'type' => 'integer',
				'rest' => false,
			),
			'visite_soglia_raggiunta' => array(
				'type' => 'boolean',
				'rest' => false,
			),
		);
	}

	/**
	 * Registra tutti i meta del post type.
	 *
	 * @return void
	 */
	public static function register_fields() {
		foreach ( self::fields() as $key => $conf ) {
			$is_list = in_array( $conf['type'], array( 'string_list', 'int_list' ), true );

			$args = array(
				'single'            => true,
				'type'              => $is_list ? 'array' : self::rest_type( $conf['type'] ),
				'show_in_rest'      => false,
				'sanitize_callback' => self::sanitizer_for( $conf['type'] ),
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			);

			// I meta scalari esposti in REST hanno bisogno del tipo semplice.
			if ( $conf['rest'] && ! $is_list ) {
				$args['show_in_rest'] = true;
			}

			register_post_meta( Locale::POST_TYPE, 'advtr_' . $key, $args );
		}
	}

	/**
	 * Mappa il tipo interno sul tipo REST/meta di WordPress.
	 *
	 * @param string $type Tipo interno del campo.
	 * @return string
	 */
	private static function rest_type( $type ) {
		switch ( $type ) {
			case 'number':
				return 'number';
			case 'integer':
				return 'integer';
			case 'boolean':
				return 'boolean';
			default:
				return 'string';
		}
	}

	/**
	 * Restituisce la callback di sanitizzazione per il tipo indicato.
	 *
	 * @param string $type Tipo interno del campo.
	 * @return callable
	 */
	private static function sanitizer_for( $type ) {
		switch ( $type ) {
			case 'number':
				return static function ( $value ) {
					return ( '' === $value || null === $value ) ? '' : (float) $value;
				};
			case 'integer':
				return static function ( $value ) {
					return ( '' === $value || null === $value ) ? '' : (int) $value;
				};
			case 'boolean':
				return static function ( $value ) {
					return (bool) $value;
				};
			case 'date':
				return array( __CLASS__, 'sanitize_date' );
			case 'string_list':
				return static function ( $value ) {
					$value = is_array( $value ) ? $value : array();
					return array_values( array_filter( array_map( 'sanitize_text_field', $value ) ) );
				};
			case 'int_list':
				return static function ( $value ) {
					$value = is_array( $value ) ? $value : array();
					return array_values( array_filter( array_map( 'absint', $value ) ) );
				};
			case 'attachment':
				return 'absint';
			case 'email':
				return 'sanitize_email';
			case 'url':
				return 'esc_url_raw';
			case 'textarea':
				return 'sanitize_textarea_field';
			case 'text':
			default:
				return 'sanitize_text_field';
		}
	}

	/**
	 * Sanitizza una data nel formato Y-m-d; stringa vuota se non valida.
	 *
	 * @param string $value Valore grezzo.
	 * @return string
	 */
	public static function sanitize_date( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$date = \DateTime::createFromFormat( 'Y-m-d', $value );
		if ( $date && $date->format( 'Y-m-d' ) === $value ) {
			return $value;
		}
		return '';
	}

	/**
	 * Registra il meta box in bacheca.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		add_meta_box(
			'advtr_locale_dati',
			__( 'Dati locale', 'advertrieste' ),
			array( __CLASS__, 'render' ),
			Locale::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Disegna il contenuto del meta box.
	 *
	 * @param \WP_Post $post Post corrente.
	 * @return void
	 */
	public static function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$get = static function ( $key ) use ( $post ) {
			return get_post_meta( $post->ID, 'advtr_' . $key, true );
		};

		$lat               = $get( 'lat' );
		$lng               = $get( 'lng' );
		$zoom_min          = $get( 'zoom_min' );
		$data_inizio       = $get( 'data_inizio' );
		$data_fine         = $get( 'data_fine' );
		$in_evidenza       = (bool) $get( 'in_evidenza' );
		$evidenza_inizio   = $get( 'evidenza_inizio' );
		$evidenza_fine     = $get( 'evidenza_fine' );
		$evidenza_priorita = $get( 'evidenza_priorita' );
		$servizi           = $get( 'servizi' );
		$telefono          = $get( 'telefono' );
		$email             = $get( 'email' );
		$sito              = $get( 'sito' );
		$indirizzo         = $get( 'indirizzo' );
		$orari             = $get( 'orari' );
		$place_id          = $get( 'place_id' );
		$logo_id           = (int) $get( 'logo_id' );
		$galleria_ids      = $get( 'galleria_ids' );

		$servizi_text = is_array( $servizi ) ? implode( "\n", $servizi ) : '';
		$galleria_csv = is_array( $galleria_ids ) ? implode( ',', array_map( 'absint', $galleria_ids ) ) : '';
		$logo_url     = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';

		require ADVTR_PATH . 'templates/admin/locale-meta-box.php';
	}

	/**
	 * Salva i meta al salvataggio del post.
	 *
	 * @param int $post_id ID del post.
	 * @return void
	 */
	public static function save( $post_id ) {
		// Nonce.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		// Autosave / revisioni / capability.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// I campi interni (visite_*) non passano dal form: non li tocchiamo qui.
		$scalars = array(
			'lat'               => 'number',
			'lng'               => 'number',
			'zoom_min'          => 'integer',
			'data_inizio'       => 'date',
			'data_fine'         => 'date',
			'evidenza_inizio'   => 'date',
			'evidenza_fine'     => 'date',
			'evidenza_priorita' => 'integer',
			'telefono'          => 'text',
			'email'             => 'email',
			'sito'              => 'url',
			'indirizzo'         => 'text',
			'place_id'          => 'text',
			'logo_id'           => 'attachment',
		);

		foreach ( $scalars as $key => $type ) {
			// I valori del form sono scalari semplici: prima sanitizzazione base,
			// poi cast/validazione tipizzata specifica del campo.
			$raw       = isset( $_POST[ 'advtr_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'advtr_' . $key ] ) ) : '';
			$sanitizer = self::sanitizer_for( $type );
			$value     = call_user_func( $sanitizer, $raw );
			self::update_or_delete( $post_id, $key, $value );
		}

		// Checkbox in evidenza.
		$in_evidenza = isset( $_POST['advtr_in_evidenza'] ) ? 1 : 0;
		update_post_meta( $post_id, 'advtr_in_evidenza', $in_evidenza );

		// Servizi (textarea, una voce per riga).
		$servizi_raw = isset( $_POST['advtr_servizi'] ) ? sanitize_textarea_field( wp_unslash( $_POST['advtr_servizi'] ) ) : '';
		$servizi     = array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n/', $servizi_raw ) ) ) );
		self::update_or_delete( $post_id, 'servizi', $servizi );

		// Galleria (lista di ID separati da virgola).
		$galleria_raw = isset( $_POST['advtr_galleria_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_galleria_ids'] ) ) : '';
		$galleria     = array_values( array_filter( array_map( 'absint', explode( ',', $galleria_raw ) ) ) );
		self::update_or_delete( $post_id, 'galleria_ids', $galleria );

		// Orari (textarea multiriga).
		$orari = isset( $_POST['advtr_orari'] ) ? sanitize_textarea_field( wp_unslash( $_POST['advtr_orari'] ) ) : '';
		self::update_or_delete( $post_id, 'orari', $orari );
	}

	/**
	 * Aggiorna il meta oppure lo elimina se il valore è vuoto.
	 *
	 * @param int    $post_id ID del post.
	 * @param string $key     Chiave (senza prefisso).
	 * @param mixed  $value   Valore sanitizzato.
	 * @return void
	 */
	private static function update_or_delete( $post_id, $key, $value ) {
		$empty = ( '' === $value || null === $value || array() === $value || 0 === $value );
		if ( $empty ) {
			delete_post_meta( $post_id, 'advtr_' . $key );
			return;
		}
		update_post_meta( $post_id, 'advtr_' . $key, $value );
	}

	/**
	 * Carica media uploader e script del meta box nella schermata di `locale`.
	 *
	 * @param string $hook Hook della schermata admin corrente.
	 * @return void
	 */
	public static function enqueue_admin( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || Locale::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'advtr-locale-meta',
			ADVTR_URL . 'assets/src/admin/locale-meta.js',
			array( 'jquery' ),
			ADVTR_VERSION,
			true
		);
	}
}
