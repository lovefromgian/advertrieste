<?php
/**
 * Meta e meta box del CPT `punto_qr` (riservato).
 *
 * Campi minimi per posizionare un espositore/QR: coordinate e stato. L'etichetta
 * coincide con il titolo del post. I meta NON sono esposti in REST (il CPT è
 * non pubblico e privo di supporto `custom-fields`): le coordinate lasciano il
 * server solo tramite l'endpoint autenticato `/qr-map`.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Meta;

use AdverTrieste\Cpt\PuntoQr;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestione dei meta del post type `punto_qr`.
 */
class PuntoQrMeta {

	/**
	 * Azione del nonce.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'advtr_save_puntoqr_meta';

	/**
	 * Nome del campo nonce.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'advtr_puntoqr_meta_nonce';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_fields' ) );
		add_action( 'add_meta_boxes_' . PuntoQr::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . PuntoQr::POST_TYPE, array( __CLASS__, 'save' ) );
	}

	/**
	 * Registra i meta (mai in REST core: dati riservati).
	 *
	 * @return void
	 */
	public static function register_fields() {
		$auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		register_post_meta(
			PuntoQr::POST_TYPE,
			'advtr_lat',
			array(
				'single'            => true,
				'type'              => 'number',
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) {
					return ( '' === $value || null === $value ) ? '' : (float) $value;
				},
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			PuntoQr::POST_TYPE,
			'advtr_lng',
			array(
				'single'            => true,
				'type'              => 'number',
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) {
					return ( '' === $value || null === $value ) ? '' : (float) $value;
				},
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			PuntoQr::POST_TYPE,
			'advtr_stato',
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
			'advtr_puntoqr_dati',
			__( 'Posizione QR', 'advertrieste' ),
			array( __CLASS__, 'render' ),
			PuntoQr::POST_TYPE,
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

		$lat   = get_post_meta( $post->ID, 'advtr_lat', true );
		$lng   = get_post_meta( $post->ID, 'advtr_lng', true );
		$stato = get_post_meta( $post->ID, 'advtr_stato', true );
		$stati = array(
			'attivo'   => __( 'Attivo', 'advertrieste' ),
			'inattivo' => __( 'Inattivo', 'advertrieste' ),
		);
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
			<label for="advtr_stato"><strong><?php esc_html_e( 'Stato', 'advertrieste' ); ?></strong></label><br />
			<select id="advtr_stato" name="advtr_stato">
				<?php foreach ( $stati as $valore => $etichetta ) : ?>
					<option value="<?php echo esc_attr( $valore ); ?>" <?php selected( $stato, $valore ); ?>>
						<?php echo esc_html( $etichetta ); ?>
					</option>
				<?php endforeach; ?>
			</select>
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

		$lat = isset( $_POST['advtr_lat'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_lat'] ) ) : '';
		$lng = isset( $_POST['advtr_lng'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_lng'] ) ) : '';

		self::save_coord( $post_id, 'advtr_lat', $lat );
		self::save_coord( $post_id, 'advtr_lng', $lng );

		$stato = isset( $_POST['advtr_stato'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_stato'] ) ) : '';
		if ( in_array( $stato, array( 'attivo', 'inattivo' ), true ) ) {
			update_post_meta( $post_id, 'advtr_stato', $stato );
		} else {
			delete_post_meta( $post_id, 'advtr_stato' );
		}
	}

	/**
	 * Salva una coordinata (float) o la elimina se vuota.
	 *
	 * @param int    $post_id ID del post.
	 * @param string $key     Chiave meta.
	 * @param string $value   Valore grezzo.
	 * @return void
	 */
	private static function save_coord( $post_id, $key, $value ) {
		if ( '' === $value || ! is_numeric( $value ) ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, (float) $value );
	}
}
