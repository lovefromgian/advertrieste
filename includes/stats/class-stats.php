<?php
/**
 * Statistiche per scheda: tabella custom, registrazione eventi e query.
 *
 * Tabella `{prefix}advtr_stats` con eventi di tipo view/map_click/coupon/contact.
 * Gestisce anche la soglia visite (specifiche §1.6): sotto soglia la scheda
 * mostra il badge "Novità" e non il conteggio reale.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Stats;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modello e query delle statistiche.
 */
class Stats {

	/**
	 * Versione dello schema DB (per le migrazioni via dbDelta).
	 *
	 * @var string
	 */
	const DB_VERSION = '1';

	/**
	 * Opzione che memorizza la versione dello schema installata.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'advtr_stats_db_version';

	/**
	 * Tipi di evento tracciabili.
	 *
	 * @var string[]
	 */
	const TIPI = array( 'view', 'map_click', 'coupon', 'contact' );

	/**
	 * Soglia di visite oltre la quale si mostra il conteggio reale (§1.6).
	 *
	 * @var int
	 */
	const SOGLIA_VISITE = 20;

	/**
	 * Nome completo della tabella (con prefisso).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'advtr_stats';
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
			post_id bigint(20) unsigned NOT NULL,
			tipo varchar(20) NOT NULL,
			created_at datetime NOT NULL,
			meta varchar(191) NULL,
			PRIMARY KEY  (id),
			KEY post_tipo_time (post_id, tipo, created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Registra un evento statistico.
	 *
	 * @param int    $post_id ID della scheda.
	 * @param string $tipo    Tipo evento (uno di TIPI).
	 * @param string $meta    Dettaglio opzionale (es. sezione).
	 * @return bool True se inserito.
	 */
	public static function record( $post_id, $tipo, $meta = '' ) {
		if ( ! in_array( $tipo, self::TIPI, true ) ) {
			return false;
		}

		global $wpdb;
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::table(),
			array(
				'post_id'    => (int) $post_id,
				'tipo'       => $tipo,
				'created_at' => current_time( 'mysql' ),
				'meta'       => '' === $meta ? null : $meta,
			),
			array( '%d', '%s', '%s', '%s' )
		);

		// La visita aggiorna il contatore reale e la soglia (§1.6).
		if ( $ok && 'view' === $tipo ) {
			self::bump_visite( (int) $post_id );
		}

		return (bool) $ok;
	}

	/**
	 * Incrementa il contatore visite reali e aggiorna il flag soglia.
	 *
	 * @param int $post_id ID della scheda.
	 * @return void
	 */
	private static function bump_visite( $post_id ) {
		$visite = (int) get_post_meta( $post_id, 'advtr_visite_reali', true ) + 1;
		update_post_meta( $post_id, 'advtr_visite_reali', $visite );
		if ( $visite >= self::SOGLIA_VISITE ) {
			update_post_meta( $post_id, 'advtr_visite_soglia_raggiunta', 1 );
		}
	}

	/**
	 * Totali per tipo di evento per una scheda.
	 *
	 * @param int $post_id ID della scheda.
	 * @return array<string,int> tipo => conteggio (tutti i TIPI, anche a zero).
	 */
	public static function totals_by_type( $post_id ) {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT tipo, COUNT(*) AS n FROM {$table} WHERE post_id = %d GROUP BY tipo", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $post_id
			),
			ARRAY_A
		);

		$out = array_fill_keys( self::TIPI, 0 );
		foreach ( (array) $rows as $row ) {
			if ( isset( $out[ $row['tipo'] ] ) ) {
				$out[ $row['tipo'] ] = (int) $row['n'];
			}
		}
		return $out;
	}

	/**
	 * Serie temporale giornaliera di un tipo di evento.
	 *
	 * @param int    $post_id ID della scheda.
	 * @param string $tipo    Tipo evento.
	 * @param int    $days    Numero di giorni (finestra fino a oggi).
	 * @return array<string,int> data (Y-m-d) => conteggio, per ogni giorno.
	 */
	public static function daily_series( $post_id, $tipo = 'view', $days = 30 ) {
		if ( ! in_array( $tipo, self::TIPI, true ) ) {
			return array();
		}

		$days  = max( 1, min( 365, (int) $days ) );
		$from  = gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
		$table = self::table();

		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DATE(created_at) AS d, COUNT(*) AS n FROM {$table} WHERE post_id = %d AND tipo = %s AND created_at >= %s GROUP BY DATE(created_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $post_id,
				$tipo,
				$from . ' 00:00:00'
			),
			OBJECT_K
		);

		// Riempie tutti i giorni della finestra, anche quelli a zero.
		$series = array();
		for ( $i = 0; $i < $days; $i++ ) {
			$day            = gmdate( 'Y-m-d', time() - ( $days - 1 - $i ) * DAY_IN_SECONDS );
			$series[ $day ] = isset( $rows[ $day ] ) ? (int) $rows[ $day ]->n : 0;
		}
		return $series;
	}

	/**
	 * Numero di visite reali registrate per una scheda.
	 *
	 * @param int $post_id ID della scheda.
	 * @return int
	 */
	public static function visite_reali( $post_id ) {
		return (int) get_post_meta( $post_id, 'advtr_visite_reali', true );
	}

	/**
	 * La scheda ha superato la soglia visite?
	 *
	 * @param int $post_id ID della scheda.
	 * @return bool
	 */
	public static function soglia_raggiunta( $post_id ) {
		return (bool) get_post_meta( $post_id, 'advtr_visite_soglia_raggiunta', true );
	}

	/**
	 * È una scheda "Novità"? (sotto soglia visite → badge invece del numero).
	 *
	 * @param int $post_id ID della scheda.
	 * @return bool
	 */
	public static function is_novita( $post_id ) {
		return ! self::soglia_raggiunta( $post_id );
	}
}
