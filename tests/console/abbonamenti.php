<?php
/**
 * Attivazione e rinnovo degli abbonamenti.
 *
 * Da lanciare con: wp eval-file tests/console/abbonamenti.php
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/helpers.php';

use AdverTrieste\Cliente\Abbonamento;

$base = home_url( '/' );

$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_current_user( $admin->ID );

// Cookie di sessione: nessuna password, si firma il token direttamente.
$cookie = advtr_test_sessione( $admin->ID );
$nonce                       = wp_create_nonce( \AdverTrieste\Admin\AdminConsole::NONCE );
$url                         = \AdverTrieste\Admin\AdminConsole::url( 'abbonamenti' );

/**
 * Invia l'azione e restituisce il codice di avviso dal redirect.
 */
$scheda_prova = function ( $stato = 'publish' ) {
	return wp_insert_post(
		array(
			'post_type'   => 'locale',
			'post_title'  => 'PROVA abbonamento',
			'post_status' => $stato,
			'post_author' => get_current_user_id(),
		)
	);
};

echo "\n== Prima attivazione ==\n";

$id = $scheda_prova();
advtr_test_verifica( 'in partenza non ha scadenza', null === Abbonamento::giorni_alla_scadenza( $id ) );

$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'     => 'attiva_abbonamento',
		'advtr_locale'     => $id,
		'advtr_decorrenza' => current_time( 'Y-m-d' ),
		'advtr_durata'     => 365,
	)
);
advtr_test_verifica( 'attivazione accettata', 'abbonamento_attivato' === $esito, $esito );

clean_post_cache( $id );
$inizio = get_post_meta( $id, 'advtr_data_inizio', true );
$fine   = get_post_meta( $id, 'advtr_data_fine', true );
advtr_test_verifica( 'la decorrenza è oggi', current_time( 'Y-m-d' ) === $inizio, (string) $inizio );
advtr_test_verifica(
	'la scadenza è a 365 giorni',
	gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' 00:00:00' ) + 365 * DAY_IN_SECONDS ) === $fine,
	(string) $fine
);
advtr_test_verifica( 'lo stato letto è "attivo"', 'attivo' === Abbonamento::stato( $id )['codice'] );
advtr_test_verifica( 'i giorni residui sono 365', 365 === Abbonamento::giorni_alla_scadenza( $id ), (string) Abbonamento::giorni_alla_scadenza( $id ) );

echo "\n== Le durate ammesse sono solo quelle offerte ==\n";

foreach ( array( 30, 90, 180 ) as $gg ) {
	$esito = advtr_test_posta( $url, $cookie, $nonce, array(
			'advtr_azione'     => 'attiva_abbonamento',
			'advtr_locale'     => $id,
			'advtr_decorrenza' => current_time( 'Y-m-d' ),
			'advtr_durata'     => $gg,
		)
	);
	clean_post_cache( $id );
	advtr_test_verifica( "durata $gg giorni accettata", 'abbonamento_attivato' === $esito, $esito );
	advtr_test_verifica( "e produce $gg giorni residui", $gg === Abbonamento::giorni_alla_scadenza( $id ), (string) Abbonamento::giorni_alla_scadenza( $id ) );
}

$prima = get_post_meta( $id, 'advtr_data_fine', true );
foreach ( array( 7, 9999, 0, -30 ) as $gg ) {
	$esito = advtr_test_posta( $url, $cookie, $nonce, array(
			'advtr_azione'     => 'attiva_abbonamento',
			'advtr_locale'     => $id,
			'advtr_decorrenza' => current_time( 'Y-m-d' ),
			'advtr_durata'     => $gg,
		)
	);
	advtr_test_verifica( "durata $gg rifiutata", 'negato' === $esito, $esito );
}
clean_post_cache( $id );
advtr_test_verifica( 'e la scadenza non è cambiata', $prima === get_post_meta( $id, 'advtr_data_fine', true ) );

echo "\n== Date e bersagli storti ==\n";

$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'     => 'attiva_abbonamento',
		'advtr_locale'     => $id,
		'advtr_decorrenza' => 'trentadue marzo',
		'advtr_durata'     => 365,
	)
);
advtr_test_verifica( 'una data non interpretabile è rifiutata', 'data_non_valida' === $esito, $esito );

$pagina = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'PROVA pagina abbonamento', 'post_status' => 'publish' ) );
$esito  = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'     => 'attiva_abbonamento',
		'advtr_locale'     => $pagina,
		'advtr_decorrenza' => current_time( 'Y-m-d' ),
		'advtr_durata'     => 365,
	)
);
advtr_test_verifica( 'non si attiva un abbonamento su una pagina', 'negato' === $esito, $esito );
advtr_test_verifica( 'e la pagina resta senza date', '' === (string) get_post_meta( $pagina, 'advtr_data_fine', true ) );
wp_delete_post( $pagina, true );

