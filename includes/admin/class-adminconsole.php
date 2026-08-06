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
use AdverTrieste\Frontend\ClientArea;

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
	 * Durate di abbonamento offerte in console, in giorni.
	 *
	 * @var int[]
	 */
	const DURATE = array( 30, 90, 180, 365 );

	/**
	 * Template di dettaglio per sezione.
	 *
	 * @var array<string,string>
	 */
	const DETTAGLI = array(
		'locali'  => 'admin-locale',
		'poi'     => 'admin-poi-dettaglio',
		'offerte' => 'admin-offerta',
		'eventi'  => 'admin-evento',
		'qr'      => 'admin-qr-dettaglio',
		'clienti' => 'admin-cliente',
	);

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
	 * ID dell'elemento aperto in dettaglio, se valido.
	 *
	 * @return int
	 */
	public static function id_corrente() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola navigazione.
		$id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		if ( ! $id ) {
			return 0;
		}
		// Nella sezione clienti l'id è di un utente, non di un post.
		if ( 'clienti' === self::sezione_corrente() ) {
			return get_userdata( $id ) ? $id : 0;
		}
		return get_post( $id ) ? $id : 0;
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
			'abbonamento_attivato' => array( 'ok', __( 'Abbonamento attivato. La scheda non è stata pubblicata: falla tu quando è pronta.', 'advertrieste' ) ),
			'abbonamento_ripreso'  => array( 'ok', __( 'Abbonamento attivato e scheda rimessa online: era stata sospesa alla scadenza.', 'advertrieste' ) ),
			'data_non_valida'      => array( 'errore', __( 'La data di decorrenza non è valida.', 'advertrieste' ) ),
			'evidenza_on'  => array( 'ok', __( 'Piano In Evidenza attivato.', 'advertrieste' ) ),
			'evidenza_off' => array( 'ok', __( 'Piano In Evidenza disattivato.', 'advertrieste' ) ),
			'password_ok'  => array( 'ok', __( 'Password impostata. Comunicala al cliente: da qui non è più leggibile.', 'advertrieste' ) ),
			'password_corta' => array(
				'errore',
				sprintf(
					/* translators: %d: numero minimo di caratteri */
					__( 'La password deve avere almeno %d caratteri.', 'advertrieste' ),
					Salva::PASSWORD_MIN
				),
			),
			'password_link_ok' => array( 'ok', __( 'Email inviata: il cliente trova nella posta il link per impostarsi la password.', 'advertrieste' ) ),
			'password_link_ko' => array( 'errore', __( 'Non è stato possibile inviare l\'email. Imposta tu una password e comunicagliela.', 'advertrieste' ) ),
			'creato_cliente_pass' => array( 'ok', __( 'Account creato con la password che hai scelto: nessuna email inviata, le credenziali le consegni tu.', 'advertrieste' ) ),
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
		$giorni  = isset( $_POST['advtr_giorni'] ) ? intval( wp_unslash( $_POST['advtr_giorni'] ) ) : 0;
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
			case 'attiva_abbonamento':
				self::redirect( 'abbonamenti', self::attiva_abbonamento() );
				break;
			case 'evidenza':
				self::redirect( $sezione, self::evidenza( $id ) );
				break;
			case 'crea':
				$nuovo = Salva::crea( $sezione );
				if ( ! $nuovo ) {
					self::redirect( $sezione, 'negato' );
				}
				self::redirect_dettaglio( $sezione, $nuovo, 'creato' );
				break;
			case 'crea_cliente':
				$esito = Salva::crea_cliente();
				if ( $esito['id'] ) {
					self::redirect_dettaglio( 'clienti', $esito['id'], $esito['esito'] );
				}
				self::redirect( 'clienti', $esito['esito'] );
				break;
			case 'cestina':
				self::redirect( $sezione, Salva::cestina( $id ) );
				break;
			case 'ripristina':
				self::redirect( $sezione, Salva::ripristina( $id ), array( 'cestino' => 1 ) );
				break;
			case 'elimina':
				self::redirect( $sezione, Salva::elimina( $id ), array( 'cestino' => 1 ) );
				break;
			case 'elimina_cliente':
				self::redirect( 'clienti', Salva::elimina_cliente( $id ) );
				break;
			case 'poi_salva':
				self::redirect_dettaglio( 'poi', $id, Salva::poi() );
				break;
			case 'qr_salva':
				self::redirect_dettaglio( 'qr', $id, Salva::qr() );
				break;
			case 'offerta_salva':
				self::redirect_dettaglio( 'offerte', $id, Salva::offerta() );
				break;
			case 'evento_salva':
				self::redirect_dettaglio( 'eventi', $id, Salva::evento() );
				break;
			case 'password_cliente':
				self::redirect_dettaglio( 'clienti', $id, Salva::password() );
				break;
			case 'password_link':
				self::redirect_dettaglio( 'clienti', $id, Salva::password_link() );
				break;
			case 'cliente_salva':
				self::redirect_dettaglio( 'clienti', $id, Salva::cliente() );
				break;
			case 'scheda_salva':
				$esito = Scheda::salva();
				wp_safe_redirect(
					self::url(
						'locali',
						array(
							'id'     => $id,
							'avviso' => $esito,
						)
					)
				);
				exit;
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
		// Solo le durate che la console propone davvero: un numero arrivato da
		// una richiesta costruita a mano non deve poter allungare la validità
		// di quanto gli pare.
		if ( ! $post || Locale::POST_TYPE !== $post->post_type || ! in_array( $giorni, self::DURATE, true ) ) {
			return 'negato';
		}

		// Stessa logica del rinnovo via WooCommerce: una sola definizione.
		\AdverTrieste\Payments\WooBridge::extend_validity( $id, $giorni );
		return 'rinnovato';
	}

	/**
	 * Attiva un abbonamento su una scheda.
	 *
	 * Non è un rinnovo con un altro nome: il rinnovo somma giorni a una
	 * validità che esiste già, questo fissa la finestra da capo — decorrenza
	 * e scadenza — e serve quando una scheda non ne ha mai avuta una, o
	 * quando va rifatta dopo un contratto nuovo.
	 *
	 * Non pubblica la scheda: se non è mai stata online, metterla in vetrina
	 * è una decisione separata, e una scheda a metà non deve finire sulla
	 * mappa perché è stata pagata. Fa eccezione la scheda sospesa dal cron
	 * per scadenza: era già pubblica, e il pagamento la rimette dov'era.
	 *
	 * @return string Codice di esito.
	 */
	private static function attiva_abbonamento() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verificato dal controller.
		$id     = isset( $_POST['advtr_locale'] ) ? absint( wp_unslash( $_POST['advtr_locale'] ) ) : 0;
		$giorni = isset( $_POST['advtr_durata'] ) ? intval( wp_unslash( $_POST['advtr_durata'] ) ) : 0;
		$inizio = isset( $_POST['advtr_decorrenza'] ) ? sanitize_text_field( wp_unslash( $_POST['advtr_decorrenza'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$post = $id ? get_post( $id ) : null;
		if ( ! $post || Locale::POST_TYPE !== $post->post_type ) {
			return 'negato';
		}
		if ( ! in_array( $giorni, self::DURATE, true ) ) {
			return 'negato';
		}

		// Una data storta è meglio rifiutarla che interpretarla: un abbonamento
		// che parte da un giorno inventato sballa scadenze, avvisi e sospensioni.
		$inizio_ts = '' !== $inizio ? strtotime( $inizio . ' 00:00:00' ) : strtotime( current_time( 'Y-m-d' ) . ' 00:00:00' );
		if ( ! $inizio_ts ) {
			return 'data_non_valida';
		}

		$sospesa = (bool) get_post_meta( $id, 'advtr_sospesa', true );

		update_post_meta( $id, 'advtr_data_inizio', gmdate( 'Y-m-d', $inizio_ts ) );
		update_post_meta( $id, 'advtr_data_fine', gmdate( 'Y-m-d', $inizio_ts + $giorni * DAY_IN_SECONDS ) );

		// Gli avvisi già inviati si riferiscono alla scadenza vecchia.
		delete_post_meta( $id, 'advtr_sospesa' );
		delete_post_meta( $id, 'advtr_scadenza_avvisi' );

		if ( $sospesa && 'draft' === get_post_status( $id ) ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
			return 'abbonamento_ripreso';
		}

		return 'abbonamento_attivato';
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
	 * Reindirizza alla schermata di dettaglio con un esito.
	 *
	 * @param string $sezione Sezione.
	 * @param int    $id      ID dell'elemento.
	 * @param string $avviso  Codice di esito.
	 * @return void
	 */
	private static function redirect_dettaglio( $sezione, $id, $avviso ) {
		wp_safe_redirect(
			self::url(
				$sezione,
				array(
					'id'     => $id,
					'avviso' => $avviso,
				)
			)
		);
		exit;
	}

	/**
	 * Reindirizza con un esito.
	 *
	 * @param string              $sezione Sezione.
	 * @param string              $avviso  Codice.
	 * @param array<string,mixed> $extra   Parametri da conservare nell'URL.
	 * @return void
	 */
	private static function redirect( $sezione = '', $avviso = '', $extra = array() ) {
		$args = $extra;
		if ( $avviso ) {
			$args['avviso'] = $avviso;
		}
		wp_safe_redirect( self::url( $sezione, $args ) );
		exit;
	}

	/**
	 * Bottone "Aggiungi" per una sezione.
	 *
	 * @param string $sezione   Sezione.
	 * @param string $etichetta Testo del bottone.
	 * @return string Markup già escapato.
	 */
	public static function bottone_nuovo( $sezione, $etichetta ) {
		return Tabella::azione(
			array(
				'azione'    => 'crea',
				'etichetta' => $etichetta,
				'url'       => self::url( $sezione ),
				'nonce'     => self::NONCE,
				'classe'    => 'ac-btn ac-btn-verde',
				'campi'     => array( 'advtr_sezione' => $sezione ),
			)
		);
	}

	/**
	 * Stiamo guardando il cestino?
	 *
	 * @return bool
	 */
	public static function mostra_cestino() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola navigazione.
		return isset( $_GET['cestino'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cestino'] ) );
	}

	/**
	 * Stati dei post da mostrare nell'elenco corrente.
	 *
	 * @param string[] $normali Stati mostrati fuori dal cestino.
	 * @return string[]
	 */
	public static function stati_elenco( array $normali ) {
		return self::mostra_cestino() ? array( 'trash' ) : $normali;
	}

	/**
	 * Barra con il passaggio fra elenco e cestino.
	 *
	 * @param string $sezione Sezione.
	 * @return string Markup già escapato.
	 */
	public static function link_cestino( $sezione ) {
		if ( self::mostra_cestino() ) {
			return '<a class="ac-btn ac-btn-neutro" href="' . esc_url( self::url( $sezione ) ) . '">' .
				esc_html__( '← Torna all\'elenco', 'advertrieste' ) . '</a>';
		}
		return '<a class="ac-btn ac-btn-neutro" href="' . esc_url( self::url( $sezione, array( 'cestino' => 1 ) ) ) . '">' .
			esc_html__( 'Cestino', 'advertrieste' ) . '</a>';
	}

	/**
	 * Azioni di eliminazione per una riga.
	 *
	 * Nel cestino si può ripristinare o eliminare per sempre; fuori si cestina.
	 * Due decisioni distinte, mai una sola.
	 *
	 * @param string $sezione   Sezione.
	 * @param int    $id        ID dell'elemento.
	 * @param bool   $cestinato L'elemento è nel cestino.
	 * @return string Markup già escapato.
	 */
	public static function azioni_cestino( $sezione, $id, $cestinato ) {
		if ( ! $cestinato ) {
			return Tabella::azione(
				array(
					'azione'    => 'cestina',
					'etichetta' => __( 'Cestina', 'advertrieste' ),
					'url'       => self::url( $sezione ),
					'nonce'     => self::NONCE,
					'classe'    => 'ac-btn ac-btn-fragile',
					'conferma'  => __( 'Cestinare?', 'advertrieste' ),
					'campi'     => array(
						'advtr_id'      => $id,
						'advtr_sezione' => $sezione,
					),
				)
			);
		}

		return Tabella::azione(
			array(
				'azione'    => 'ripristina',
				'etichetta' => __( 'Ripristina', 'advertrieste' ),
				'url'       => self::url( $sezione ),
				'nonce'     => self::NONCE,
				'classe'    => 'ac-btn ac-btn-verde',
				'campi'     => array(
					'advtr_id'      => $id,
					'advtr_sezione' => $sezione,
				),
			)
		) . Tabella::azione(
			array(
				'azione'    => 'elimina',
				'etichetta' => __( 'Elimina per sempre', 'advertrieste' ),
				'url'       => self::url( $sezione ),
				'nonce'     => self::NONCE,
				'classe'    => 'ac-btn ac-btn-fragile',
				'conferma'  => __( 'Definitivamente?', 'advertrieste' ),
				'campi'     => array(
					'advtr_id'      => $id,
					'advtr_sezione' => $sezione,
				),
			)
		);
	}

	/**
	 * Messaggio di elenco vuoto, consapevole della vista.
	 *
	 * Nel cestino "Nessuna offerta" farebbe pensare a un guasto: quello che manca
	 * non sono le offerte, è la roba buttata via.
	 *
	 * @param string $normale Messaggio dell'elenco vivo.
	 * @return string
	 */
	public static function vuoto( $normale ) {
		return self::mostra_cestino() ? __( 'Il cestino è vuoto.', 'advertrieste' ) : $normale;
	}

	/**
	 * Riquadro di eliminazione in fondo a una scheda di dettaglio.
	 *
	 * Sta in fondo e non fra i campi: chi apre una scheda per correggere un
	 * orario non deve trovarsi il bottone che la cancella accanto a "Salva".
	 *
	 * @param string $sezione Sezione.
	 * @param int    $id      ID dell'elemento.
	 * @param string $nota    Testo esplicativo aggiuntivo.
	 * @return string Markup già escapato.
	 */
	public static function zona_pericolosa( $sezione, $id, $nota = '' ) {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post ) {
			return '';
		}
		$cestinato = 'trash' === $post->post_status;

		if ( '' === $nota ) {
			$nota = $cestinato
				? __( 'È nel cestino: non compare più da nessuna parte. Puoi ancora riportarlo indietro, oppure eliminarlo per sempre.', 'advertrieste' )
				: __( 'Finisce nel cestino, da dove puoi ancora recuperarlo. Statistiche e riscatti restano collegati finché non lo elimini per sempre.', 'advertrieste' );
		}

		return '<div class="ac-card ac-zona-fragile">' .
			'<h3 class="ac-card-titolo">' . esc_html__( 'Eliminazione', 'advertrieste' ) . '</h3>' .
			'<p class="ac-card-sottotitolo">' . esc_html( $nota ) . '</p>' .
			'<div class="ac-azioni-cella">' . self::azioni_cestino( $sezione, $id, $cestinato ) . '</div>' .
			'</div>';
	}

	/**
	 * Riquadro di eliminazione di un account cliente.
	 *
	 * @param int $user_id Utente.
	 * @return string Markup già escapato.
	 */
	public static function zona_pericolosa_cliente( $user_id ) {
		$utente = $user_id ? get_userdata( $user_id ) : null;
		if ( ! $utente || user_can( $utente, 'manage_options' ) || get_current_user_id() === (int) $user_id ) {
			return '';
		}

		return '<div class="ac-card ac-zona-fragile">' .
			'<h3 class="ac-card-titolo">' . esc_html__( 'Eliminazione dell\'account', 'advertrieste' ) . '</h3>' .
			'<p class="ac-card-sottotitolo">' .
			esc_html__( 'L\'accesso viene chiuso subito. Le sue schede, offerte ed eventi non spariscono: passano a te, e potrai riassegnarli a un altro account.', 'advertrieste' ) .
			'</p><div class="ac-azioni-cella">' .
			Tabella::azione(
				array(
					'azione'    => 'elimina_cliente',
					'etichetta' => __( 'Elimina questo account', 'advertrieste' ),
					'url'       => self::url( 'clienti' ),
					'nonce'     => self::NONCE,
					'classe'    => 'ac-btn ac-btn-fragile',
					'conferma'  => __( 'Sicuro? I contenuti passano a te', 'advertrieste' ),
					'campi'     => array(
						'advtr_id'      => (int) $user_id,
						'advtr_sezione' => 'clienti',
					),
				)
			) . '</div></div>';
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
			'post_status'    => self::stati_elenco( array( 'publish', 'pending', 'draft' ) ),
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
				'<div class="ac-accesso-marchio">' .
				'<img class="ac-logo ac-logo-accesso" src="' . esc_url( ADVTR_URL . 'assets/img/logo-scuro.png' ) . '" ' .
				'alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" width="480" height="333" /></div>' .
				'<h1 class="ac-accesso-titolo">' . esc_html__( 'Area riservata', 'advertrieste' ) . '</h1>' .
				'<p class="ac-accesso-nota">' . esc_html__( 'Questa console è riservata agli amministratori del sito.', 'advertrieste' ) . '</p>' .
				'<p class="ac-accesso-alt"><a href="' . esc_url( wp_login_url( self::url() ) ) . '">' .
				esc_html__( 'Accedi', 'advertrieste' ) . '</a></p>' .
				'</div></div>';
		}

		// Il dettaglio usa il selettore di posizione e la ricerca indirizzo:
		// stessi asset dell'area clienti, nessun duplicato.
		wp_enqueue_style( 'leaflet' );
		wp_enqueue_script( 'leaflet' );
		wp_enqueue_style( ClientArea::HANDLE );
		wp_enqueue_script( ClientArea::HANDLE );
		wp_localize_script(
			ClientArea::HANDLE,
			'advtrCliente',
			array(
				'geocode' => rest_url( 'advertrieste/v1/geocode' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'ricerca'        => __( 'Ricerca in corso…', 'advertrieste' ),
					'trovato'        => __( 'Trovato. Trascina il segnaposto sul punto esatto.', 'advertrieste' ),
					'senzaIndirizzo' => __( 'Scrivi prima l\'indirizzo nel riquadro Contatti.', 'advertrieste' ),
					'errore'         => __( 'Ricerca non riuscita: posiziona il segnaposto a mano.', 'advertrieste' ),
				),
			)
		);

		$corrente = get_queried_object_id();
		if ( $corrente && (int) get_option( self::OPTION_PAGE ) !== $corrente ) {
			update_option( self::OPTION_PAGE, $corrente );
		}

		$sezione = self::sezione_corrente();
		$utente  = wp_get_current_user();
		$cerca   = self::ricerca();
		$id      = self::id_corrente();

		// Con un id valido si apre il dettaglio, non l'elenco: è ciò che fa il
		// pulsante "Apri", che deve restare dentro la console.
		$vista = ( $id && isset( self::DETTAGLI[ $sezione ] ) )
			? self::DETTAGLI[ $sezione ]
			: 'admin-' . $sezione;

		ob_start();
		require ADVTR_PATH . 'templates/console/' . $vista . '.php';
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
				'titolo'      => $id ? self::titolo_dettaglio( $sezione, $id ) : self::sezioni()[ $sezione ],
				'sottotitolo' => $id
					? __( 'Modifica la scheda senza uscire dalla console', 'advertrieste' )
					: self::sottotitolo( $sezione ),
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
	 * Titolo della schermata di dettaglio.
	 *
	 * @param string $sezione Sezione.
	 * @param int    $id      ID dell'elemento.
	 * @return string
	 */
	private static function titolo_dettaglio( $sezione, $id ) {
		if ( 'clienti' === $sezione ) {
			$u = get_userdata( $id );
			return $u ? $u->display_name : '';
		}
		return get_the_title( $id );
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
