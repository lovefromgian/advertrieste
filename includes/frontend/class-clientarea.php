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
use AdverTrieste\Rest\Markers;
use AdverTrieste\Console\Console;
use AdverTrieste\Cliente\Evidenza;
use AdverTrieste\Cliente\Abbonamento;

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
		$sezioni = array();
		foreach ( self::menu() as $voci ) {
			foreach ( $voci as $slug => $etichetta ) {
				$sezioni[ $slug ] = $etichetta;
			}
		}
		return $sezioni;
	}

	/**
	 * Menu della console cliente, raggruppato come nella proposta di progetto.
	 *
	 * @return array<string,array<string,string>> Gruppo => [ slug => etichetta ].
	 */
	public static function menu() {
		$menu = array(
			__( 'Principale', 'advertrieste' ) => array(
				'statistiche' => __( 'Statistiche', 'advertrieste' ),
				'scheda'      => __( 'La mia scheda', 'advertrieste' ),
				'immagini'    => __( 'Galleria media', 'advertrieste' ),
			),
			__( 'Marketing', 'advertrieste' )  => array(
				'offerte'  => __( 'Offerte & Coupon', 'advertrieste' ),
				'coupon'   => __( 'Valida coupon', 'advertrieste' ),
				'evidenza' => __( 'Piano In Evidenza', 'advertrieste' ),
			),
			__( 'Account', 'advertrieste' )    => array(
				'abbonamento'  => __( 'Abbonamento', 'advertrieste' ),
				'impostazioni' => __( 'Impostazioni', 'advertrieste' ),
			),
		);

		if ( Access::can_view_qr_map() ) {
			$menu[ __( 'Strumenti', 'advertrieste' ) ] = array(
				'qr' => __( 'Mappa punti QR', 'advertrieste' ),
			);
		}
		return $menu;
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
		add_action( 'template_redirect', array( __CLASS__, 'evita_cache' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'gestisci_azioni' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'registra_asset' ) );
	}

	/**
	 * Shortcode le cui pagine non devono MAI finire in una page cache.
	 *
	 * @var string[]
	 */
	const SHORTCODE_NON_CACHABILI = array(
		'advtr_area_clienti',
		'advtr_area_riservata',
		'advtr_statistiche',
		'advtr_valida_coupon',
	);

	/**
	 * Impedisce alle cache di pagina di conservare l'area clienti.
	 *
	 * Non è un'ottimizzazione mancata, è correttezza. Queste pagine contengono
	 * nonce: un nonce vive fra 12 e 24 ore, mentre la copia in cache viene
	 * servita per tutta la durata configurata. Appena il nonce scade, il modulo
	 * di accesso smette di funzionare e `check_admin_referer` mostra la schermata
	 * di errore di WordPress — proprio ciò che l'area serve a evitare. Contengono
	 * inoltre dati personali dell'utente, che non vanno serviti a un altro
	 * visitatore.
	 *
	 * `DONOTCACHEPAGE` è la convenzione riconosciuta da WP Rocket, W3 Total
	 * Cache, WP Super Cache e WP-Optimize: non dipendiamo da un plugin preciso.
	 *
	 * @return void
	 */
	public static function evita_cache() {
		if ( is_admin() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$da_proteggere = false;
		foreach ( self::SHORTCODE_NON_CACHABILI as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				$da_proteggere = true;
				break;
			}
		}
		if ( ! $da_proteggere ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// Volutamente SENZA prefisso del plugin: è il nome esatto che i plugin
			// di cache cercano. Prefissarlo lo renderebbe inerte.
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}
		nocache_headers();
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
		return array_key_exists( $s, self::sezioni() ) ? $s : 'statistiche';
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
			'account_ok'   => array( 'ok', __( 'Dati dell\'account aggiornati.', 'advertrieste' ) ),
			'account_ko'   => array( 'errore', __( 'Controlla nome ed email inseriti.', 'advertrieste' ) ),
			'account_email' => array( 'errore', __( 'Questa email è già usata da un altro account.', 'advertrieste' ) ),
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
			case 'account_salva':
				self::redirect( 'impostazioni', self::salva_account() );
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

		// Ricerca indirizzo del selettore di posizione. Il nonce qui è affidabile:
		// l'area è per soli utenti autenticati, quindi non finisce in page cache.
		wp_localize_script(
			self::HANDLE,
			'advtrCliente',
			array(
				'geocode' => rest_url( Markers::NAMESPACE . '/geocode' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'ricerca'        => __( 'Ricerca in corso…', 'advertrieste' ),
					'trovato'        => __( 'Trovato. Ora trascina il segnaposto sull\'ingresso esatto.', 'advertrieste' ),
					'senzaIndirizzo' => __( 'Scrivi prima l\'indirizzo nel riquadro Contatti.', 'advertrieste' ),
					'errore'         => __( 'Ricerca non riuscita: posiziona il segnaposto a mano.', 'advertrieste' ),
				),
			)
		);

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

		wp_enqueue_style( Console::HANDLE );

		$sezione = self::sezione_corrente();
		$locale  = self::locale_utente();
		$utente  = wp_get_current_user();

		// Il contenuto della sezione viene composto prima, poi montato nel guscio
		// condiviso: le due console usano lo stesso layout e gli stessi componenti.
		ob_start();
		if ( ! $locale && in_array( $sezione, array( 'scheda', 'immagini', 'offerte', 'evidenza', 'abbonamento' ), true ) ) {
			require ADVTR_PATH . 'templates/console/cliente-senza-scheda.php';
		} else {
			switch ( $sezione ) {
				case 'scheda':
					require ADVTR_PATH . 'templates/cliente/sez-scheda.php';
					break;
				case 'immagini':
					require ADVTR_PATH . 'templates/cliente/sez-immagini.php';
					break;
				case 'offerte':
					require ADVTR_PATH . 'templates/cliente/sez-offerte.php';
					break;
				case 'coupon':
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lo shortcode restituisce markup già escapato.
					echo do_shortcode( '[advtr_valida_coupon]' );
					break;
				case 'qr':
					require ADVTR_PATH . 'templates/cliente/sez-qr.php';
					break;
				case 'evidenza':
					require ADVTR_PATH . 'templates/console/cliente-evidenza.php';
					break;
				case 'abbonamento':
					require ADVTR_PATH . 'templates/console/cliente-abbonamento.php';
					break;
				case 'impostazioni':
					require ADVTR_PATH . 'templates/console/cliente-impostazioni.php';
					break;
				case 'statistiche':
				default:
					require ADVTR_PATH . 'templates/console/cliente-statistiche.php';
					break;
			}
		}
		$contenuto = (string) ob_get_clean();

		return Console::guscio(
			array(
				'marchio'     => get_bloginfo( 'name' ),
				'menu'        => self::menu_per_guscio(),
				'attiva'      => $sezione,
				'titolo'      => self::titolo_sezione( $sezione ),
				'sottotitolo' => self::sottotitolo_sezione( $sezione, $locale ),
				'utente'      => array(
					'sigla' => Console::sigla( $locale ? $locale->post_title : $utente->display_name ),
					'nome'  => $locale ? $locale->post_title : $utente->display_name,
					'ruolo' => self::etichetta_piano( $locale ),
					'esci'  => wp_logout_url( self::url() ),
				),
				'avviso'      => $avviso ? array(
					'tipo'  => 'errore' === $avviso['tipo'] ? 'errore' : 'ok',
					'testo' => $avviso['testo'],
				) : self::avviso_scadenza( $locale ),
				'contenuto'   => $contenuto,
			)
		);
	}

	/**
	 * Salva nome visualizzato ed email dell'account.
	 *
	 * L'email è il recapito per gli avvisi di scadenza: se è già di un altro
	 * utente il salvataggio viene rifiutato, altrimenti si romperebbe l'accesso.
	 *
	 * @return string Codice di esito.
	 */
	private static function salva_account() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce già verificato dal controller.
		$nome  = isset( $_POST['advtr_nome'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_nome'] ) ) : '';
		$email = isset( $_POST['advtr_email'] ) ? sanitize_email( wp_unslash( $_POST['advtr_email'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $nome || ! is_email( $email ) ) {
			return 'account_ko';
		}

		$uid       = get_current_user_id();
		$occupante = get_user_by( 'email', $email );
		if ( $occupante && (int) $occupante->ID !== $uid ) {
			return 'account_email';
		}

		$esito = wp_update_user(
			array(
				'ID'           => $uid,
				'display_name' => $nome,
				'user_email'   => $email,
			)
		);

		return is_wp_error( $esito ) ? 'account_ko' : 'account_ok';
	}

	/**
	 * Menu nel formato atteso dal guscio (slug, etichetta, url).
	 *
	 * @return array<string,array<int,array<string,string>>>
	 */
	private static function menu_per_guscio() {
		$out = array();
		foreach ( self::menu() as $gruppo => $voci ) {
			foreach ( $voci as $slug => $etichetta ) {
				$out[ $gruppo ][] = array(
					'slug'      => $slug,
					'etichetta' => $etichetta,
					'url'       => self::url( $slug ),
				);
			}
		}
		return $out;
	}

	/**
	 * Titolo della schermata.
	 *
	 * @param string $sezione Slug.
	 * @return string
	 */
	private static function titolo_sezione( $sezione ) {
		$titoli = array(
			'statistiche' => __( 'Statistiche della scheda', 'advertrieste' ),
			'scheda'      => __( 'La mia scheda', 'advertrieste' ),
			'immagini'    => __( 'Galleria media', 'advertrieste' ),
			'offerte'     => __( 'Offerte & Coupon', 'advertrieste' ),
			'coupon'      => __( 'Valida un coupon', 'advertrieste' ),
			'evidenza'    => __( 'Piano In Evidenza', 'advertrieste' ),
			'abbonamento' => __( 'Il tuo abbonamento', 'advertrieste' ),
			'impostazioni' => __( 'Impostazioni', 'advertrieste' ),
			'qr'          => __( 'Mappa dei punti QR', 'advertrieste' ),
		);
		return $titoli[ $sezione ] ?? '';
	}

	/**
	 * Sottotitolo della schermata.
	 *
	 * @param string        $sezione Slug.
	 * @param \WP_Post|null $locale  Scheda del cliente.
	 * @return string
	 */
	private static function sottotitolo_sezione( $sezione, $locale ) {
		$sottotitoli = array(
			'statistiche' => __( 'Andamento delle visualizzazioni e interazioni', 'advertrieste' ),
			'scheda'      => __( 'I contenuti che il pubblico vede sulla tua pagina', 'advertrieste' ),
			'immagini'    => __( 'Logo e fotografie della tua attività', 'advertrieste' ),
			'offerte'     => __( 'Promozioni a tempo e coupon da validare sul posto', 'advertrieste' ),
			'coupon'      => __( 'Verifica il codice che il cliente ti mostra', 'advertrieste' ),
			'evidenza'    => __( 'Marker dorato e priorità nei risultati', 'advertrieste' ),
			'abbonamento' => __( 'Validità della tua presenza sulla mappa', 'advertrieste' ),
			'impostazioni' => __( 'Dati del tuo account', 'advertrieste' ),
			'qr'          => __( 'La rete di espositori sul territorio', 'advertrieste' ),
		);
		unset( $locale );
		return $sottotitoli[ $sezione ] ?? '';
	}

	/**
	 * Etichetta del piano mostrata sotto il nome, nella sidebar.
	 *
	 * @param \WP_Post|null $locale Scheda del cliente.
	 * @return string
	 */
	private static function etichetta_piano( $locale ) {
		if ( ! $locale ) {
			return __( 'Nessuna scheda', 'advertrieste' );
		}
		return Evidenza::attiva( $locale->ID )
			? __( 'Piano In Evidenza', 'advertrieste' )
			: __( 'Piano base', 'advertrieste' );
	}

	/**
	 * Banner di scadenza imminente, come nella proposta di progetto.
	 *
	 * @param \WP_Post|null $locale Scheda del cliente.
	 * @return array<string,string>|null
	 */
	private static function avviso_scadenza( $locale ) {
		if ( ! $locale ) {
			return null;
		}
		$giorni = Abbonamento::giorni_alla_scadenza( $locale->ID );
		if ( null === $giorni || $giorni > 30 ) {
			return null;
		}

		if ( $giorni > 0 ) {
			$titolo = sprintf(
				/* translators: %d: giorni mancanti */
				_n( 'Il tuo abbonamento scade fra %d giorno', 'Il tuo abbonamento scade fra %d giorni', $giorni, 'advertrieste' ),
				$giorni
			);
			$testo = sprintf(
				/* translators: %s: data di scadenza */
				__( 'Rinnova entro il %s per non interrompere la visibilità sulla mappa. Riceverai un promemoria via email.', 'advertrieste' ),
				Abbonamento::data_scadenza( $locale->ID )
			);
		} else {
			$titolo = __( 'Il tuo abbonamento è scaduto', 'advertrieste' );
			$testo  = __( 'La scheda non è più visibile sulla mappa. Rinnova per riattivarla.', 'advertrieste' );
		}

		return array(
			'tipo'   => 'attesa',
			'titolo' => $titolo,
			'testo'  => $testo,
			'azione' => '<a class="ac-btn ac-btn-primario" href="' . esc_url( self::url( 'abbonamento' ) ) . '">' .
				esc_html__( 'Rinnova ora', 'advertrieste' ) . '</a>',
		);
	}
}
