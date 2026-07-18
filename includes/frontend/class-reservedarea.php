<?php
/**
 * Area riservata clienti: shortcode `[advtr_area_riservata]`.
 *
 * Gate di accesso lato server: chi non è autenticato vede l'invito al login;
 * chi è autenticato ma non è cliente vede un avviso; i clienti con capability
 * `advtr_view_qr_map` vedono la dashboard con la mappa dei punti QR (dati serviti
 * dall'endpoint autenticato `/qr-map`).
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Frontend;

use AdverTrieste\Access\Access;
use AdverTrieste\Rest\Markers;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode e asset dell'area riservata.
 */
class ReservedArea {

	/**
	 * Handle degli asset della mappa QR.
	 *
	 * @var string
	 */
	const HANDLE = 'advtr-qr-map';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'advtr_area_riservata', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Registra (senza accodare) lo script della mappa QR.
	 *
	 * Dipende da `leaflet` e `advtr-map` (registrati da Frontend\Map).
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_script(
			self::HANDLE,
			ADVTR_URL . 'assets/src/qr-map/qr-map.js',
			array( 'leaflet' ),
			ADVTR_VERSION,
			true
		);
	}

	/**
	 * Rende lo shortcode `[advtr_area_riservata]`.
	 *
	 * @return string
	 */
	public static function shortcode() {
		if ( ! is_user_logged_in() ) {
			return self::render_login();
		}
		if ( ! Access::is_cliente() ) {
			return self::render_no_access();
		}

		if ( Access::can_view_qr_map() ) {
			self::enqueue_qr_assets();
		}

		$puo_qr = Access::can_view_qr_map();
		$user   = wp_get_current_user();

		ob_start();
		require ADVTR_PATH . 'templates/area-riservata.php';
		return (string) ob_get_clean();
	}

	/**
	 * Accoda gli asset della mappa QR e localizza la configurazione.
	 *
	 * @return void
	 */
	private static function enqueue_qr_assets() {
		wp_enqueue_style( 'leaflet' );
		wp_enqueue_script( 'leaflet' );
		wp_enqueue_style( 'advtr-map' );
		wp_enqueue_script( self::HANDLE );

		wp_localize_script(
			self::HANDLE,
			'advtrQrMap',
			array(
				'endpoint' => rest_url( Markers::NAMESPACE . '/qr-map' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'center'   => array( 45.6495, 13.7768 ),
				'zoom'     => 12,
				'i18n'     => array(
					'stato'  => __( 'Stato', 'advertrieste' ),
					'errore' => __( 'Impossibile caricare i punti QR.', 'advertrieste' ),
				),
			)
		);
	}

	/**
	 * Messaggio di invito al login (con ritorno alla pagina corrente).
	 *
	 * @return string
	 */
	private static function render_login() {
		$login_url = wp_login_url( self::current_url() );
		return sprintf(
			'<div class="advtr-area-riservata advtr-login-richiesto"><p>%s</p><p><a class="button" href="%s">%s</a></p></div>',
			esc_html__( 'Questa area è riservata ai clienti. Effettua l\'accesso per continuare.', 'advertrieste' ),
			esc_url( $login_url ),
			esc_html__( 'Accedi', 'advertrieste' )
		);
	}

	/**
	 * Messaggio per utenti autenticati ma senza permessi di cliente.
	 *
	 * @return string
	 */
	private static function render_no_access() {
		return sprintf(
			'<div class="advtr-area-riservata advtr-no-access"><p>%s</p></div>',
			esc_html__( 'Il tuo account non ha accesso all\'area riservata clienti.', 'advertrieste' )
		);
	}

	/**
	 * URL della pagina corrente (per il redirect post-login).
	 *
	 * @return string
	 */
	private static function current_url() {
		$home = home_url( '/' );
		$req  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $req ) {
			return $home;
		}
		return home_url( $req );
	}
}
