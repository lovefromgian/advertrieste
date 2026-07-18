<?php
/**
 * Front-end eventi.
 *
 * - `[advtr_grandi_eventi]` — banner grandi eventi con countdown e locali collegati.
 * - `[advtr_eventi]`        — elenco degli eventi approvati.
 *
 * Entrambi mostrano solo la versione pubblica (approvata) servita da `Rest\Eventi`.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Frontend;

use AdverTrieste\Rest\Markers;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode e asset degli eventi.
 */
class Eventi {

	/**
	 * Handle asset.
	 *
	 * @var string
	 */
	const HANDLE = 'advtr-eventi';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'advtr_grandi_eventi', array( __CLASS__, 'shortcode_grandi' ) );
		add_shortcode( 'advtr_eventi', array( __CLASS__, 'shortcode_eventi' ) );
	}

	/**
	 * Registra gli asset.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style( self::HANDLE, ADVTR_URL . 'assets/src/eventi/eventi.css', array(), ADVTR_VERSION );
		wp_register_script( self::HANDLE, ADVTR_URL . 'assets/src/eventi/eventi.js', array(), ADVTR_VERSION, true );
	}

	/**
	 * Accoda gli asset e localizza la configurazione (una sola volta).
	 *
	 * @return void
	 */
	private static function enqueue() {
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
		wp_localize_script(
			self::HANDLE,
			'advtrEventi',
			array(
				'eventi' => rest_url( Markers::NAMESPACE . '/eventi' ),
				'grandi' => rest_url( Markers::NAMESPACE . '/grandi-eventi' ),
				'i18n'   => array(
					'scade'    => __( 'Inizia tra', 'advertrieste' ),
					'incorso'  => __( 'In corso', 'advertrieste' ),
					'concluso' => __( 'Concluso', 'advertrieste' ),
					'locali'   => __( 'Locali aderenti', 'advertrieste' ),
					'nessuno'  => __( 'Nessun evento in programma.', 'advertrieste' ),
					'giorni'   => __( 'g', 'advertrieste' ),
					'ore'      => __( 'h', 'advertrieste' ),
					'minuti'   => __( 'm', 'advertrieste' ),
				),
			)
		);
	}

	/**
	 * Shortcode `[advtr_grandi_eventi]`.
	 *
	 * @return string
	 */
	public static function shortcode_grandi() {
		self::enqueue();
		return '<div class="advtr-grandi-eventi" data-advtr-grandi-eventi="1"></div>';
	}

	/**
	 * Shortcode `[advtr_eventi]`.
	 *
	 * @return string
	 */
	public static function shortcode_eventi() {
		self::enqueue();
		return '<div class="advtr-eventi" data-advtr-eventi="1"></div>';
	}
}
