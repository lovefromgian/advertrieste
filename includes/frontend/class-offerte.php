<?php
/**
 * Front-end offerte/coupon.
 *
 * - `[advtr_offerte]`        — pubblico: elenco offerte attive con countdown.
 * - `[advtr_valida_coupon]`  — riservato: form per l'esercente che valida un coupon.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Frontend;

use AdverTrieste\Cpt\Offerta;
use AdverTrieste\Cpt\Locale;
use AdverTrieste\Rest\Markers;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode e asset di offerte e validazione coupon.
 */
class Offerte {

	/**
	 * Handle asset elenco offerte.
	 *
	 * @var string
	 */
	const HANDLE_LIST = 'advtr-offerte';

	/**
	 * Handle asset validazione coupon.
	 *
	 * @var string
	 */
	const HANDLE_REDEEM = 'advtr-valida-coupon';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'advtr_offerte', array( __CLASS__, 'shortcode_offerte' ) );
		add_shortcode( 'advtr_valida_coupon', array( __CLASS__, 'shortcode_valida' ) );
	}

	/**
	 * Registra gli asset (comune CSS + due script).
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style(
			self::HANDLE_LIST,
			ADVTR_URL . 'assets/src/offerte/offerte.css',
			array(),
			ADVTR_VERSION
		);
		wp_register_script(
			self::HANDLE_LIST,
			ADVTR_URL . 'assets/src/offerte/offerte.js',
			array(),
			ADVTR_VERSION,
			true
		);
		wp_register_script(
			self::HANDLE_REDEEM,
			ADVTR_URL . 'assets/src/offerte/valida.js',
			array(),
			ADVTR_VERSION,
			true
		);
	}

	/**
	 * Shortcode `[advtr_offerte]`.
	 *
	 * @param array<string,mixed> $atts Attributi (locale = id).
	 * @return string
	 */
	public static function shortcode_offerte( $atts ) {
		$atts   = shortcode_atts( array( 'locale' => 0 ), $atts, 'advtr_offerte' );
		$locale = absint( $atts['locale'] );

		wp_enqueue_style( self::HANDLE_LIST );
		wp_enqueue_script( self::HANDLE_LIST );
		wp_localize_script(
			self::HANDLE_LIST,
			'advtrOfferte',
			array(
				'endpoint' => add_query_arg(
					$locale ? array( 'locale' => $locale ) : array(),
					rest_url( Markers::NAMESPACE . '/offerte' )
				),
				'i18n'     => array(
					'scade'   => __( 'Scade tra', 'advertrieste' ),
					'scaduta' => __( 'Scaduta', 'advertrieste' ),
					'codice'  => __( 'Codice', 'advertrieste' ),
					'nessuna' => __( 'Nessuna offerta attiva.', 'advertrieste' ),
					'giorni'  => __( 'g', 'advertrieste' ),
					'ore'     => __( 'h', 'advertrieste' ),
					'minuti'  => __( 'm', 'advertrieste' ),
					'secondi' => __( 's', 'advertrieste' ),
				),
			)
		);

		return '<div class="advtr-offerte" data-advtr-offerte="1"></div>';
	}

	/**
	 * Shortcode `[advtr_valida_coupon]` (riservato all'esercente).
	 *
	 * @return string
	 */
	public static function shortcode_valida() {
		if ( ! is_user_logged_in() ) {
			return '<div class="advtr-valida"><p>' . esc_html__( 'Accedi per validare i coupon.', 'advertrieste' ) . '</p></div>';
		}

		$offerte = self::offerte_utente();
		if ( empty( $offerte ) ) {
			return '<div class="advtr-valida"><p>' . esc_html__( 'Nessuna offerta da validare.', 'advertrieste' ) . '</p></div>';
		}

		wp_enqueue_style( self::HANDLE_LIST );
		wp_enqueue_script( self::HANDLE_REDEEM );
		wp_localize_script(
			self::HANDLE_REDEEM,
			'advtrValida',
			array(
				'base'  => rest_url( Markers::NAMESPACE . '/offerta/' ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'ok'     => __( 'Coupon validato. Riscatti totali:', 'advertrieste' ),
					'errore' => __( 'Validazione non riuscita:', 'advertrieste' ),
				),
			)
		);

		ob_start();
		require ADVTR_PATH . 'templates/valida-coupon.php';
		return (string) ob_get_clean();
	}

	/**
	 * Offerte che l'utente corrente può validare.
	 *
	 * @return array<int,array<string,mixed>> lista {id, titolo}.
	 */
	private static function offerte_utente() {
		if ( current_user_can( 'manage_options' ) ) {
			$ids = get_posts(
				array(
					'post_type'      => Offerta::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'fields'         => 'ids',
				)
			);
		} else {
			$uid       = get_current_user_id();
			$propri    = get_posts(
				array(
					'post_type'      => Offerta::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'author'         => $uid,
					'fields'         => 'ids',
				)
			);
			$locali    = get_posts(
				array(
					'post_type'      => Locale::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'author'         => $uid,
					'fields'         => 'ids',
				)
			);
			$da_locali = array();
			if ( $locali ) {
				$da_locali = get_posts(
					array(
						'post_type'      => Offerta::POST_TYPE,
						'post_status'    => 'publish',
						'posts_per_page' => 100,
						'fields'         => 'ids',
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'     => 'advtr_locale_id',
								'value'   => $locali,
								'compare' => 'IN',
							),
						),
					)
				);
			}
			$ids = array_values( array_unique( array_merge( $propri, $da_locali ) ) );
		}

		$out = array();
		foreach ( $ids as $id ) {
			$out[] = array(
				'id'     => (int) $id,
				'titolo' => get_the_title( $id ),
			);
		}
		return $out;
	}
}
