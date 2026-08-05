<?php
/**
 * Area clienti in front-end (§2.1).
 *
 * Sostituisce la bacheca di WordPress per gli utenti `cliente_locale`: accesso,
 * modifica della scheda, immagini, offerte, statistiche, validazione coupon e
 * mappa dei punti QR, tutto dentro le pagine del sito e con la grafica del tema.
 * Il cliente non vede mai una schermata di WordPress (vedi `Access\AdminLock`).
 *
 * Le azioni sono POST classici gestiti su `template_redirect` con schema
 * Post/Redirect/Get: niente stato in sessione, niente dipendenza da JavaScript,
 * e un nonce per ogni scrittura. L'esito viaggia come codice in query string
 * (`?avviso=...`), così la pagina resta condivisibile e ricaricabile.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Frontend;

use AdverTrieste\Access\Access;
use AdverTrieste\Access\Roles;
use AdverTrieste\Cliente\Scheda as SchedaCliente;
use AdverTrieste\Cliente\Media as MediaCliente;
use AdverTrieste\Cliente\Offerte as OfferteCliente;
use AdverTrieste\Cpt\Locale;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller dell'area clienti.
 */
class ClientArea {

	/**
	 * Handle degli asset dell'area.
	 *
	 * @var string
	 */
	const HANDLE = 'advtr-cliente';

	/**
	 * Opzione con l'ID della pagina che ospita l'area.
	 *
	 * @var string
	 */
	const OPTION_PAGE = 'advtr_area_clienti_page_id';

	/**
	 * Nonce delle azioni dell'area.
	 *
	 * @var string
	 */
	const NONCE = 'advtr_area_clienti';

