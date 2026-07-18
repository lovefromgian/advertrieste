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
	 * Plurale delle capability del CPT `locale` (capability_type).
	 *
	 * @var string
	 */
	const LOCALE_PLURAL = 'advtr_locali';

	/**
	 * Plurale delle capability del CPT `evento` (capability_type).
	 *
	 * @var string
	 */
	const EVENTO_PLURAL = 'advtr_eventi';

	/**
	 * Genera l'elenco completo delle capability primitive per un CPT.
	 *
	 * @param string $plural Plurale del capability_type.
	 * @return string[]
	 */
	public static function cpt_capabilities( $plural ) {
		return array(
			"edit_{$plural}",
			"edit_others_{$plural}",
			"edit_published_{$plural}",
			"edit_private_{$plural}",
			"publish_{$plural}",
			"read_private_{$plural}",
			"delete_{$plural}",
			"delete_others_{$plural}",
			"delete_published_{$plural}",
			"delete_private_{$plural}",
		);
	}

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
		// Ruolo cliente_locale: gestisce le PROPRIE schede locale in bacheca.
		$cliente_caps = array(
			'read'                                  => true,
			'upload_files'                          => true,
			self::CAP_VIEW_QR_MAP                   => true,
			self::CAP_EDIT_OWN_LOCALE               => true,
			'edit_' . self::LOCALE_PLURAL           => true,
			'edit_published_' . self::LOCALE_PLURAL => true,
			'delete_' . self::LOCALE_PLURAL         => true,
		);
		self::ensure_role( self::CLIENTE, __( 'Cliente (locale)', 'advertrieste' ), $cliente_caps );

		// Ruolo organizzatore_evento: crea/gestisce i PROPRI eventi in bacheca.
		$org_caps = array(
			'read'                                    => true,
			'upload_files'                            => true,
			self::CAP_SUBMIT_EVENTO                   => true,
			'edit_' . self::EVENTO_PLURAL             => true,
			'edit_published_' . self::EVENTO_PLURAL   => true,
			'publish_' . self::EVENTO_PLURAL          => true,
			'delete_' . self::EVENTO_PLURAL           => true,
			'delete_published_' . self::EVENTO_PLURAL => true,
		);
		self::ensure_role( self::ORGANIZZATORE, __( 'Organizzatore evento', 'advertrieste' ), $org_caps );

		// L'amministratore riceve tutte le capability custom + tutte quelle dei CPT.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin_caps = array_merge(
				self::all_caps(),
				self::cpt_capabilities( self::LOCALE_PLURAL ),
				self::cpt_capabilities( self::EVENTO_PLURAL )
			);
			foreach ( $admin_caps as $cap ) {
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
			$admin_caps = array_merge(
				self::all_caps(),
				self::cpt_capabilities( self::LOCALE_PLURAL ),
				self::cpt_capabilities( self::EVENTO_PLURAL )
			);
			foreach ( $admin_caps as $cap ) {
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
