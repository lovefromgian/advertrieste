<?php
/**
 * Job pianificati (WP-Cron).
 *
 * Attualmente: `advtr_expire_coupons` (giornaliero) marca scadute le offerte
 * oltre la data di scadenza. Lo scheduling avviene all'attivazione; l'handler è
 * agganciato sempre, con auto-riparazione dello scheduling se mancante.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Cron;

use AdverTrieste\Coupon\Coupon;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrazione e gestione dei cron.
 */
class Cron {

	/**
	 * Hook del job di scadenza coupon/offerte.
	 *
	 * @var string
	 */
	const HOOK_EXPIRE = 'advtr_expire_coupons';

	/**
	 * Aggancia gli handler e assicura lo scheduling.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::HOOK_EXPIRE, array( Coupon::class, 'expire_offers' ) );

		// Auto-riparazione: se il job non è pianificato, pianificalo.
		if ( ! wp_next_scheduled( self::HOOK_EXPIRE ) ) {
			self::schedule();
		}
	}

	/**
	 * Pianifica i job (idempotente). Da chiamare all'attivazione.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK_EXPIRE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_EXPIRE );
		}
	}

	/**
	 * Rimuove i job pianificati. Da chiamare alla disattivazione.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK_EXPIRE );
	}
}
