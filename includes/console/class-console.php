<?php
/**
 * Guscio condiviso delle console (cliente e amministratore).
 *
 * Un solo impianto per due pannelli: la struttura — sidebar con voci
 * raggruppate, intestazione, area contenuti — e i componenti (KPI, barre,
 * grafico, avvisi) stanno qui; ogni console fornisce soltanto il proprio menu,
 * i propri permessi e il contenuto della sezione.
 *
 * Le funzioni che restituiscono markup lo restituiscono GIÀ ESCAPATO: chi le
 * usa può stamparle direttamente. È la ragione per cui accettano dati grezzi e
 * non HTML — nessun componente deve diventare un veicolo di markup arbitrario.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Console;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Costruzione del guscio e dei componenti delle console.
 */
class Console {

	/**
	 * Handle degli asset condivisi.
	 *
	 * @var string
	 */
	const HANDLE = 'advtr-console';

	/**
	 * Registra gli asset condivisi.
	 *
	 * @return void
	 */
	public static function registra_asset() {
		wp_register_style( self::HANDLE, ADVTR_URL . 'assets/src/console/console.css', array(), ADVTR_VERSION );
	}

	/**
	 * Compone il guscio completo della console.
	 *
	 * @param array<string,mixed> $conf {
	 *     Configurazione della schermata.
	 *
	 *     @type string $marchio     Nome mostrato in alto nella sidebar.
	 *     @type array  $menu        Gruppi: [ 'Gruppo' => [ ['slug','etichetta','url'], … ] ].
	 *     @type string $attiva      Slug della voce attiva.
	 *     @type string $titolo      Titolo della schermata.
	 *     @type string $sottotitolo Sottotitolo.
	 *     @type string $azioni      Markup già escapato per l'angolo in alto a destra.
	 *     @type array  $utente      [ 'sigla', 'nome', 'ruolo', 'esci' ].
	 *     @type array  $avviso      [ 'tipo', 'titolo', 'testo', 'azione' ] oppure null.
	 *     @type string $contenuto   Markup già escapato della sezione.
	 * }
	 * @return string
	 */
	public static function guscio( array $conf ) {
		$conf = wp_parse_args(
			$conf,
			array(
				'marchio'     => get_bloginfo( 'name' ),
				'menu'        => array(),
				'attiva'      => '',
				'titolo'      => '',
				'sottotitolo' => '',
				'azioni'      => '',
				'utente'      => array(),
				'avviso'      => null,
				'contenuto'   => '',
			)
		);

		ob_start();
		require ADVTR_PATH . 'templates/console/layout.php';
		return (string) ob_get_clean();
	}

