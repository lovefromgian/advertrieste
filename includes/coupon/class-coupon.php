<?php
/**
 * Coupon: tabella dei riscatti e stato delle offerte.
 *
 * Tabella `{prefix}advtr_coupon` con i riscatti registrati dagli esercenti.
 * Fornisce anche le utilità per capire se un'offerta è attiva (finestra date)
 * e per marcare scadute offerte e coupon (usate dal cron).
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Coupon;

use AdverTrieste\Cpt\Offerta;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modello dei coupon/riscatti.
 */
class Coupon {

	/**
	 * Versione schema DB.
	 *
	 * @var string
	 */
	const DB_VERSION = '1';

	/**
	 * Opzione versione schema.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'advtr_coupon_db_version';

	/**
	 * Nome completo della tabella.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'advtr_coupon';
	}

	/**
	 * Crea/aggiorna la tabella via dbDelta. Idempotente.
	 *
	 * @return void
	 */
	public static function install_table() {
		global $wpdb;
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			offerta_id bigint(20) unsigned NOT NULL,
			codice varchar(64) NOT NULL,
			stato varchar(20) NOT NULL DEFAULT 'riscattato',
			emesso_il datetime NOT NULL,
			riscattato_il datetime NULL,
			PRIMARY KEY  (id),
			KEY offerta_stato (offerta_id, stato)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Un'offerta è attiva adesso? (stato non scaduto + finestra date).
	 *
	 * @param int $offerta_id ID offerta.
	 * @return bool
	 */
	public static function is_offer_active( $offerta_id ) {
		if ( 'scaduto' === get_post_meta( $offerta_id, 'advtr_stato', true ) ) {
			return false;
		}
		$now      = current_time( 'mysql' );
		$inizio   = get_post_meta( $offerta_id, 'advtr_data_inizio', true );
		$scadenza = get_post_meta( $offerta_id, 'advtr_data_scadenza', true );

		if ( $inizio && $now < $inizio ) {
			return false;
		}
		if ( $scadenza && $now > $scadenza ) {
			return false;
		}
		return true;
	}

	/**
	 * Registra il riscatto di un coupon per un'offerta.
	 *
	 * @param int    $offerta_id ID offerta.
	 * @param string $codice     Codice presentato.
	 * @return int|false ID della riga inserita o false.
	 */
	public static function record_redemption( $offerta_id, $codice ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::table(),
			array(
				'offerta_id'    => (int) $offerta_id,
				'codice'        => $codice,
				'stato'         => 'riscattato',
				'emesso_il'     => $now,
				'riscattato_il' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Numero di riscatti registrati per un'offerta.
	 *
	 * @param int $offerta_id ID offerta.
	 * @return int
	 */
	public static function redemptions_count( $offerta_id ) {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE offerta_id = %d AND stato = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $offerta_id,
				'riscattato'
			)
		);
	}

	/**
	 * Marca scadute le offerte oltre la data di scadenza e i relativi coupon.
	 * Chiamata dal cron giornaliero.
	 *
	 * @return int Numero di offerte marcate scadute.
	 */
	public static function expire_offers() {
		$now = current_time( 'mysql' );

		$ids = get_posts(
			array(
				'post_type'      => Offerta::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- batch cron.
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => 'advtr_data_scadenza',
						'value'   => $now,
						'compare' => '<',
						'type'    => 'DATETIME',
					),
					// Non ancora scadute: il meta può non esistere ('!=' da solo
					// escluderebbe i post privi del meta).
					array(
						'relation' => 'OR',
						array(
							'key'     => 'advtr_stato',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => 'advtr_stato',
							'value'   => 'scaduto',
							'compare' => '!=',
						),
					),
				),
			)
		);

		foreach ( $ids as $id ) {
			update_post_meta( $id, 'advtr_stato', 'scaduto' );
		}
		return count( $ids );
	}
}
