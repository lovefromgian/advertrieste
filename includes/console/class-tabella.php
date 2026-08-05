<?php
/**
 * Elenco tabellare riusabile dalle console.
 *
 * Le sezioni dell'amministratore sono quasi tutte "un elenco di cose con
 * qualche azione": senza un componente unico diventerebbero nove tabelle
 * scritte a mano, con nove modi diversi di gestire ricerca, stato vuoto e
 * intestazioni.
 *
 * Le celle accettano testo o markup già costruito dai componenti (pill,
 * bottoni). Nessuna cella riceve mai HTML che arrivi dal database senza
 * passare prima da `esc_html()`.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Console;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Costruzione di elenchi tabellari.
 */
class Tabella {

	/**
	 * Compone un elenco.
	 *
	 * Chiavi accettate in `$conf`:
	 * - `colonne` (string[]) intestazioni;
	 * - `righe` (array) celle già escapate, una lista per riga;
	 * - `vuoto` (string) messaggio quando non c'è nulla da mostrare;
	 * - `ricerca` (string|null) valore corrente; se null il campo non compare;
	 * - `azione` (string) URL del modulo di ricerca;
	 * - `sezione` (string) sezione da conservare nella ricerca.
	 *
	 * @param array<string,mixed> $conf Configurazione dell'elenco.
	 * @return string
	 */
	public static function rendi( array $conf ) {
		$conf = wp_parse_args(
			$conf,
			array(
				'colonne' => array(),
				'righe'   => array(),
				'vuoto'   => __( 'Nessun elemento.', 'advertrieste' ),
				'ricerca' => null,
				'azione'  => '',
				'sezione' => '',
			)
		);

		$html = '';

		if ( null !== $conf['ricerca'] ) {
			$html .= '<form class="ac-cerca" method="get" action="' . esc_url( $conf['azione'] ) . '">';
			$html .= '<input type="hidden" name="sezione" value="' . esc_attr( $conf['sezione'] ) . '" />';
			$html .= '<input type="search" name="q" value="' . esc_attr( $conf['ricerca'] ) . '" placeholder="' .
				esc_attr__( 'Cerca…', 'advertrieste' ) . '" />';
			$html .= '<button type="submit" class="ac-btn ac-btn-neutro">' . esc_html__( 'Cerca', 'advertrieste' ) . '</button>';
			if ( '' !== $conf['ricerca'] ) {
				$html .= '<a class="ac-cerca-azzera" href="' . esc_url( add_query_arg( 'sezione', $conf['sezione'], $conf['azione'] ) ) . '">' .
					esc_html__( 'Azzera', 'advertrieste' ) . '</a>';
			}
			$html .= '</form>';
		}

		if ( ! $conf['righe'] ) {
			return $html . '<div class="ac-vuoto"><p>' . esc_html( $conf['vuoto'] ) . '</p></div>';
		}

		$html .= '<div class="ac-tabella-scorri"><table class="ac-tabella"><thead><tr>';
		foreach ( $conf['colonne'] as $intestazione ) {
			$html .= '<th>' . esc_html( $intestazione ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		foreach ( $conf['righe'] as $riga ) {
			$html .= '<tr>';
			foreach ( $riga as $cella ) {
				$html .= '<td>' . $cella . '</td>';
			}
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		return $html;
	}

	/**
	 * Etichetta colorata.
	 *
	 * @param string $testo    Testo dell'etichetta.
	 * @param string $variante Variante grafica: ok, attesa, oro.
	 * @return string
	 */
	public static function pill( $testo, $variante = '' ) {
		return '<span class="ac-pill ' . esc_attr( $variante ) . '">' . esc_html( $testo ) . '</span>';
	}

	/**
	 * Bottone che invia un'azione POST con nonce.
	 *
	 * Le azioni che cambiano stato non possono essere collegamenti: un GET che
	 * pubblica o sospende viene eseguito da qualunque prefetch del browser.
	 *
	 * Chiavi accettate in `$conf`: `azione` (nome dell'azione), `etichetta`
	 * (testo del bottone), `url` (destinazione), `nonce`, `campi` (campi
	 * nascosti aggiuntivi), `classe` e `conferma` (conferma in due tempi).
	 *
	 * @param array<string,mixed> $conf Configurazione del bottone.
	 * @return string
	 */
	public static function azione( array $conf ) {
		$conf = wp_parse_args(
			$conf,
			array(
				'azione'    => '',
				'etichetta' => '',
				'url'       => '',
				'nonce'     => '',
				'campi'     => array(),
				'classe'    => 'ac-btn ac-btn-neutro',
				'conferma'  => '',
			)
		);

		$html = '<form method="post" action="' . esc_url( $conf['url'] ) . '" class="ac-azione-form"';
		if ( $conf['conferma'] ) {
			$html .= ' data-advtr-conferma="' . esc_attr( $conf['conferma'] ) . '"';
		}
		$html .= '>';
		$html .= wp_nonce_field( $conf['nonce'], '_wpnonce', true, false );
		$html .= '<input type="hidden" name="advtr_azione" value="' . esc_attr( $conf['azione'] ) . '" />';
		foreach ( $conf['campi'] as $nome => $valore ) {
			$html .= '<input type="hidden" name="' . esc_attr( $nome ) . '" value="' . esc_attr( $valore ) . '" />';
		}
		$html .= '<button type="submit" class="' . esc_attr( $conf['classe'] ) . '">' . esc_html( $conf['etichetta'] ) . '</button>';
		$html .= '</form>';
		return $html;
	}
}