	/**
	 * Sezioni disponibili: slug => etichetta (risolta a runtime).
	 *
	 * @return array<string,string>
	 */
	public static function sezioni() {
		$sezioni = array(
			'scheda'      => __( 'La mia scheda', 'advertrieste' ),
			'immagini'    => __( 'Logo e foto', 'advertrieste' ),
			'offerte'     => __( 'Offerte', 'advertrieste' ),
			'statistiche' => __( 'Statistiche', 'advertrieste' ),
			'coupon'      => __( 'Valida coupon', 'advertrieste' ),
		);
		if ( Access::can_view_qr_map() ) {
			$sezioni['qr'] = __( 'Mappa punti QR', 'advertrieste' );
		}
		return $sezioni;
	}

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'advtr_area_clienti', array( __CLASS__, 'shortcode' ) );
		// Alias storico, così le pagine già create continuano a funzionare.
		add_shortcode( 'advtr_area_riservata', array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'gestisci_azioni' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'registra_asset' ) );
	}

	/**
	 * Registra (senza accodare) gli asset dell'area.
	 *
	 * @return void
	 */
	public static function registra_asset() {
		wp_register_style( self::HANDLE, ADVTR_URL . 'assets/src/cliente/cliente.css', array(), ADVTR_VERSION );
		// Dipende da Leaflet: il selettore di posizione nella scheda lo usa.
		wp_register_script( self::HANDLE, ADVTR_URL . 'assets/src/cliente/cliente.js', array( 'leaflet' ), ADVTR_VERSION, true );
	}

	/**
	 * URL dell'area clienti (eventualmente di una sezione).
	 *
	 * @param string              $sezione Slug della sezione.
	 * @param array<string,mixed> $extra   Parametri aggiuntivi.
	 * @return string
	 */
	public static function url( $sezione = '', $extra = array() ) {
		$page_id = (int) get_option( self::OPTION_PAGE );
		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			$page_id = self::trova_pagina();
		}
		$base = $page_id ? get_permalink( $page_id ) : home_url( '/' );

		$args = $extra;
		if ( $sezione ) {
			$args['sezione'] = $sezione;
		}
		return $args ? add_query_arg( $args, $base ) : $base;
	}

	/**
	 * Cerca la pagina che contiene lo shortcode dell'area e memorizza l'ID.
	 *
	 * @return int ID della pagina, 0 se assente.
	 */
	private static function trova_pagina() {
		$pagine  = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				's'              => 'advtr_area_',
			)
		);
		$page_id = $pagine ? (int) $pagine[0] : 0;
		if ( $page_id ) {
			update_option( self::OPTION_PAGE, $page_id );
		}
		return $page_id;
	}

	/**
	 * Sezione corrente, validata contro l'elenco delle sezioni.
	 *
	 * @return string
	 */
	public static function sezione_corrente() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola navigazione.
		$s = isset( $_GET['sezione'] ) ? sanitize_key( wp_unslash( $_GET['sezione'] ) ) : '';
		return array_key_exists( $s, self::sezioni() ) ? $s : 'scheda';
	}

	/**
	 * La scheda `locale` del cliente corrente (la prima di cui è autore).
	 *
	 * @return \WP_Post|null
	 */
	public static function locale_utente() {
		$posts = get_posts(
			array(
				'post_type'      => Locale::POST_TYPE,
				'post_status'    => array( 'publish', 'pending', 'draft' ),
				'posts_per_page' => 1,
				'author'         => get_current_user_id(),
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		return $posts ? $posts[0] : null;
	}

	/**
	 * Messaggi di esito, per codice.
	 *
	 * @return array<string,array{0:string,1:string}> codice => [tipo, testo].
	 */
	private static function avvisi() {
		return array(
			'login_ko'     => array( 'errore', __( 'Email o password non corretti.', 'advertrieste' ) ),
			'login_vuoto'  => array( 'errore', __( 'Inserisci email e password.', 'advertrieste' ) ),
			'reset_ok'     => array( 'ok', __( 'Ti abbiamo inviato un\'email con le istruzioni per reimpostare la password.', 'advertrieste' ) ),
			'reset_ko'     => array( 'errore', __( 'Non troviamo un account con questa email.', 'advertrieste' ) ),
			'scheda_ok'    => array( 'ok', __( 'Scheda aggiornata. Le modifiche sono già online.', 'advertrieste' ) ),
			'immagine_ok'  => array( 'ok', __( 'Immagine caricata.', 'advertrieste' ) ),
			'immagine_ko'  => array( 'errore', __( 'Caricamento non riuscito: controlla formato e dimensione del file.', 'advertrieste' ) ),
			'immagine_del' => array( 'ok', __( 'Immagine rimossa.', 'advertrieste' ) ),
			'offerta_ok'   => array( 'ok', __( 'Offerta salvata.', 'advertrieste' ) ),
			'offerta_del'  => array( 'ok', __( 'Offerta eliminata.', 'advertrieste' ) ),
			'offerta_date' => array( 'errore', __( 'La data di scadenza deve essere successiva a quella di inizio.', 'advertrieste' ) ),
			'negato'       => array( 'errore', __( 'Operazione non consentita.', 'advertrieste' ) ),
		);
	}

	/**
	 * Avviso corrente da mostrare, se presente.
	 *
	 * @return array{tipo:string,testo:string}|null
	 */
	public static function avviso_corrente() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo presentazione.
		$code  = isset( $_GET['avviso'] ) ? sanitize_key( wp_unslash( $_GET['avviso'] ) ) : '';
		$mappa = self::avvisi();
		if ( ! $code || ! isset( $mappa[ $code ] ) ) {
			return null;
		}
		return array(
			'tipo'  => $mappa[ $code ][0],
			'testo' => $mappa[ $code ][1],
		);
	}

	/**
	 * Esegue l'azione richiesta e reindirizza (Post/Redirect/Get).
	 *
	 * @return void
	 */
	public static function gestisci_azioni() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il nonce è verificato subito sotto, per azione.
		$azione = isset( $_POST['advtr_azione'] ) ? sanitize_key( wp_unslash( $_POST['advtr_azione'] ) ) : '';
		if ( ! $azione ) {
			return;
		}

		// Il login è l'unica azione ammessa senza utente autenticato.
		if ( 'login' === $azione ) {
			self::azione_login();
			return;
		}
		if ( 'password' === $azione ) {
			self::azione_password();
			return;
		}

		check_admin_referer( self::NONCE );

		if ( ! is_user_logged_in() || ! Access::is_cliente() ) {
			self::redirect( '', 'negato' );
		}

		switch ( $azione ) {
			case 'scheda_salva':
				self::redirect( 'scheda', SchedaCliente::salva() );
				break;
			case 'media_carica':
				self::redirect( 'immagini', MediaCliente::carica() );
				break;
			case 'media_elimina':
				self::redirect( 'immagini', MediaCliente::elimina() );
				break;
			case 'offerta_salva':
				self::redirect( 'offerte', OfferteCliente::salva() );
				break;
			case 'offerta_elimina':
				self::redirect( 'offerte', OfferteCliente::elimina() );
				break;
		}
	}

	/**
	 * Autentica il cliente dal form dell'area.
	 *
	 * @return void
	 */
	private static function azione_login() {
		check_admin_referer( self::NONCE . '_login' );

		$utente = isset( $_POST['advtr_utente'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_utente'] ) ) : '';
		$pass   = isset( $_POST['advtr_password'] ) ? (string) wp_unslash( $_POST['advtr_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- la password non va sanitizzata.

		if ( '' === $utente || '' === $pass ) {
			self::redirect( '', 'login_vuoto' );
		}

		$esito = wp_signon(
			array(
				'user_login'    => $utente,
				'user_password' => $pass,
				'remember'      => ! empty( $_POST['advtr_ricordami'] ),
			),
			is_ssl()
		);

		if ( is_wp_error( $esito ) ) {
			self::redirect( '', 'login_ko' );
		}

		wp_set_current_user( $esito->ID );
		self::redirect();
	}

	/**
	 * Avvia il recupero password dal form dell'area.
	 *
	 * @return void
	 */
	private static function azione_password() {
		check_admin_referer( self::NONCE . '_password' );

		$utente = isset( $_POST['advtr_utente'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_utente'] ) ) : '';
		$user   = is_email( $utente ) ? get_user_by( 'email', $utente ) : get_user_by( 'login', $utente );
		if ( ! $user ) {
			self::redirect( '', 'reset_ko', array( 'vista' => 'password' ) );
		}

		$esito = retrieve_password( $user->user_login );
		self::redirect( '', is_wp_error( $esito ) ? 'reset_ko' : 'reset_ok', is_wp_error( $esito ) ? array( 'vista' => 'password' ) : array() );
	}

	/**
	 * Reindirizza all'area con un codice di esito e termina.
	 *
	 * @param string              $sezione Sezione di destinazione.
	 * @param string              $avviso  Codice di esito.
	 * @param array<string,mixed> $extra   Parametri aggiuntivi.
	 * @return void
	 */
	private static function redirect( $sezione = '', $avviso = '', $extra = array() ) {
		if ( $avviso ) {
			$extra['avviso'] = $avviso;
		}
		wp_safe_redirect( self::url( $sezione, $extra ) );
		exit;
	}

	/**
	 * Rende l'area clienti.
	 *
	 * @return string
	 */
	public static function shortcode() {
		// Memorizza la pagina che ospita l'area, per i redirect.
		$corrente = get_queried_object_id();
		if ( $corrente && (int) get_option( self::OPTION_PAGE ) !== $corrente ) {
			update_option( self::OPTION_PAGE, $corrente );
		}

		wp_enqueue_style( 'leaflet' );
		wp_enqueue_script( 'leaflet' );
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );

		$avviso = self::avviso_corrente();

		if ( ! is_user_logged_in() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola scelta di vista.
			$vista = isset( $_GET['vista'] ) ? sanitize_key( wp_unslash( $_GET['vista'] ) ) : '';
			ob_start();
			require ADVTR_PATH . 'templates/cliente/login.php';
			return (string) ob_get_clean();
		}

		if ( ! Access::is_cliente() ) {
			return '<div class="advtr-cliente advtr-cliente-avviso errore">' .
				esc_html__( 'Questo account non ha accesso all\'area clienti.', 'advertrieste' ) .
				'</div>';
		}

		$sezione = self::sezione_corrente();
		$locale  = self::locale_utente();
		$utente  = wp_get_current_user();

		ob_start();
		require ADVTR_PATH . 'templates/cliente/dashboard.php';
		return (string) ob_get_clean();
	}
}
