<?php
/**
 * Pagine servite interamente dal plugin, senza il tema attorno.
 *
 * Usata sia dalla console cliente sia dalle pagine pubbliche disegnate sui
 * mockup della proposta: entrambe sostituiscono il template del tema con un
 * documento completo e tengono fuori gli asset che non sono del plugin.
 *
 * La regola sugli asset è "viene da questo plugin", non un elenco di handle: un
 * elenco va aggiornato a ogni asset nuovo, e dimenticarne uno rompe una sezione
 * in silenzio.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Console;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utilità condivise delle pagine a documento intero.
 */
class Pagina {

	/**
	 * L'asset può restare su una pagina del plugin?
	 *
	 * @param \WP_Dependencies $registro Registro degli stili o degli script.
	 * @param string           $handle   Handle da valutare.
	 * @return bool
	 */
	public static function asset_ammesso( $registro, $handle ) {
		/**
		 * Handle da conservare anche se esterni al plugin.
		 *
		 * @param string[] $ammessi Elenco di handle.
		 */
		$ammessi = apply_filters( 'advtr_console_asset_consentiti', array() );
		if ( in_array( $handle, $ammessi, true ) ) {
			return true;
		}

		$src = isset( $registro->registered[ $handle ] ) ? $registro->registered[ $handle ]->src : '';

		if ( ! is_string( $src ) || '' === $src ) {
			// Handle senza file proprio. Non sono innocui: i temi li usano per
			// iniettare CSS inline — betheme ci mette anche lo sfondo di `html`,
			// che trapelerebbe dietro la pagina. Passano solo i nostri.
			return 0 === strpos( $handle, 'advtr' );
		}

		return 0 === strpos( $src, ADVTR_URL );
	}

	/**
	 * Toglie dalla coda gli asset estranei.
	 *
	 * @return void
	 */
	public static function pulisci_asset() {
		foreach ( wp_styles()->queue as $handle ) {
			if ( ! self::asset_ammesso( wp_styles(), $handle ) ) {
				wp_dequeue_style( $handle );
			}
		}
		foreach ( wp_scripts()->queue as $handle ) {
			if ( ! self::asset_ammesso( wp_scripts(), $handle ) ) {
				wp_dequeue_script( $handle );
			}
		}
	}

	/**
	 * Sopprime il tag degli asset estranei.
	 *
	 * Ultima rete: alcuni plugin stampano fuori dalla coda, e toglierli dalla
	 * coda non basta — revslider lo faceva.
	 *
	 * @param string $tag    Markup del tag.
	 * @param string $handle Handle dell'asset.
	 * @return string Stringa vuota se l'asset va soppresso.
	 */
	public static function filtra_tag( $tag, $handle ) {
		$registro = 'style_loader_tag' === current_filter() ? wp_styles() : wp_scripts();
		return self::asset_ammesso( $registro, $handle ) ? $tag : '';
	}

	/**
	 * Stampa `wp_head()` senza i tag viewport altrui.
	 *
	 * Temi e plugin ne aggiungono uno proprio, spesso con `maximum-scale=1`, che
	 * impedisce di ingrandire la pagina con le dita: su telefono è un ostacolo di
	 * accessibilità, non una scelta di stile. Poiché il documento è nostro, il
	 * viewport lo dichiariamo noi e togliamo i duplicati.
	 *
	 * @return void
	 */
	public static function stampa_head() {
		ob_start();
		wp_head();
		$head = (string) ob_get_clean();

		$head = preg_replace( '#<meta[^>]*name=["\']viewport["\'][^>]*>#i', '', $head );

		echo $head; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output di wp_head, già escapato dal core e dai plugin.
	}

	/**
	 * La pagina interrogata contiene uno degli shortcode indicati?
	 *
	 * @param string[] $shortcode Tag da cercare.
	 * @return bool
	 */
	public static function pagina_con_shortcode( array $shortcode ) {
		if ( is_admin() ) {
			return false;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		foreach ( $shortcode as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return true;
			}
		}
		return false;
	}
}