$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'     => 'attiva_abbonamento',
		'advtr_locale'     => 999999,
		'advtr_decorrenza' => current_time( 'Y-m-d' ),
		'advtr_durata'     => 365,
	)
);
advtr_test_verifica( 'un id inesistente è rifiutato', 'negato' === $esito, $esito );

echo "\n== Decorrenza futura ==\n";

$domani = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) ) + 30 * DAY_IN_SECONDS );
advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'     => 'attiva_abbonamento',
		'advtr_locale'     => $id,
		'advtr_decorrenza' => $domani,
		'advtr_durata'     => 30,
	)
);
clean_post_cache( $id );
advtr_test_verifica( 'la decorrenza futura viene registrata', $domani === get_post_meta( $id, 'advtr_data_inizio', true ) );
advtr_test_verifica( 'e la scadenza parte da lì, non da oggi', 60 === Abbonamento::giorni_alla_scadenza( $id ), (string) Abbonamento::giorni_alla_scadenza( $id ) );

wp_delete_post( $id, true );

echo "\n== Una scheda mai pubblicata non va online da sola ==\n";

$id = $scheda_prova( 'draft' );
$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'     => 'attiva_abbonamento',
		'advtr_locale'     => $id,
		'advtr_decorrenza' => current_time( 'Y-m-d' ),
		'advtr_durata'     => 365,
	)
);
clean_post_cache( $id );
advtr_test_verifica( "l'esito dice che non è stata pubblicata", 'abbonamento_attivato' === $esito, $esito );
advtr_test_verifica( 'ed è ancora in bozza', 'draft' === get_post_status( $id ), (string) get_post_status( $id ) );
advtr_test_verifica( 'ma la validità è stata scritta', '' !== (string) get_post_meta( $id, 'advtr_data_fine', true ) );
wp_delete_post( $id, true );

echo "\n== Una scheda sospesa alla scadenza torna online ==\n";

$id = $scheda_prova( 'draft' );
update_post_meta( $id, 'advtr_sospesa', 1 );
update_post_meta( $id, 'advtr_scadenza_avvisi', array( 30, 15, 7 ) );

$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'     => 'attiva_abbonamento',
		'advtr_locale'     => $id,
		'advtr_decorrenza' => current_time( 'Y-m-d' ),
		'advtr_durata'     => 365,
	)
);
clean_post_cache( $id );
advtr_test_verifica( "l'esito distingue il caso", 'abbonamento_ripreso' === $esito, $esito );
advtr_test_verifica( 'la scheda è tornata online', 'publish' === get_post_status( $id ), (string) get_post_status( $id ) );
advtr_test_verifica( 'il flag di sospensione è sparito', '' === (string) get_post_meta( $id, 'advtr_sospesa', true ) );
advtr_test_verifica( 'gli avvisi già inviati sono azzerati', '' === (string) get_post_meta( $id, 'advtr_scadenza_avvisi', true ) );
wp_delete_post( $id, true );

echo "\n== Senza nonce valido non passa nulla ==\n";

$id = $scheda_prova();
$r  = wp_remote_post(
	$url,
	array(
		'redirection' => 0,
		'timeout'     => 20,
		'headers'     => array( 'Cookie' => $cookie ),
		'body'        => array(
			'_wpnonce'         => 'nonce-inventato',
			'advtr_azione'     => 'attiva_abbonamento',
			'advtr_locale'     => $id,
			'advtr_decorrenza' => current_time( 'Y-m-d' ),
			'advtr_durata'     => 365,
		),
	)
);
$codice = is_wp_error( $r ) ? 0 : wp_remote_retrieve_response_code( $r );
advtr_test_verifica( 'un nonce falso viene respinto', 403 === (int) $codice, (string) $codice );
clean_post_cache( $id );
advtr_test_verifica( 'e la scheda resta senza abbonamento', '' === (string) get_post_meta( $id, 'advtr_data_fine', true ) );

// Senza cookie: non è amministratore, quindi non deve toccare nulla.
$r = wp_remote_post(
	$url,
	array(
		'redirection' => 0,
		'timeout'     => 20,
		'body'        => array(
			'_wpnonce'         => $nonce,
			'advtr_azione'     => 'attiva_abbonamento',
			'advtr_locale'     => $id,
			'advtr_decorrenza' => current_time( 'Y-m-d' ),
			'advtr_durata'     => 365,
		),
	)
);
clean_post_cache( $id );
advtr_test_verifica( 'senza sessione la scheda resta intatta', '' === (string) get_post_meta( $id, 'advtr_data_fine', true ) );
wp_delete_post( $id, true );

advtr_test_riepilogo();
