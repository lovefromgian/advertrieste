<?php
/**
 * Strumenti condivisi dalle suite di console e area clienti.
 *
 * Ogni suite è uno script autonomo da lanciare con `wp eval-file`. Il motivo
 * per cui non girano tutte nello stesso processo è concreto: WordPress ricorda
 * quali script e fogli di stile ha già stampato, quindi rendere due sezioni
 * nella stessa richiesta fa risultare "mancanti" asset che in realtà erano solo
 * già stati emessi. Un processo per suite, e il problema non si pone.
 *
 * Le azioni di scrittura vengono provate via HTTP vero, non chiamando i metodi:
 * un bug che ha attraversato diverse revisioni ("Il link che hai seguito è
 * scaduto") era invisibile ai test che saltavano gli hook.
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['advtr_test'] = array(
	'passati' => 0,
	'falliti' => 0,
);

if ( ! function_exists( 'advtr_test_verifica' ) ) {
	/**
	 * Registra un'asserzione.
	 *
	 * @param string $etichetta Cosa si sta verificando, in italiano leggibile.
	 * @param bool   $esito     Condizione attesa.
	 * @param string $dettaglio Valore reale, mostrato solo se fallisce.
	 * @return bool L'esito, per poterlo concatenare.
	 */
	function advtr_test_verifica( $etichetta, $esito, $dettaglio = '' ) {
		if ( $esito ) {
			++$GLOBALS['advtr_test']['passati'];
			echo "  \u{2713} {$etichetta}\n";
			return true;
		}
		++$GLOBALS['advtr_test']['falliti'];
		echo "  \u{2717} FAIL: {$etichetta}" . ( '' !== $dettaglio ? " ({$dettaglio})" : '' ) . "\n";
		return false;
	}
}

if ( ! function_exists( 'ok' ) ) {
	/**
	 * Alias breve, usato dalle suite più vecchie.
	 *
	 * @param bool   $condizione Condizione attesa.
	 * @param string $etichetta  Cosa si sta verificando.
	 * @return bool
	 */
	function ok( $condizione, $etichetta ) {
		return advtr_test_verifica( $etichetta, (bool) $condizione );
	}
}

if ( ! function_exists( 'advtr_test_riepilogo' ) ) {
	/**
	 * Stampa il totale e termina con codice 1 se qualcosa è fallito.
	 *
	 * Il codice di uscita serve al lanciatore (e a un domani in CI): senza,
	 * una suite rossa passerebbe inosservata in mezzo all'output.
	 *
	 * @return void
	 */
	function advtr_test_riepilogo() {
		$s = $GLOBALS['advtr_test'];
		printf( "\nRISULTATO: %d passati, %d falliti\n", $s['passati'], $s['falliti'] );
		if ( $s['falliti'] > 0 ) {
			exit( 1 );
		}
	}
}

if ( ! function_exists( 'advtr_test_sessione' ) ) {
	/**
	 * Cookie di sessione per un utente, senza passare da una password.
	 *
	 * Il token viene firmato direttamente: serve a provare i percorsi
	 * autenticati via HTTP, non a simulare un accesso umano.
	 *
	 * @param int $user_id Utente.
	 * @return string Cookie pronto per l'header `Cookie:`.
	 */
	function advtr_test_sessione( $user_id ) {
		$scadenza = time() + HOUR_IN_SECONDS;
		$token    = wp_generate_password( 43, false, false );
		WP_Session_Tokens::get_instance( $user_id )->update( $token, array( 'expiration' => $scadenza ) );

		$cookie = wp_generate_auth_cookie( $user_id, $scadenza, 'logged_in', $token );

		// Serve anche a questo processo: wp_create_nonce() lega il nonce al
		// token di sessione, e lo legge da $_COOKIE.
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
		wp_set_current_user( $user_id );

		// Pronto per l'header: nome e valore insieme, così chi lo usa non deve
		// ricordarsi il nome della costante.
		return LOGGED_IN_COOKIE . '=' . $cookie;
	}
}

if ( ! function_exists( 'advtr_test_posta' ) ) {
	/**
	 * Invia un'azione della console e restituisce il codice di avviso.
	 *
	 * Un tentativo di rinvio in caso di errore di trasporto: il server locale
	 * ogni tanto non risponde entro il timeout, e un intoppo di rete non è un
	 * fallimento del codice. Un secondo errore invece viene riportato.
	 *
	 * @param string              $url    Destinazione.
	 * @param string              $cookie Cookie di sessione.
	 * @param string              $nonce  Nonce dell'azione.
	 * @param array<string,mixed> $campi  Campi del modulo.
	 * @return string Codice di avviso letto dal redirect, o una descrizione dell'errore.
	 */
	function advtr_test_posta( $url, $cookie, $nonce, array $campi ) {
		$risposta = null;

		for ( $tentativo = 0; $tentativo < 2; $tentativo++ ) {
			$risposta = wp_remote_post(
				$url,
				array(
					'redirection' => 0,
					'timeout'     => 25,
					'headers'     => array( 'Cookie' => $cookie ),
					'body'        => array_merge( array( '_wpnonce' => $nonce ), $campi ),
				)
			);
			if ( ! is_wp_error( $risposta ) ) {
				break;
			}
			echo "  … rete lenta, riprovo\n";
		}

		if ( is_wp_error( $risposta ) ) {
			return 'ERRORE: ' . $risposta->get_error_message();
		}

		$dove = wp_remote_retrieve_header( $risposta, 'location' );
		if ( ! $dove ) {
			return 'nessun redirect (http ' . wp_remote_retrieve_response_code( $risposta ) . ')';
		}

		$query = array();
		parse_str( (string) wp_parse_url( $dove, PHP_URL_QUERY ), $query );
		return isset( $query['avviso'] ) ? $query['avviso'] : '(nessun avviso)';
	}
}

if ( ! function_exists( 'advtr_test_apri' ) ) {
	/**
	 * Scarica una pagina come utente autenticato.
	 *
	 * @param string $url    Indirizzo.
	 * @param string $cookie Cookie di sessione.
	 * @return string Corpo della risposta, stringa vuota in caso di errore.
	 */
	function advtr_test_apri( $url, $cookie ) {
		$risposta = wp_remote_get(
			$url,
			array(
				'timeout' => 25,
				'headers' => array( 'Cookie' => $cookie ),
			)
		);
		return is_wp_error( $risposta ) ? '' : wp_remote_retrieve_body( $risposta );
	}
}

if ( ! function_exists( 'advtr_test_ripulisci' ) ) {
	/**
	 * Rimuove i contenuti e gli utenti creati dalle suite.
	 *
	 * Tutto ciò che le suite creano ha un titolo che inizia per PROVA o una
	 * email @example.test: se una suite muore a metà, la chiamata successiva
	 * fa pulizia al posto suo.
	 *
	 * @return int Elementi rimossi.
	 */
	function advtr_test_ripulisci() {
		$rimossi = 0;

		$posts = get_posts(
			array(
				'post_type'      => array( 'locale', 'poi', 'offerta', 'evento', 'punto_qr', 'page' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				's'              => 'PROVA',
			)
		);
		foreach ( $posts as $post ) {
			if ( 0 === strpos( $post->post_title, 'PROVA' ) ) {
				wp_delete_post( $post->ID, true );
				++$rimossi;
			}
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( get_users( array( 'search' => '*@example.test', 'search_columns' => array( 'user_email' ) ) ) as $utente ) {
			if ( ! user_can( $utente, 'manage_options' ) ) {
				wp_delete_user( $utente->ID );
				++$rimossi;
			}
		}

		return $rimossi;
	}
}
