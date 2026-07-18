<?php
/**
 * Dashboard statistiche cliente: shortcode `[advtr_statistiche]`.
 *
 * Mostra le statistiche delle schede `locale` di cui l'utente è proprietario
 * (o, per l'admin, la scheda indicata con l'attributo `id`). I dati arrivano
 * dall'endpoint autenticato `GET /stats/{id}`; il rendering (stat tiles + grafico
 * a barre) è fatto lato client senza librerie esterne.
 *
 * Attributi: `id` (opzionale) — forza una scheda specifica.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Frontend;

use AdverTrieste\Cpt\Locale;
use AdverTrieste\Rest\Markers;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode e asset della dashboard statistiche.
 */
class StatsDashboard {

	/**
	 * Handle degli asset.
	 *
	 * @var string
	 */
	const HANDLE = 'advtr-stats';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'advtr_statistiche', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Registra gli asset della dashboard.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style(
			self::HANDLE,
			ADVTR_URL . 'assets/src/stats/stats.css',
			array(),
			ADVTR_VERSION
		);
		wp_register_script(
			self::HANDLE,
			ADVTR_URL . 'assets/src/stats/stats.js',
			array(),
			ADVTR_VERSION,
			true
		);
	}

	/**
	 * Rende lo shortcode `[advtr_statistiche]`.
	 *
	 * @param array<string,mixed> $atts Attributi.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="advtr-stats"><p>' . esc_html__( 'Accedi per vedere le statistiche.', 'advertrieste' ) . '</p></div>';
		}

		$atts   = shortcode_atts( array( 'id' => 0 ), $atts, 'advtr_statistiche' );
		$forced = absint( $atts['id'] );
		$schede = self::schede_utente( $forced );

		if ( empty( $schede ) ) {
			return '<div class="advtr-stats"><p>' . esc_html__( 'Nessuna scheda disponibile per le statistiche.', 'advertrieste' ) . '</p></div>';
		}

		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
		wp_localize_script(
			self::HANDLE,
			'advtrStats',
			array(
				'base'   => rest_url( Markers::NAMESPACE . '/stats/' ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'schede' => $schede,
				'i18n'   => self::labels(),
			)
		);

		ob_start();
		require ADVTR_PATH . 'templates/statistiche.php';
		return (string) ob_get_clean();
	}

	/**
	 * Schede visibili all'utente: forzata (se admin/owner) o le proprie.
	 *
	 * @param int $forced ID forzato (0 = nessuno).
	 * @return array<int,array<string,mixed>> lista {id, titolo}.
	 */
	private static function schede_utente( $forced ) {
		if ( $forced ) {
			$post = get_post( $forced );
			if ( $post && Locale::POST_TYPE === $post->post_type && self::can_view( $post ) ) {
				return array(
					array(
						'id'     => (int) $post->ID,
						'titolo' => get_the_title( $post ),
					),
				);
			}
			return array();
		}

		$args = array(
			'post_type'      => Locale::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		);
		// Non-admin: solo le proprie schede.
		if ( ! current_user_can( 'manage_options' ) ) {
			$args['author'] = get_current_user_id();
		}

		$ids = get_posts( $args );
		$out = array();
		foreach ( $ids as $id ) {
			$out[] = array(
				'id'     => (int) $id,
				'titolo' => get_the_title( $id ),
			);
		}
		return $out;
	}

	/**
	 * L'utente corrente può vedere le statistiche della scheda?
	 *
	 * @param \WP_Post $post Scheda.
	 * @return bool
	 */
	private static function can_view( $post ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return get_current_user_id() === (int) $post->post_author;
	}

	/**
	 * Etichette tradotte per il front-end.
	 *
	 * @return array<string,string>
	 */
	private static function labels() {
		return array(
			'view'      => __( 'Visualizzazioni', 'advertrieste' ),
			'map_click' => __( 'Click sulla mappa', 'advertrieste' ),
			'coupon'    => __( 'Coupon riscattati', 'advertrieste' ),
			'contact'   => __( 'Click contatti', 'advertrieste' ),
			'andamento' => __( 'Andamento visite (30 giorni)', 'advertrieste' ),
			'novita'    => __( 'Scheda in fase "Novità" (sotto soglia visite)', 'advertrieste' ),
			'errore'    => __( 'Impossibile caricare le statistiche.', 'advertrieste' ),
		);
	}
}
