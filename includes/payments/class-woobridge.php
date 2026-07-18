<?php
/**
 * Bridge pagamenti WooCommerce (§2.6).
 *
 * Collega gli acquisti WooCommerce al rinnovo della validità delle schede. È
 * INERTE se WooCommerce non è attivo: gli hook vengono agganciati solo quando la
 * classe `WooCommerce` esiste, così il plugin funziona anche senza WooCommerce.
 *
 * Contratto: un prodotto WooCommerce che rappresenta un abbonamento a una scheda
 * ha i meta prodotto `_advtr_locale_id` (ID del locale) e `_advtr_durata_giorni`
 * (durata del rinnovo). Al completamento dell'ordine, la validità del locale
 * viene estesa e la scheda riattivata se sospesa.
 *
 * NB: gli hook WooCommerce sono verificabili solo con WooCommerce installato; la
 * logica di rinnovo (`extend_validity`) è invece testabile in isolamento.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Payments;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrazione con WooCommerce (attiva solo se WooCommerce è presente).
 */
class WooBridge {

	/**
	 * Aggancia gli hook, solo se WooCommerce è attivo.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_completed' ) );
	}

	/**
	 * A ordine completato, estende la validità dei locali acquistati.
	 *
	 * @param int $order_id ID ordine WooCommerce.
	 * @return void
	 */
	public static function on_order_completed( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id = (int) $item->get_product_id();
			$locale_id  = (int) get_post_meta( $product_id, '_advtr_locale_id', true );
			$giorni     = (int) get_post_meta( $product_id, '_advtr_durata_giorni', true );
			if ( $locale_id && $giorni > 0 ) {
				self::extend_validity( $locale_id, $giorni );
			}
		}
	}

	/**
	 * Estende la validità di una scheda e la riattiva se sospesa.
	 *
	 * Nuova scadenza = max(oggi, scadenza attuale) + giorni. Azzera lo stato di
	 * sospensione e gli avvisi di scadenza già inviati, e ripubblica la scheda se
	 * era stata messa in bozza dalla sospensione automatica.
	 *
	 * @param int $locale_id ID del locale.
	 * @param int $giorni    Giorni di rinnovo.
	 * @return string La nuova data di fine ('Y-m-d').
	 */
	public static function extend_validity( $locale_id, $giorni ) {
		$giorni  = max( 1, (int) $giorni );
		$oggi_ts = strtotime( current_time( 'mysql' ) );
		$fine    = (string) get_post_meta( $locale_id, 'advtr_data_fine', true );
		$base_ts = ( '' !== $fine ) ? strtotime( $fine . ' 23:59:59' ) : $oggi_ts;
		if ( $base_ts < $oggi_ts ) {
			$base_ts = $oggi_ts;
		}

		$nuova = gmdate( 'Y-m-d', $base_ts + $giorni * DAY_IN_SECONDS );
		update_post_meta( $locale_id, 'advtr_data_fine', $nuova );

		// Riattivazione: azzera sospensione e avvisi, ripubblica se in bozza.
		delete_post_meta( $locale_id, 'advtr_sospesa' );
		delete_post_meta( $locale_id, 'advtr_scadenza_avvisi' );
		if ( 'draft' === get_post_status( $locale_id ) ) {
			wp_update_post(
				array(
					'ID'          => $locale_id,
					'post_status' => 'publish',
				)
			);
		}

		return $nuova;
	}
}
