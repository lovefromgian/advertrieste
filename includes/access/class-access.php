<?php
/**
 * Helper per i controlli di accesso dell'area riservata.
 *
 * Centralizza le verifiche di capability così che endpoint REST e front-end
 * usino la stessa logica. Le decisioni di visibilità dei dati riservati (in
 * particolare i punti QR) passano tutte da qui.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Access;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controlli di accesso riutilizzabili.
 */
class Access {

	/**
	 * L'utente corrente può vedere la mappa dei punti QR?
	 *
	 * Requisito: autenticato E con capability dedicata. Nessun dato QR deve
	 * essere servito se questa restituisce false.
	 *
	 * @return bool
	 */
	public static function can_view_qr_map() {
		return is_user_logged_in() && current_user_can( Roles::CAP_VIEW_QR_MAP );
	}

	/**
	 * L'utente corrente ha accesso all'area riservata clienti?
	 *
	 * Consideriamo "cliente" chi ha almeno una delle capability del plugin
	 * (cliente_locale, organizzatore_evento) oppure è amministratore.
	 *
	 * @return bool
	 */
	public static function is_cliente() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		foreach ( Roles::all_caps() as $cap ) {
			if ( current_user_can( $cap ) ) {
				return true;
			}
		}
		return false;
	}
}
