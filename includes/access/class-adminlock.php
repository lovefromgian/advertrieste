<?php
/**
 * Tiene i clienti fuori dalla bacheca di WordPress.
 *
 * I clienti gestiscono scheda, immagini e offerte dall'area clienti in
 * front-end (`Frontend\ClientArea`): la bacheca non deve comparire mai, né come
 * schermata né come barra di amministrazione. Oltre alla presentazione c'è un
 * motivo di riservatezza: `upload_files` da solo dà accesso in lettura all'INTERA
 * libreria media del sito — WordPress non la limita per autore — quindi un
 * cliente vedrebbe le immagini caricate dagli altri clienti.
 *
 * Chi è "cliente" qui: chi ha il ruolo `cliente_locale` e non è un
 * amministratore. Un admin che avesse anche quel ruolo continua a usare la
 * bacheca normalmente.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Access;

use AdverTrieste\Frontend\ClientArea;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect e restrizioni per gli utenti cliente.
 */
class AdminLock {

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'blocca_bacheca' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'nascondi_barra' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'dopo_login' ), 10, 3 );
		add_filter( 'logout_redirect', array( __CLASS__, 'dopo_logout' ), 10, 3 );

		// Riservatezza della libreria media: un cliente vede solo i propri file,
		// sia nella lista sia nel selettore. Vale anche se raggiungesse la
		// bacheca per altra via.
		add_action( 'pre_get_posts', array( __CLASS__, 'solo_propri_media' ) );
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'solo_propri_media_ajax' ) );
	}

	/**
	 * L'utente indicato è un cliente da tenere fuori dalla bacheca?
	 *
	 * @param int $user_id ID utente (0 = utente corrente).
	 * @return bool
	 */
	public static function is_cliente_bloccato( $user_id = 0 ) {
		$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		if ( user_can( $user, 'manage_options' ) ) {
			return false;
		}
		return in_array( Roles::CLIENTE, (array) $user->roles, true );
	}

	/**
	 * Rimanda i clienti all'area clienti se tentano di aprire la bacheca.
	 *
	 * Le richieste AJAX passano: l'area in front-end può averne bisogno e sono
	 * comunque protette dalle capability.
	 *
	 * @return void
	 */
	public static function blocca_bacheca() {
		if ( wp_doing_ajax() || ! self::is_cliente_bloccato() ) {
			return;
		}
		wp_safe_redirect( ClientArea::url() );
		exit;
	}

	/**
	 * Nasconde la barra di amministrazione ai clienti.
	 *
	 * @param bool $mostra Valore corrente.
	 * @return bool
	 */
	public static function nascondi_barra( $mostra ) {
		return self::is_cliente_bloccato() ? false : $mostra;
	}

	/**
	 * Dopo il login, un cliente va all'area clienti, non in bacheca.
	 *
	 * @param string             $redirect Destinazione richiesta.
	 * @param string             $ignorato Destinazione richiesta (non usata).
	 * @param \WP_User|\WP_Error $user   Utente autenticato.
	 * @return string
	 */
	public static function dopo_login( $redirect, $ignorato, $user ) {
		if ( $user instanceof \WP_User && self::is_cliente_bloccato( $user->ID ) ) {
			return ClientArea::url();
		}
		return $redirect;
	}

	/**
	 * Dopo il logout, un cliente torna all'area clienti (schermata di accesso).
	 *
	 * @param string        $redirect Destinazione richiesta.
	 * @param string        $ignorato Destinazione richiesta (non usata).
	 * @param \WP_User|null $user     Utente che ha effettuato il logout.
	 * @return string
	 */
	public static function dopo_logout( $redirect, $ignorato, $user ) {
		if ( $user instanceof \WP_User && self::is_cliente_bloccato( $user->ID ) ) {
			return ClientArea::url();
		}
		return $redirect;
	}

	/**
	 * Limita la lista dei media ai propri file (schermata Media).
	 *
	 * @param \WP_Query $query Query in corso.
	 * @return void
	 */
	public static function solo_propri_media( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'attachment' !== $query->get( 'post_type' ) || ! self::is_cliente_bloccato() ) {
			return;
		}
		$query->set( 'author', get_current_user_id() );
	}

	/**
	 * Limita il selettore media ai propri file.
	 *
	 * @param array<string,mixed> $args Argomenti della query.
	 * @return array<string,mixed>
	 */
	public static function solo_propri_media_ajax( $args ) {
		if ( self::is_cliente_bloccato() ) {
			$args['author'] = get_current_user_id();
		}
		return $args;
	}
}
