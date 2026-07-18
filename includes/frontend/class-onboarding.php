<?php
/**
 * Ingresso guidato "Cosa stai cercando?" (§1.1).
 *
 * Shortcode `[advtr_onboarding]`: schermata con schede d'intenzione (le categorie)
 * che rimandano alla pagina della mappa con la categoria pre-selezionata
 * (parametro `?categoria=slug`, letto dalla mappa).
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Frontend;

use AdverTrieste\Cpt\Categoria;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode e asset dell'ingresso guidato.
 */
class Onboarding {

	/**
	 * Handle asset.
	 *
	 * @var string
	 */
	const HANDLE = 'advtr-onboarding';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'advtr_onboarding', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Registra gli asset.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style( self::HANDLE, ADVTR_URL . 'assets/src/onboarding/onboarding.css', array(), ADVTR_VERSION );
	}

	/**
	 * Rende lo shortcode `[advtr_onboarding]`.
	 *
	 * @param array<string,mixed> $atts Attributi: `map` (URL pagina mappa), `titolo`.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'map'    => '',
				'titolo' => __( 'Cosa stai cercando?', 'advertrieste' ),
			),
			$atts,
			'advtr_onboarding'
		);

		$map_url = $atts['map'] ? esc_url( $atts['map'] ) : home_url( '/' );

		$terms = get_terms(
			array(
				'taxonomy'   => Categoria::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return '';
		}

		wp_enqueue_style( self::HANDLE );

		$titolo = $atts['titolo'];

		ob_start();
		require ADVTR_PATH . 'templates/onboarding.php';
		return (string) ob_get_clean();
	}
}
