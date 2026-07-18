<?php
/**
 * Meta e meta box del CPT `poi` (punto d'interesse).
 *
 * Campi minimi per posizionare il POI sulla mappa: coordinate, soglia di zoom e
 * tipo. La descrizione usa l'editor nativo (`post_content`). Senza questi campi
 * un POI non può comparire sulla mappa (l'endpoint `/map/markers` filtra per
 * coordinate).
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Meta;

use AdverTrieste\Cpt\Poi;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestione dei meta del post type `poi`.
 */
class PoiMeta {

	const NONCE_ACTION = 'advtr_save_poi_meta';
	const NONCE_NAME   = 'advtr_poi_meta_nonce';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_fields' ) );
		add_action( 'add_meta_boxes_' . Poi::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . Poi::POST_TYPE, array( __CLASS__, 'save' ) );
	}

	/**
	 * Registra i meta. Coordinate e zoom esposti in REST (dati pubblici della mappa).
	 *
	 * @return void
	 */
	public static function register_fields() {
		$auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		$num = static function ( $value ) {
			return ( '' === $value || null === $value ) ? '' : (float) $value;
		};

		register_post_meta(
			Poi::POST_TYPE,
			'advtr_lat',
			array(
				'single'            => true,
				'type'              => 'number',
				'show_in_rest'      => true,
				'sanitize_callback' => $num,
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			Poi::POST_TYPE,
			'advtr_lng',
			array(
				'single'            => true,
				'type'              => 'number',
				'show_in_rest'      => true,
				'sanitize_callback' => $num,
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			Poi::POST_TYPE,
			'advtr_zoom_min',
			array(
				'single'            => true,
				'type'              => 'integer',
				'show_in_rest'      => true,
				'sanitize_callback' => static function ( $value ) {
					return ( '' === $value || null === $value ) ? '' : (int) $value;
				},
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			Poi::POST_TYPE,
			'advtr_tipo',
			array(
				'single'            => true,
				'type'              => 'string',
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
			)
		);
	}

	/**
	 * Registra il meta box.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		add_meta_box(
			'advtr_poi_dati',
			__( 'Dati punto d\'interesse', 'advertrieste' ),
			array( __CLASS__, 'render' ),
			Poi::POST_TYPE,
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

		$lat      = get_post_meta( $post->ID, 'advtr_lat', true );
		$lng      = get_post_meta( $post->ID, 'advtr_lng', true );
		$zoom_min = get_post_meta( $post->ID, 'advtr_zoom_min', true );
		$tipo     = get_post_meta( $post->ID, 'advtr_tipo', true );
		?>
		<p>
			<label for="advtr_lat"><strong><?php esc_html_e( 'Latitudine', 'advertrieste' ); ?></strong></label><br />
			<input type="number" step="any" id="advtr_lat" name="advtr_lat" value="<?php echo esc_attr( $lat ); ?>" />
		</p>
		<p>
			<label for="advtr_lng"><strong><?php esc_html_e( 'Longitudine', 'advertrieste' ); ?></strong></label><br />
			<input type="number" step="any" id="advtr_lng" name="advtr_lng" value="<?php echo esc_attr( $lng ); ?>" />
		</p>
		<p>
			<label for="advtr_zoom_min"><strong><?php esc_html_e( 'Zoom minimo (visibilità sulla mappa)', 'advertrieste' ); ?></strong></label><br />
			<input type="number" step="1" min="0" max="22" id="advtr_zoom_min" name="advtr_zoom_min" value="<?php echo esc_attr( $zoom_min ); ?>" />
			<span class="description"><?php esc_html_e( 'I POI hanno tipicamente soglia bassa: visibili anche da lontano.', 'advertrieste' ); ?></span>
		</p>
		<p>
			<label for="advtr_tipo"><strong><?php esc_html_e( 'Tipo', 'advertrieste' ); ?></strong></label><br />
			<input type="text" id="advtr_tipo" name="advtr_tipo" value="<?php echo esc_attr( $tipo ); ?>" placeholder="<?php esc_attr_e( 'museo, castello, monumento…', 'advertrieste' ); ?>" />
		</p>
		<?php
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

		foreach ( array( 'advtr_lat', 'advtr_lng' ) as $key ) {
			$raw = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			if ( '' === $raw || ! is_numeric( $raw ) ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, (float) $raw );
			}
		}

		$zoom = isset( $_POST['advtr_zoom_min'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_zoom_min'] ) ) : '';
		if ( '' === $zoom ) {
			delete_post_meta( $post_id, 'advtr_zoom_min' );
		} else {
			update_post_meta( $post_id, 'advtr_zoom_min', (int) $zoom );
		}

		$tipo = isset( $_POST['advtr_tipo'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_tipo'] ) ) : '';
		if ( '' === $tipo ) {
			delete_post_meta( $post_id, 'advtr_tipo' );
		} else {
			update_post_meta( $post_id, 'advtr_tipo', $tipo );
		}
	}
}
