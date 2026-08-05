<?php
/**
 * Home pubblica — l'ingresso guidato (§1.1, schermata 05 della proposta).
 *
 * Prende l'intera pagina, con la barra di navigazione del progetto al posto di
 * quella del tema: la home del documento è una schermata a sé, non un contenuto
 * incorniciato da un tema generico.
 *
 * L'utente parte da un'intenzione o da una ricerca libera; entrambe portano alla
 * mappa già filtrata. La ricerca non è decorativa: il termine viaggia fino
 * all'endpoint dei marker.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Frontend;

use AdverTrieste\Console\Pagina;
use AdverTrieste\Cpt\Categoria;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ingresso guidato a tutta pagina.
 */
class Home {

	/**
	 * Handle degli asset pubblici.
	 *
	 * @var string
	 */
	const HANDLE = 'advtr-pubblico';

	/**
	 * Shortcode che attivano la pagina intera pubblica.
	 *
	 * @var string[]
	 */
	const SHORTCODE = array( 'advtr_home' );

	/**
	 * Intenzioni della schermata d'ingresso: slug => [icona, titolo, dettaglio].
	 *
	 * Titoli e descrizioni ricalcano il documento ("Bere qualcosa", non "Bere"):
	 * sono inviti all'azione, non nomi di tassonomia. Le categorie che non sono
	 * qui elencate compaiono comunque, con il nome del termine.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function intenzioni() {
		return array(
			'mangiare' => array(
				'icona'  => '🍴',
				'titolo' => __( 'Mangiare', 'advertrieste' ),
				'dett'   => __( 'Ristoranti, trattorie, street food', 'advertrieste' ),
			),
			'bere'     => array(
				'icona'  => '☕',
				'titolo' => __( 'Bere qualcosa', 'advertrieste' ),
				'dett'   => __( 'Bar, caffè, enoteche', 'advertrieste' ),
			),
			'shopping' => array(
				'icona'  => '🛍️',
				'titolo' => __( 'Fare shopping', 'advertrieste' ),
				'dett'   => __( 'Negozi e botteghe', 'advertrieste' ),
			),
			'visitare' => array(
				'icona'  => '🏛️',
				'titolo' => __( 'Visitare', 'advertrieste' ),
				'dett'   => __( 'Musei, castelli, monumenti', 'advertrieste' ),
			),
			'servizi'  => array(
				'icona'  => '💈',
				'titolo' => __( 'Servizi', 'advertrieste' ),
				'dett'   => __( 'Parrucchieri, estetica, altro', 'advertrieste' ),
			),
		);
	}

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'advtr_home', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'registra_asset' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'niente_barra' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'pulisci_asset' ), 999 );
		add_action( 'wp_print_styles', array( __CLASS__, 'pulisci_asset' ), 1 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'pulisci_asset' ), 1 );
		add_filter( 'style_loader_tag', array( __CLASS__, 'filtra_tag' ), 10, 2 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'filtra_tag' ), 10, 2 );
	}

	/**
	 * Registra gli asset pubblici.
	 *
	 * @return void
	 */
	public static function registra_asset() {
		wp_register_style( self::HANDLE, ADVTR_URL . 'assets/src/pubblico/pubblico.css', array(), ADVTR_VERSION );
	}

	/**
	 * Siamo sulla home del progetto?
	 *
	 * @return bool
	 */
	public static function e_home() {
		return Pagina::pagina_con_shortcode( self::SHORTCODE );
	}

	/**
	 * Sostituisce il template del tema con il documento intero.
	 *
	 * @param string $template Template scelto dal tema.
	 * @return string
	 */
	public static function template( $template ) {
		return self::e_home() ? ADVTR_PATH . 'templates/pubblico/pagina.php' : $template;
	}

	/**
	 * Nasconde la barra di amministrazione sulla home.
	 *
	 * @param bool $mostra Valore corrente.
	 * @return bool
	 */
	public static function niente_barra( $mostra ) {
		return self::e_home() ? false : $mostra;
	}

	/**
	 * Toglie gli asset estranei.
	 *
	 * @return void
	 */
	public static function pulisci_asset() {
		if ( self::e_home() ) {
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
		return self::e_home() ? Pagina::filtra_tag( $tag, $handle ) : $tag;
	}

	/**
	 * URL della pagina mappa, con eventuali filtri già applicati.
	 *
	 * @param array<string,string> $args Parametri da aggiungere.
	 * @return string
	 */
	public static function url_mappa( $args = array() ) {
		$pagina = get_page_by_path( 'mappa' );
		$base   = $pagina ? get_permalink( $pagina ) : home_url( '/' );
		return $args ? add_query_arg( $args, $base ) : $base;
	}

	/**
	 * Ordina i termini come nella proposta, non alfabeticamente.
	 *
	 * L'ordine delle schede è una scelta di prodotto — si parte da mangiare, che
	 * è la ricerca più frequente — non un dettaglio tipografico. Le categorie
	 * aggiunte in futuro e non previste qui finiscono in coda, in ordine di nome.
	 *
	 * @param \WP_Term[] $termini Termini da ordinare.
	 * @return \WP_Term[]
	 */
	private static function ordina_per_intenzione( array $termini ) {
		$ordine = array_keys( self::intenzioni() );

		usort(
			$termini,
			static function ( $a, $b ) use ( $ordine ) {
				$pa = array_search( $a->slug, $ordine, true );
				$pb = array_search( $b->slug, $ordine, true );
				$pa = false === $pa ? PHP_INT_MAX : $pa;
				$pb = false === $pb ? PHP_INT_MAX : $pb;
				return $pa === $pb ? strcmp( $a->name, $b->name ) : $pa - $pb;
			}
		);

		return $termini;
	}

	/**
	 * Rende la home.
	 *
	 * @return string
	 */
	public static function shortcode() {
		wp_enqueue_style( self::HANDLE );

		$termini = get_terms(
			array(
				'taxonomy'   => Categoria::TAXONOMY,
				'hide_empty' => false,
			)
		);
		$termini = is_array( $termini ) ? self::ordina_per_intenzione( $termini ) : array();

		ob_start();
		require ADVTR_PATH . 'templates/pubblico/home.php';
		return (string) ob_get_clean();
	}
}
