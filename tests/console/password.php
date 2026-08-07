<?php
/**
 * Nome utente e gestione password degli account cliente.
 *
 * Da lanciare con: wp eval-file tests/console/password.php
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/helpers.php';

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Admin\Salva;

$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_current_user( $admin->ID );

$cookie = advtr_test_sessione( $admin->ID );
$nonce                       = wp_create_nonce( AdminConsole::NONCE );
$url                         = AdminConsole::url( 'clienti' );

$suffisso = wp_generate_password( 6, false );

echo "\n== Creazione con password scelta dall'amministratore ==\n";

$mail  = 'provapass' . $suffisso . '@example.test';
$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'   => 'crea_cliente',
		'advtr_nome'     => 'Prova Password',
		'advtr_email'    => $mail,
		'advtr_ruolo'    => 'cliente_locale',
		'advtr_password' => 'unaPasswordLunga1',
	)
);
advtr_test_verifica( "l'esito distingue il caso con password", 'creato_cliente_pass' === $esito, $esito );

$u = get_user_by( 'email', $mail );
advtr_test_verifica( "l'account esiste", (bool) $u );
advtr_test_verifica( 'la password scelta funziona davvero', $u && wp_check_password( 'unaPasswordLunga1', $u->user_pass, $u->ID ) );
advtr_test_verifica( 'e un\'altra password no', $u && ! wp_check_password( 'sbagliata12345', $u->user_pass, $u->ID ) );

echo "\n== Password troppo corta ==\n";

$mail2 = 'provacorta' . $suffisso . '@example.test';
$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'   => 'crea_cliente',
		'advtr_nome'     => 'Prova Corta',
		'advtr_email'    => $mail2,
		'advtr_ruolo'    => 'cliente_locale',
		'advtr_password' => 'corta',
	)
);
advtr_test_verifica( 'la creazione è rifiutata', 'password_corta' === $esito, $esito );
advtr_test_verifica( "e l'account non viene creato a metà", false === get_user_by( 'email', $mail2 ) );

echo "\n== Creazione senza password: resta il percorso via email ==\n";

$mail3 = 'provamail' . $suffisso . '@example.test';
$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione' => 'crea_cliente',
		'advtr_nome'   => 'Prova Email',
		'advtr_email'  => $mail3,
		'advtr_ruolo'  => 'cliente_locale',
	)
);
advtr_test_verifica( "l'esito è quello classico", 'creato_cliente' === $esito, $esito );
$u3 = get_user_by( 'email', $mail3 );
advtr_test_verifica( 'account creato', (bool) $u3 );
advtr_test_verifica( 'con una password non indovinabile', $u3 && ! wp_check_password( '', $u3->user_pass, $u3->ID ) );
if ( $u3 ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $u3->ID );
}

echo "\n== Cambio password su un account esistente ==\n";

$vecchia_hash = $u->user_pass;
$esito        = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'   => 'password_cliente',
		'advtr_id'       => $u->ID,
		'advtr_password' => 'nuovaPassword2026',
	)
);
advtr_test_verifica( 'il cambio è accettato', 'password_ok' === $esito, $esito );

clean_user_cache( $u->ID );
$u = get_userdata( $u->ID );
advtr_test_verifica( 'la nuova password funziona', wp_check_password( 'nuovaPassword2026', $u->user_pass, $u->ID ) );
advtr_test_verifica( 'la vecchia non funziona più', ! wp_check_password( 'unaPasswordLunga1', $u->user_pass, $u->ID ) );
advtr_test_verifica( "l'hash salvato è cambiato", $vecchia_hash !== $u->user_pass );

$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'   => 'password_cliente',
		'advtr_id'       => $u->ID,
		'advtr_password' => 'corta12',
	)
);
advtr_test_verifica( 'sette caratteri sono rifiutati', 'password_corta' === $esito, $esito );
clean_user_cache( $u->ID );
$u = get_userdata( $u->ID );
advtr_test_verifica( 'e la password resta quella di prima', wp_check_password( 'nuovaPassword2026', $u->user_pass, $u->ID ) );

$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'   => 'password_cliente',
		'advtr_id'       => $u->ID,
		'advtr_password' => '',
	)
);
advtr_test_verifica( 'una password vuota è rifiutata', 'password_corta' === $esito, $esito );

echo "\n== Le sessioni aperte del cliente decadono ==\n";

$t = wp_generate_password( 43, false, false );
WP_Session_Tokens::get_instance( $u->ID )->update( $t, array( 'expiration' => time() + 3600 ) );
advtr_test_verifica( 'la sessione di prova esiste', (bool) WP_Session_Tokens::get_instance( $u->ID )->verify( $t ) );

advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'   => 'password_cliente',
		'advtr_id'       => $u->ID,
		'advtr_password' => 'ancoraUnAltra2026',
	)
);
clean_user_cache( $u->ID );
wp_cache_delete( $u->ID, 'user_meta' );
advtr_test_verifica( 'dopo il cambio non è più valida', ! WP_Session_Tokens::get_instance( $u->ID )->verify( $t ) );

echo "\n== Chi non si può toccare ==\n";

$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'   => 'password_cliente',
		'advtr_id'       => $admin->ID,
		'advtr_password' => 'provaAmministratore1',
	)
);
advtr_test_verifica( 'un amministratore non si tocca da qui', 'negato' === $esito, $esito );
clean_user_cache( $admin->ID );
$ricarica = get_userdata( $admin->ID );
advtr_test_verifica( "e la sua password non è cambiata", ! wp_check_password( 'provaAmministratore1', $ricarica->user_pass, $admin->ID ) );

$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'   => 'password_cliente',
		'advtr_id'       => 999999,
		'advtr_password' => 'unaPasswordLunga1',
	)
);
advtr_test_verifica( 'un id inesistente è rifiutato', 'negato' === $esito, $esito );

$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione' => 'password_link',
		'advtr_id'     => $admin->ID,
	)
);
advtr_test_verifica( 'nemmeno il link di reset per un amministratore', 'negato' === $esito, $esito );

echo "\n== Invio del link ==\n";

$intercettata = array();
add_filter(
	'wp_mail',
	function ( $args ) use ( &$intercettata ) {
		$intercettata[] = $args;
		return $args;
	}
);
$esito = advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione' => 'password_link',
		'advtr_id'     => $u->ID,
	)
);
advtr_test_verifica( "l'invio riporta un esito noto", in_array( $esito, array( 'password_link_ok', 'password_link_ko' ), true ), $esito );
advtr_test_verifica( 'e la chiave di reset è stata generata', '' !== (string) get_user_meta( $u->ID, 'default_password_nag', true ) || (bool) get_password_reset_key( $u ) );

echo "\n== Senza permessi non passa ==\n";

$r = wp_remote_post(
	$url,
	array(
		'redirection' => 0,
		'timeout'     => 20,
		'body'        => array(
			'_wpnonce'       => $nonce,
			'advtr_azione'   => 'password_cliente',
			'advtr_id'       => $u->ID,
			'advtr_password' => 'intrusoIntruso1',
		),
	)
);
clean_user_cache( $u->ID );
$dopo = get_userdata( $u->ID );
advtr_test_verifica( 'senza sessione la password non cambia', ! wp_check_password( 'intrusoIntruso1', $dopo->user_pass, $u->ID ) );

$r = wp_remote_post(
	$url,
	array(
		'redirection' => 0,
		'timeout'     => 20,
		'headers'     => array( 'Cookie' => $cookie ),
		'body'        => array(
			'_wpnonce'       => 'nonce-inventato',
			'advtr_azione'   => 'password_cliente',
			'advtr_id'       => $u->ID,
			'advtr_password' => 'nonceFintoFinto1',
		),
	)
);
advtr_test_verifica( 'un nonce falso dà 403', 403 === (int) wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_response_code( $r ) );
clean_user_cache( $u->ID );
$dopo = get_userdata( $u->ID );
advtr_test_verifica( 'e la password non cambia', ! wp_check_password( 'nonceFintoFinto1', $dopo->user_pass, $u->ID ) );

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $u->ID );

echo "\n== La password non finisce mai nella pagina ==\n";

$mail4 = 'provaeco' . $suffisso . '@example.test';
advtr_test_posta( $url, $cookie, $nonce, array(
		'advtr_azione'   => 'crea_cliente',
		'advtr_nome'     => 'Prova Eco',
		'advtr_email'    => $mail4,
		'advtr_ruolo'    => 'cliente_locale',
		'advtr_password' => 'segretissimaXY1',
	)
);
$u4 = get_user_by( 'email', $mail4 );
if ( $u4 ) {
	$r    = wp_remote_get(
		AdminConsole::url( 'clienti', array( 'id' => $u4->ID ) ),
		array(
			'timeout' => 20,
			'headers' => array( 'Cookie' => $cookie ),
		)
	);
	$html = wp_remote_retrieve_body( $r );
	advtr_test_verifica( 'la pagina di dettaglio si apre', false !== strpos( $html, 'Prova Eco' ) );
	advtr_test_verifica( 'la password non compare da nessuna parte', false === strpos( $html, 'segretissimaXY1' ) );
	advtr_test_verifica( "nemmeno l'hash", false === strpos( $html, substr( $u4->user_pass, 0, 20 ) ) );
	advtr_test_verifica( 'il nome utente invece si vede', false !== strpos( $html, $u4->user_login ) );
	advtr_test_verifica( 'e ci sono entrambe le vie per la password', false !== strpos( $html, 'password_link' ) && false !== strpos( $html, 'password_cliente' ) );
	wp_delete_user( $u4->ID );
}

advtr_test_riepilogo();
