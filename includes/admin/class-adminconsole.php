<?php
/**
 * Console dell'amministratore, fuori dalla bacheca di WordPress.
 *
 * Il cockpit operativo del progetto: cosa richiede attenzione oggi, chi sono i
 * clienti, cosa scade, cosa aspetta approvazione. Costruita sullo stesso guscio
 * della console cliente (`Console\Console`), così le due restano coerenti senza
 * duplicare layout e componenti.
 *
 * Confine deliberato: qui si prendono le decisioni ricorrenti — approvare,
 * pubblicare, sospendere, rinnovare, accendere l'evidenza — con un gesto solo.
 * La scrittura di contenuti lunghi (descrizioni, gallerie) resta agli editor
 * esistenti: rifare qui un editor completo significherebbe riscrivere ciò che
 * WordPress già fa, senza guadagno per chi lo usa.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Admin;

use AdverTrieste\Console\Console;
use AdverTrieste\Console\Pagina;
use AdverTrieste\Console\Tabella;
use AdverTrieste\Cpt\Locale;
use AdverTrieste\Cpt\Poi;
use AdverTrieste\Cpt\Offerta;
use AdverTrieste\Cpt\Evento;
use AdverTrieste\Cpt\PuntoQr;
use AdverTrieste\Access\Roles;
use AdverTrieste\Cliente\Abbonamento;
use AdverTrieste\Cliente\Evidenza;
use AdverTrieste\Evento\Workflow;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller della console amministratore.
 */
class AdminConsole {

	/**
	 * Opzione con l'ID della pagina che ospita la console.
	 *
	 * @var string
	 */
	const OPTION_PAGE = 'advtr_console_admin_page_id';

	/**
	 * Nonce delle azioni.
	 *
	 * @var string
	 */
	const NONCE = 'advtr_console_admin';

