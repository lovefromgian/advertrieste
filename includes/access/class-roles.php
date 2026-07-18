<?php
/**
 * Ruoli e capability custom del plugin.
 *
 * Definisce i ruoli `cliente_locale` e `organizzatore_evento` e le capability
 * custom, e li installa all'attivazione (assegnando le capability anche
 * all'amministratore). L'installazione è idempotente: si può richiamare a ogni
 * attivazione per allineare le capability quando cambiano.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Access;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestione di ruoli e capability.
 */
class Roles {

	/**
	 * Ruolo cliente che gestisce le proprie schede `locale`.
	 *
	 * @var string
	 */
	const CLIENTE = 'cliente_locale';

	/**
	 * Ruolo organizzatore che gestisce i propri `evento`.
	 *
	 * @var string
	 */
	const ORGANIZZATORE = 'organizzatore_evento';

	/**
	 * Capability: vedere la mappa dei punti QR (riservata).
	 *
	 * @var string
	 */
	const CAP_VIEW_QR_MAP = 'advtr_view_qr_map';

	/**
	 * Capability: modificare la propria scheda `locale`.
	 *
	 * @var string
	 */
	const CAP_EDIT_OWN_LOCALE = 'advtr_edit_own_locale';

	/**
	 * Capability: inviare in revisione un `evento`.
	 *
	 * @var string
	 */
	const CAP_SUBMIT_EVENTO = 'advtr_submit_evento';

	/**
	 * Capability: approvare/pubblicare un `evento`.
	 *
	 * @var string
	 */
	const CAP_APPROVE_EVENTO = 'advtr_approve_evento';

	/**
	 * Tutte le capability custom del plugin.
	 *
	 * @return string[]
	 */
	public static function all_caps() {
		return array(
			self::CAP_VIEW_QR_MAP,
			self::CAP_EDIT_OWN_LOCALE,
			self::CAP_SUBMIT_EVENTO,
			self::CAP_APPROVE_EVENTO,
		);
	}

	/**
	 * Installa ruoli e capability (idempotente). Da chiamare all'attivazione.
	 *
	 * @return void
	 */
	public static function install() {
		// Ruolo cliente_locale.
		self::ensure_role(
			self::CLIENTE,
			__( 'Cliente (locale)', 'advertrieste' ),
			array(
				'read'                    => true,
				'upload_files'            => true,
				self::CAP_VIEW_QR_MAP     => true,
				self::CAP_EDIT_OWN_LOCALE => true,
			)
		);

		// Ruolo organizzatore_evento.
		self::ensure_role(
			self::ORGANIZZATORE,
			__( 'Organizzatore evento', 'advertrieste' ),
			array(
				'read'                  => true,
				'upload_files'          => true,
				self::CAP_SUBMIT_EVENTO => true,
			)
		);

		// L'amministratore riceve tutte le capability custom.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all_caps() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Rimuove ruoli e capability. Da chiamare in fase di disinstallazione.
	 *
	 * @return void
	 */
	public static function remove() {
		remove_role( self::CLIENTE );
		remove_role( self::ORGANIZZATORE );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all_caps() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}

	/**
	 * Crea il ruolo se assente e allinea sempre le capability indicate.
	 *
	 * @param string             $slug Slug del ruolo.
	 * @param string             $name Nome visualizzato.
	 * @param array<string,bool> $caps Capability da garantire.
	 * @return void
	 */
	private static function ensure_role( $slug, $name, $caps ) {
		$role = get_role( $slug );
		if ( ! $role ) {
			add_role( $slug, $name, $caps );
			return;
		}
		foreach ( $caps as $cap => $grant ) {
			if ( $grant ) {
				$role->add_cap( $cap );
			}
		}
	}
}
