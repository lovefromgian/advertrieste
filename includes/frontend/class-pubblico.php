<?php
/**
 * Guscio pubblico: tutte le pagine del plugin, con la veste della proposta.
 *
 * Stesso meccanismo della console — `template_include` sostituisce il template
 * del tema con un documento completo — ma applicato al lato pubblico: home,
 * mappa, offerte, eventi e schede attività condividono barra di navigazione,
 * tipografia e colori, invece di essere isole dentro il tema.
 *
 * La console resta separata: ha una struttura sua (sidebar) e regole d'accesso
 * proprie. Qui si occupa solo di ciò che vede il pubblico.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Frontend;

use AdverTrieste\Console\Console;
use AdverTrieste\Console\Pagina;
use AdverTrieste\Cpt\Locale;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Documento e asset delle pagine pubbliche.
 */
class Pubblico {

	/**
	 * Handle del foglio di stile pubblico.
	 *
	 * @var string
	 */
	const HANDLE = 'advtr-pubblico';

	/**
	 * Shortcode che rendono una pagina "del plugin".
	 *
	 * La console NON è qui: ha un guscio proprio (`Frontend\ClientArea`).
	 *
	 * @var string[]
	 */
	const SHORTCODE = array(
		'advtr_home',
		'advtr_map',
		'advtr_onboarding',
		'advtr_offerte',
		'advtr_eventi',
		'advtr_grandi_eventi',
		'advtr_valida_coupon',
		'advtr_statistiche',
	);

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public static function init() {
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
	 * Registra il foglio di stile pubblico.
	 *
	 * @return void
	 */
	public static function registra_asset() {
		wp_register_style( self::HANDLE, ADVTR_URL . 'assets/src/pubblico/pubblico.css', array(), ADVTR_VERSION );
	}

	/**
	 * Siamo su una pagina pubblica del plugin?
	 *
	 * @return bool
	 */
	public static function e_pagina_nostra() {
		if ( is_admin() ) {
			return false;
		}
		// La scheda attività è un CPT, non una pagina con shortcode.
		if ( is_singular( Locale::POST_TYPE ) ) {
			return true;
		}
		return Pagina::pagina_con_shortcode( self::SHORTCODE );
	}

	/**
	 * È l'ingresso guidato? (unica schermata a fondo scuro).
	 *
	 * @return bool
	 */
	public static function e_ingresso() {
		return Pagina::pagina_con_shortcode( array( 'advtr_home' ) );
	}

	/**
	 * Sostituisce il template del tema con il documento del plugin.
	 *
	 * @param string $template Template scelto dal tema.
	 * @return string
	 */
	public static function template( $template ) {
		return self::e_pagina_nostra() ? ADVTR_PATH . 'templates/pubblico/pagina.php' : $template;
	}

	/**
	 * Nasconde la barra di amministrazione.
	 *
	 * @param bool $mostra Valore corrente.
	 * @return bool
	 */
	public static function niente_barra( $mostra ) {
		return self::e_pagina_nostra() ? false : $mostra;
	}

	/**
	 * Toglie gli asset estranei.
	 *
	 * @return void
	 */
	public static function pulisci_asset() {
		if ( self::e_pagina_nostra() ) {
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
		return self::e_pagina_nostra() ? Pagina::filtra_tag( $tag, $handle ) : $tag;
	}

	/**
	 * Compone il contenuto della pagina corrente.
	 *
	 * L'ingresso guidato ha un layout suo; tutto il resto entra nel guscio
	 * interno, con intestazione e contenuto del post (shortcode compresi).
	 *
	 * @return string
	 */
	public static function contenuto() {
		wp_enqueue_style( self::HANDLE );

		if ( self::e_ingresso() ) {
			return Home::shortcode();
		}

		ob_start();
		if ( is_singular( Locale::POST_TYPE ) ) {
			require ADVTR_PATH . 'templates/pubblico/interna-scheda.php';
		} else {
			require ADVTR_PATH . 'templates/pubblico/interna.php';
		}
		return (string) ob_get_clean();
	}

	/**
	 * Registra in anticipo gli asset e compone: l'ordine conta.
	 *
	 * @return string
	 */
	public static function prepara() {
		Console::registra_asset_plugin();
		self::registra_asset();
		return self::contenuto();
	}
}