	/**
	 * Shortcode che attiva la console.
	 *
	 * @var string
	 */
	const SHORTCODE = 'advtr_console_admin';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'evita_cache' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'gestisci_azioni' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'niente_barra' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'pulisci_asset' ), 999 );
		add_action( 'wp_print_styles', array( __CLASS__, 'pulisci_asset' ), 1 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'pulisci_asset' ), 1 );
		add_filter( 'style_loader_tag', array( __CLASS__, 'filtra_tag' ), 10, 2 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'filtra_tag' ), 10, 2 );
	}

	/**
	 * Siamo sulla console amministratore?
	 *
	 * @return bool
	 */
	public static function e_console() {
		return Pagina::pagina_con_shortcode( array( self::SHORTCODE ) );
	}

	/**
	 * Sostituisce il template del tema.
	 *
	 * @param string $template Template scelto dal tema.
	 * @return string
	 */
	public static function template( $template ) {
		return self::e_console() ? ADVTR_PATH . 'templates/console/pagina-admin.php' : $template;
	}

	/**
	 * Nasconde la barra di amministrazione.
	 *
	 * @param bool $mostra Valore corrente.
	 * @return bool
	 */
	public static function niente_barra( $mostra ) {
		return self::e_console() ? false : $mostra;
	}

	/**
	 * Toglie gli asset estranei.
	 *
	 * @return void
	 */
	public static function pulisci_asset() {
		if ( self::e_console() ) {
			Pagina::pulisci_asset();
		}
	}

	/**
	 * Sopprime i tag degli asset estranei.
	 *
	 * @param string $tag    Markup.
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function filtra_tag( $tag, $handle ) {
		return self::e_console() ? Pagina::filtra_tag( $tag, $handle ) : $tag;
	}

	/**
	 * La console non va mai messa in cache: contiene dati riservati e nonce.
	 *
	 * @return void
	 */
	public static function evita_cache() {
		if ( ! self::e_console() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}
		nocache_headers();
	}

	/**
	 * Menu della console, raggruppato per attività.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function menu() {
		return array(
			__( 'Principale', 'advertrieste' )  => array(
				'panoramica'   => __( 'Panoramica', 'advertrieste' ),
				'approvazioni' => __( 'Da approvare', 'advertrieste' ),
			),
			__( 'Contenuti', 'advertrieste' )   => array(
				'locali'  => __( 'Locali', 'advertrieste' ),
				'poi'     => __( 'Punti d\'interesse', 'advertrieste' ),
				'offerte' => __( 'Offerte', 'advertrieste' ),
				'eventi'  => __( 'Eventi', 'advertrieste' ),
			),
			__( 'Commerciale', 'advertrieste' ) => array(
				'clienti'     => __( 'Clienti', 'advertrieste' ),
				'abbonamenti' => __( 'Abbonamenti', 'advertrieste' ),
			),
			__( 'Rete', 'advertrieste' )        => array(
				'qr' => __( 'Punti QR', 'advertrieste' ),
			),
		);
	}

	/**
	 * Sezioni valide.
	 *
	 * @return array<string,string>
	 */
	public static function sezioni() {
		$out = array();
		foreach ( self::menu() as $voci ) {
			foreach ( $voci as $slug => $etichetta ) {
				$out[ $slug ] = $etichetta;
			}
		}
		return $out;
	}

	/**
	 * URL della console (eventualmente di una sezione).
	 *
	 * @param string              $sezione Slug.
	 * @param array<string,mixed> $extra   Parametri aggiuntivi.
	 * @return string
	 */
	public static function url( $sezione = '', $extra = array() ) {
		$page_id = (int) get_option( self::OPTION_PAGE );
		$base    = $page_id ? get_permalink( $page_id ) : home_url( '/' );

		$args = $extra;
		if ( $sezione ) {
			$args['sezione'] = $sezione;
		}
		return $args ? add_query_arg( $args, $base ) : $base;
	}

	/**
	 * Sezione corrente.
	 *
	 * @return string
	 */
	public static function sezione_corrente() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola navigazione.
		$s = isset( $_GET['sezione'] ) ? sanitize_key( wp_unslash( $_GET['sezione'] ) ) : '';
		return array_key_exists( $s, self::sezioni() ) ? $s : 'panoramica';
	}

	/**
	 * Termine di ricerca corrente.
	 *
	 * @return string
	 */
	public static function ricerca() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola navigazione.
		return isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	}

	/**
	 * Messaggi di esito.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	private static function avvisi() {
		return array(
			'pubblicato'   => array( 'ok', __( 'Scheda pubblicata: è online sulla mappa.', 'advertrieste' ) ),
			'sospeso'      => array( 'ok', __( 'Scheda sospesa: non è più visibile al pubblico.', 'advertrieste' ) ),
			'approvato'    => array( 'ok', __( 'Evento approvato: la versione pubblica è aggiornata.', 'advertrieste' ) ),
			'rinnovato'    => array( 'ok', __( 'Abbonamento rinnovato.', 'advertrieste' ) ),
			'evidenza_on'  => array( 'ok', __( 'Piano In Evidenza attivato.', 'advertrieste' ) ),
			'evidenza_off' => array( 'ok', __( 'Piano In Evidenza disattivato.', 'advertrieste' ) ),
			'negato'       => array( 'errore', __( 'Operazione non consentita.', 'advertrieste' ) ),
		);
	}

	/**
	 * Avviso da mostrare.
	 *
	 * @return array<string,string>|null
	 */
	public static function avviso() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola presentazione.
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
	 * Esegue le azioni della console.
	 *
	 * @return void
	 */
	public static function gestisci_azioni() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verificato subito sotto.
		$azione = isset( $_POST['advtr_azione'] ) ? sanitize_key( wp_unslash( $_POST['advtr_azione'] ) ) : '';
		if ( ! $azione || ! self::e_console() ) {
			return;
		}

		check_admin_referer( self::NONCE );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::redirect( '', 'negato' );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce già verificato.
		$id      = isset( $_POST['advtr_id'] ) ? absint( wp_unslash( $_POST['advtr_id'] ) ) : 0;
		$giorni  = isset( $_POST['advtr_giorni'] ) ? absint( wp_unslash( $_POST['advtr_giorni'] ) ) : 0;
		$sezione = isset( $_POST['advtr_sezione'] ) ? sanitize_key( wp_unslash( $_POST['advtr_sezione'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		switch ( $azione ) {
			case 'pubblica':
				self::redirect( $sezione, self::pubblica( $id, true ) );
				break;
			case 'sospendi':
				self::redirect( $sezione, self::pubblica( $id, false ) );
				break;
			case 'approva_evento':
				self::redirect( $sezione, self::approva_evento( $id ) );
				break;
			case 'rinnova':
				self::redirect( $sezione, self::rinnova( $id, $giorni ) );
				break;
			case 'evidenza':
				self::redirect( $sezione, self::evidenza( $id ) );
				break;
		}
	}

	/**
	 * Pubblica o sospende una scheda.
	 *
	 * @param int  $id       ID del post.
	 * @param bool $pubblica True per pubblicare.
	 * @return string Codice di esito.
	 */
	private static function pubblica( $id, $pubblica ) {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || ! in_array( $post->post_type, array( Locale::POST_TYPE, Poi::POST_TYPE, Offerta::POST_TYPE ), true ) ) {
			return 'negato';
		}

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => $pubblica ? 'publish' : 'draft',
			)
		);

		// Pubblicare a mano annulla la sospensione automatica: senza questo, il
		// cron la ritroverebbe "già sospesa" e non la sospenderebbe più.
		if ( $pubblica ) {
			delete_post_meta( $id, 'advtr_sospesa' );
		}

		return $pubblica ? 'pubblicato' : 'sospeso';
	}

	/**
	 * Approva un evento (promuove la versione in lavorazione).
	 *
	 * @param int $id ID dell'evento.
	 * @return string
	 */
	private static function approva_evento( $id ) {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || Evento::POST_TYPE !== $post->post_type ) {
			return 'negato';
		}
		Workflow::approve( $id );
		return 'approvato';
	}

	/**
	 * Estende la validità di una scheda.
	 *
	 * @param int $id     ID del locale.
	 * @param int $giorni Giorni da aggiungere.
	 * @return string
	 */
	private static function rinnova( $id, $giorni ) {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || Locale::POST_TYPE !== $post->post_type || $giorni < 1 ) {
			return 'negato';
		}

		// Stessa logica del rinnovo via WooCommerce: una sola definizione.
		\AdverTrieste\Payments\WooBridge::extend_validity( $id, $giorni );
		return 'rinnovato';
	}

	/**
	 * Accende o spegne il piano In Evidenza.
	 *
	 * @param int $id ID del locale.
	 * @return string
	 */
	private static function evidenza( $id ) {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || Locale::POST_TYPE !== $post->post_type ) {
			return 'negato';
		}

		if ( Evidenza::attiva( $id ) ) {
			update_post_meta( $id, 'advtr_in_evidenza', 0 );
			return 'evidenza_off';
		}

		update_post_meta( $id, 'advtr_in_evidenza', 1 );
		update_post_meta( $id, 'advtr_evidenza_inizio', current_time( 'Y-m-d' ) );
		update_post_meta( $id, 'advtr_evidenza_fine', wp_date( 'Y-m-d', time() + YEAR_IN_SECONDS ) );
		return 'evidenza_on';
	}

	/**
	 * Reindirizza con un esito.
	 *
	 * @param string $sezione Sezione.
	 * @param string $avviso  Codice.
	 * @return void
	 */
	private static function redirect( $sezione = '', $avviso = '' ) {
		wp_safe_redirect( self::url( $sezione, $avviso ? array( 'avviso' => $avviso ) : array() ) );
		exit;
	}

	/**
	 * Numero di elementi che richiedono attenzione.
	 *
	 * @return array<string,int>
	 */
	public static function da_fare() {
		$schede = get_posts(
			array(
				'post_type'      => Locale::POST_TYPE,
				'post_status'    => array( 'pending', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$eventi = 0;
		foreach ( get_posts(
			array(
				'post_type'      => Evento::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => -1,
			)
		) as $ev ) {
			if ( Workflow::STATO_IN_REVISIONE === Workflow::stato( $ev->ID ) ) {
				++$eventi;
			}
		}

		$scadenza = 0;
		foreach ( self::locali() as $loc ) {
			$g = Abbonamento::giorni_alla_scadenza( $loc->ID );
			if ( null !== $g && $g <= 30 ) {
				++$scadenza;
			}
		}

		return array(
			'schede'   => count( $schede ),
			'eventi'   => $eventi,
			'scadenza' => $scadenza,
		);
	}

	/**
	 * Valore o trattino se vuoto.
	 *
	 * @param string $valore Valore grezzo.
	 * @return string
	 */
	public static function o_trattino( $valore ) {
		return '' !== trim( (string) $valore ) ? $valore : '—';
	}

	/**
	 * Quanti post pubblicati di un tipo.
	 *
	 * Sta qui e non nel template: una funzione dichiarata dentro un template
	 * non si può includere due volte nella stessa richiesta senza far morire
	 * PHP con "Cannot redeclare".
	 *
	 * @param string $tipo Post type.
	 * @return int
	 */
	public static function conta( $tipo ) {
		$c = wp_count_posts( $tipo );
		return isset( $c->publish ) ? (int) $c->publish : 0;
	}

	/**
	 * Tutti i locali, in qualunque stato.
	 *
	 * @param string $cerca Termine di ricerca.
	 * @return \WP_Post[]
	 */
	public static function locali( $cerca = '' ) {
		$args = array(
			'post_type'      => Locale::POST_TYPE,
			'post_status'    => array( 'publish', 'pending', 'draft' ),
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( '' !== $cerca ) {
			$args['s'] = $cerca;
		}
		return get_posts( $args );
	}

	/**
	 * Rende la console.
	 *
	 * @return string
	 */
	public static function shortcode() {
		wp_enqueue_style( Console::HANDLE );

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return '<div class="ac-accesso"><div class="ac-accesso-scheda">' .
				'<h1 class="ac-accesso-titolo">' . esc_html__( 'Area riservata', 'advertrieste' ) . '</h1>' .
				'<p class="ac-accesso-nota">' . esc_html__( 'Questa console è riservata agli amministratori del sito.', 'advertrieste' ) . '</p>' .
				'<p class="ac-accesso-alt"><a href="' . esc_url( wp_login_url( self::url() ) ) . '">' .
				esc_html__( 'Accedi', 'advertrieste' ) . '</a></p>' .
				'</div></div>';
		}

		$corrente = get_queried_object_id();
		if ( $corrente && (int) get_option( self::OPTION_PAGE ) !== $corrente ) {
			update_option( self::OPTION_PAGE, $corrente );
		}

		$sezione = self::sezione_corrente();
		$utente  = wp_get_current_user();
		$cerca   = self::ricerca();

		ob_start();
		require ADVTR_PATH . 'templates/console/admin-' . $sezione . '.php';
		$contenuto = (string) ob_get_clean();

		$menu = array();
		foreach ( self::menu() as $gruppo => $voci ) {
			foreach ( $voci as $slug => $etichetta ) {
				$menu[ $gruppo ][] = array(
					'slug'      => $slug,
					'etichetta' => $etichetta,
					'url'       => self::url( $slug ),
				);
			}
		}

		return Console::guscio(
			array(
				'marchio'     => get_bloginfo( 'name' ),
				'menu'        => $menu,
				'attiva'      => $sezione,
				'titolo'      => self::sezioni()[ $sezione ],
				'sottotitolo' => self::sottotitolo( $sezione ),
				'utente'      => array(
					'sigla' => Console::sigla( $utente->display_name ),
					'nome'  => $utente->display_name,
					'ruolo' => __( 'Amministratore', 'advertrieste' ),
					'esci'  => wp_logout_url( home_url( '/' ) ),
				),
				'avviso'      => self::avviso(),
				'contenuto'   => $contenuto,
			)
		);
	}

	/**
	 * Sottotitolo della sezione.
	 *
	 * @param string $sezione Slug.
	 * @return string
	 */
	private static function sottotitolo( $sezione ) {
		$t = array(
			'panoramica'   => __( 'Cosa richiede la tua attenzione', 'advertrieste' ),
			'approvazioni' => __( 'Contenuti in attesa di una tua decisione', 'advertrieste' ),
			'locali'       => __( 'Tutte le attività sulla mappa', 'advertrieste' ),
			'poi'          => __( 'Musei, castelli e monumenti', 'advertrieste' ),
			'offerte'      => __( 'Promozioni e coupon dei clienti', 'advertrieste' ),
			'eventi'       => __( 'Grandi eventi e proposte degli organizzatori', 'advertrieste' ),
			'clienti'      => __( 'Chi gestisce le schede e cosa possiede', 'advertrieste' ),
			'abbonamenti'  => __( 'Validità delle schede e rinnovi', 'advertrieste' ),
			'qr'           => __( 'La rete di espositori sul territorio', 'advertrieste' ),
		);
		return $t[ $sezione ] ?? '';
	}
}