	/**
	 * Card di un indicatore, con confronto sul periodo precedente.
	 *
	 * @param string   $etichetta Descrizione del dato.
	 * @param int      $valore    Valore del periodo corrente.
	 * @param int|null $prima     Valore del periodo precedente (null: nessun confronto).
	 * @return string
	 */
	public static function kpi( $etichetta, $valore, $prima = null ) {
		$html  = '<div class="ac-kpi">';
		$html .= '<div class="ac-kpi-eti">' . esc_html( $etichetta ) . '</div>';
		$html .= '<div class="ac-kpi-valore">' . esc_html( number_format_i18n( (int) $valore ) ) . '</div>';

		if ( null !== $prima ) {
			$html .= self::delta( (int) $valore, (int) $prima );
		}

		$html .= '<span class="ac-kpi-barra"></span>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Riga di confronto percentuale con il periodo precedente.
	 *
	 * Il caso "prima = 0" non produce una percentuale: da zero qualunque
	 * aumento sarebbe infinito, e un "+100%" sarebbe una bugia gentile.
	 *
	 * @param int $ora   Valore corrente.
	 * @param int $prima Valore precedente.
	 * @return string
	 */
	private static function delta( $ora, $prima ) {
		if ( 0 === $prima ) {
			$testo = 0 === $ora
				? __( 'nessun dato nel periodo precedente', 'advertrieste' )
				: __( 'primo periodo con dati', 'advertrieste' );
			return '<div class="ac-kpi-delta pari">' . esc_html( $testo ) . '</div>';
		}

		$perc  = ( ( $ora - $prima ) / $prima ) * 100;
		$verso = 'pari';
		$segno = '=';
		if ( $perc > 0.5 ) {
			$verso = 'su';
			$segno = '▲';
		} elseif ( $perc < -0.5 ) {
			$verso = 'giu';
			$segno = '▼';
		}

		return sprintf(
			'<div class="ac-kpi-delta %s">%s %s%%%s</div>',
			esc_attr( $verso ),
			esc_html( $segno ),
			esc_html( number_format_i18n( abs( $perc ), abs( $perc ) < 10 ? 1 : 0 ) ),
			' <span style="font-weight:400">' . esc_html__( 'vs periodo prec.', 'advertrieste' ) . '</span>'
		);
	}

	/**
	 * Elenco di barre orizzontali con percentuale.
	 *
	 * @param array<int,array{etichetta:string,valore:int}> $voci Voci da mostrare.
	 * @return string
	 */
	public static function barre( array $voci ) {
		if ( ! $voci ) {
			return '<p class="ac-card-sottotitolo">' . esc_html__( 'Ancora nessun dato.', 'advertrieste' ) . '</p>';
		}

		$massimo = 0;
		foreach ( $voci as $v ) {
			$massimo = max( $massimo, (int) $v['valore'] );
		}
		$totale = array_sum( wp_list_pluck( $voci, 'valore' ) );

		$html = '<div class="ac-barre">';
		foreach ( $voci as $v ) {
			// La larghezza è relativa al massimo (leggibilità), la percentuale
			// mostrata è invece sul totale (significato).
			$larghezza = $massimo > 0 ? ( (int) $v['valore'] / $massimo ) * 100 : 0;
			$quota     = $totale > 0 ? round( ( (int) $v['valore'] / $totale ) * 100 ) : 0;

			$html .= '<div class="ac-barra-riga">';
			$html .= '<span>' . esc_html( $v['etichetta'] ) . '</span>';
			$html .= '<span class="ac-barra-pista"><span class="ac-barra-riemp" style="width:' . esc_attr( max( 12, $larghezza ) ) . '%">' . esc_html( $quota ) . '%</span></span>';
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Grafico ad area di una serie giornaliera, in SVG puro.
	 *
	 * Nessuna libreria di grafici: la scelta è già registrata in
	 * `docs/architettura.md` §4 per non aggiungere dipendenze al front-end.
	 *
	 * @param array<string,int> $serie Data (Y-m-d) => valore, in ordine.
	 * @return string
	 */
	public static function grafico( array $serie ) {
		if ( count( $serie ) < 2 ) {
			return '<p class="ac-card-sottotitolo">' . esc_html__( 'Servono almeno due giorni di dati per disegnare l\'andamento.', 'advertrieste' ) . '</p>';
		}

		$valori  = array_values( $serie );
		$giorni  = array_keys( $serie );
		$massimo = max( $valori );
		$massimo = $massimo > 0 ? $massimo : 1;

		$larghezza = 640;
		$altezza   = 180;
		$margine   = 8;
		$passo     = ( $larghezza - $margine * 2 ) / ( count( $valori ) - 1 );

		$punti = array();
		foreach ( $valori as $i => $v ) {
			$x       = $margine + $i * $passo;
			$y       = $altezza - $margine - ( $v / $massimo ) * ( $altezza - $margine * 2 );
			$punti[] = round( $x, 1 ) . ',' . round( $y, 1 );
		}

		$linea = implode( ' ', $punti );
		$area  = $margine . ',' . ( $altezza - $margine ) . ' ' . $linea . ' ' . ( $larghezza - $margine ) . ',' . ( $altezza - $margine );

		$id = 'ac-grad-' . wp_unique_id();

		$svg  = '<svg class="ac-grafico" viewBox="0 0 ' . $larghezza . ' ' . $altezza . '" role="img" aria-label="' . esc_attr__( 'Andamento del periodo', 'advertrieste' ) . '">';
		$svg .= '<defs><linearGradient id="' . esc_attr( $id ) . '" x1="0" y1="0" x2="0" y2="1">';
		$svg .= '<stop offset="0%" stop-color="#2f6b4f" stop-opacity="0.22"/>';
		$svg .= '<stop offset="100%" stop-color="#2f6b4f" stop-opacity="0"/>';
		$svg .= '</linearGradient></defs>';
		$svg .= '<polygon points="' . esc_attr( $area ) . '" fill="url(#' . esc_attr( $id ) . ')"/>';
		$svg .= '<polyline points="' . esc_attr( $linea ) . '" fill="none" stroke="#2f6b4f" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';

		// Un punto ogni sette giorni, per non affollare la linea.
		foreach ( $punti as $i => $p ) {
			if ( 0 !== $i % 7 && count( $punti ) - 1 !== $i ) {
				continue;
			}
			list( $x, $y ) = explode( ',', $p );
			$svg          .= '<circle cx="' . esc_attr( $x ) . '" cy="' . esc_attr( $y ) . '" r="3" fill="#fff" stroke="#2f6b4f" stroke-width="2"/>';
		}
		$svg .= '</svg>';

		// Asse: primo, mediano e ultimo giorno.
		$eti  = array( $giorni[0], $giorni[ (int) floor( count( $giorni ) / 2 ) ], end( $giorni ) );
		$svg .= '<div class="ac-grafico-asse">';
		foreach ( $eti as $g ) {
			$svg .= '<span>' . esc_html( wp_date( 'j M', strtotime( $g ) ) ) . '</span>';
		}
		$svg .= '</div>';

		return $svg;
	}

	/**
	 * Sigla di due lettere da un nome, per l'avatar testuale.
	 *
	 * @param string $nome Nome completo.
	 * @return string
	 */
	public static function sigla( $nome ) {
		$parole = preg_split( '/\s+/', trim( wp_strip_all_tags( (string) $nome ) ) );
		$sigla  = '';
		foreach ( array_slice( (array) $parole, 0, 2 ) as $p ) {
			if ( '' !== $p ) {
				$sigla .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
			}
		}
		return '' !== $sigla ? $sigla : '—';
	}
}
