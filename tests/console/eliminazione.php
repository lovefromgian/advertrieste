<?php
/**
 * Cestino, ripristino ed eliminazione definitiva.
 *
 * Da lanciare con: wp eval-file tests/console/eliminazione.php
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/helpers.php';

use AdverTrieste\Admin\Salva;
use AdverTrieste\Admin\AdminConsole;


// --- Preparazione -----------------------------------------------------------

$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
$admin = $admin ? $admin[0] : null;
if ( ! $admin ) {
	exit( "Nessun amministratore trovato.\n" );
}
wp_set_current_user( $admin->ID );

$cliente = get_users( array( 'role' => 'cliente_locale', 'number' => 1 ) );
$cliente = $cliente ? $cliente[0] : null;

function crea_prova( $tipo, $autore = 0 ) {
	return wp_insert_post(
		array(
			'post_type'   => $tipo,
			'post_title'  => 'PROVA cancellazione ' . $tipo,
			'post_status' => 'publish',
			'post_author' => $autore ? $autore : get_current_user_id(),
		)
	);
}

echo "\n== Ciclo cestina → ripristina ==\n";

foreach ( array( 'locale', 'poi', 'offerta', 'evento', 'punto_qr' ) as $tipo ) {
	$id = crea_prova( $tipo );

	$esito = Salva::cestina( $id );
	advtr_test_verifica( "$tipo: cestina restituisce 'cestinato'", 'cestinato' === $esito, $esito );
	advtr_test_verifica( "$tipo: lo stato è 'trash'", 'trash' === get_post_status( $id ), (string) get_post_status( $id ) );

	// Da cestinato non si ri-cestina.
	advtr_test_verifica( "$tipo: ricestinare è rifiutato", 'negato' === Salva::cestina( $id ) );

	$esito = Salva::ripristina( $id );
	advtr_test_verifica( "$tipo: ripristina restituisce 'ripristinato'", 'ripristinato' === $esito, $esito );
	advtr_test_verifica(
		"$tipo: torna in bozza, non online",
		'draft' === get_post_status( $id ),
		(string) get_post_status( $id )
	);

	wp_delete_post( $id, true );
}

echo "\n== L'eliminazione definitiva passa dal cestino ==\n";

$id = crea_prova( 'locale' );
advtr_test_verifica( 'una scheda pubblicata non si elimina per sempre', 'negato' === Salva::elimina( $id ) );
advtr_test_verifica( 'ed è ancora lì', (bool) get_post( $id ) );

Salva::cestina( $id );
advtr_test_verifica( 'dal cestino si elimina', 'eliminato' === Salva::elimina( $id ) );
advtr_test_verifica( 'ora non esiste più', null === get_post( $id ) );

echo "\n== Nulla di estraneo al plugin ==\n";

$pagina = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_title'  => 'PROVA pagina estranea',
		'post_status' => 'publish',
	)
);
advtr_test_verifica( 'una pagina WordPress non si cestina da qui', 'negato' === Salva::cestina( $pagina ) );
advtr_test_verifica( 'ed è intatta', 'publish' === get_post_status( $pagina ) );
wp_delete_post( $pagina, true );

advtr_test_verifica( 'un id inesistente è rifiutato', 'negato' === Salva::cestina( 999999 ) );
advtr_test_verifica( 'un id zero è rifiutato', 'negato' === Salva::elimina( 0 ) );

echo "\n== Chi non è amministratore non cancella ==\n";

$id = crea_prova( 'locale', $cliente ? $cliente->ID : 0 );
if ( $cliente ) {
	wp_set_current_user( $cliente->ID );
	advtr_test_verifica( 'il cliente non cestina la propria scheda dalla console', 'negato' === Salva::cestina( $id ) );
	advtr_test_verifica( 'la scheda è ancora online', 'publish' === get_post_status( $id ) );
	wp_set_current_user( $admin->ID );
}
wp_delete_post( $id, true );

echo "\n== Account: cosa si può eliminare ==\n";

advtr_test_verifica( 'un amministratore non è eliminabile', 'negato' === Salva::elimina_cliente( $admin->ID ) );
advtr_test_verifica( "l'amministratore non elimina se stesso", 'negato' === Salva::elimina_cliente( get_current_user_id() ) );
advtr_test_verifica( 'un utente inesistente è rifiutato', 'negato' === Salva::elimina_cliente( 999999 ) );

// Cliente usa e getta, con una scheda che deve sopravvivergli.
$uid = wp_insert_user(
	array(
		'user_login' => 'prova_cancellazione_' . wp_generate_password( 6, false ),
		'user_email' => 'prova' . wp_rand( 1000, 9999 ) . '@example.test',
		'user_pass'  => wp_generate_password( 20 ),
		'role'       => 'cliente_locale',
	)
);
$scheda  = crea_prova( 'locale', $uid );
$offerta = crea_prova( 'offerta', $uid );

$esito = Salva::elimina_cliente( $uid );
advtr_test_verifica( "l'account cliente si elimina", 'cliente_eliminato' === $esito, is_string( $esito ) ? $esito : '' );
advtr_test_verifica( "l'utente non esiste più", false === get_userdata( $uid ) );
advtr_test_verifica( 'la sua scheda esiste ancora', (bool) get_post( $scheda ) );
advtr_test_verifica(
	'ed è passata all\'amministratore, non orfana',
	(int) get_post_field( 'post_author', $scheda ) === (int) $admin->ID,
	'autore=' . get_post_field( 'post_author', $scheda )
);
advtr_test_verifica(
	'lo stesso vale per la sua offerta',
	(int) get_post_field( 'post_author', $offerta ) === (int) $admin->ID
);
wp_delete_post( $scheda, true );
wp_delete_post( $offerta, true );

echo "\n== Elenchi: il cestino è una vista separata ==\n";

$id = crea_prova( 'locale' );
$_GET = array();
$titoli = wp_list_pluck( AdminConsole::locali( '' ), 'post_title' );
advtr_test_verifica( 'la scheda viva compare nell\'elenco', in_array( 'PROVA cancellazione locale', $titoli, true ) );
advtr_test_verifica( 'fuori dal cestino gli stati sono quelli vivi', AdminConsole::stati_elenco( array( 'publish' ) ) === array( 'publish' ) );

Salva::cestina( $id );
$titoli = wp_list_pluck( AdminConsole::locali( '' ), 'post_title' );
advtr_test_verifica( 'una volta cestinata sparisce dall\'elenco', ! in_array( 'PROVA cancellazione locale', $titoli, true ) );

$_GET['cestino'] = '1';
advtr_test_verifica( 'con ?cestino=1 la vista è il cestino', AdminConsole::mostra_cestino() );
advtr_test_verifica( 'e gli stati diventano solo trash', AdminConsole::stati_elenco( array( 'publish' ) ) === array( 'trash' ) );
$titoli = wp_list_pluck( AdminConsole::locali( '' ), 'post_title' );
advtr_test_verifica( 'nel cestino la scheda si vede', in_array( 'PROVA cancellazione locale', $titoli, true ) );

$_GET['cestino'] = '0';
advtr_test_verifica( '?cestino=0 non apre il cestino', ! AdminConsole::mostra_cestino() );
$_GET = array();

wp_delete_post( $id, true );

echo "\n== I bottoni giusti al momento giusto ==\n";

$id = crea_prova( 'poi' );
$vivo = AdminConsole::azioni_cestino( 'poi', $id, false );
advtr_test_verifica( 'su un elemento vivo compare solo "Cestina"', false !== strpos( $vivo, 'value="cestina"' ) && false === strpos( $vivo, 'value="elimina"' ) );
advtr_test_verifica( 'e chiede conferma', false !== strpos( $vivo, 'data-advtr-conferma' ) );
advtr_test_verifica( 'via POST, non via link', false !== strpos( $vivo, '<form method="post"' ) );
advtr_test_verifica( 'con nonce', false !== strpos( $vivo, '_wpnonce' ) );

$cest = AdminConsole::azioni_cestino( 'poi', $id, true );
advtr_test_verifica( 'nel cestino compaiono ripristina ed elimina', false !== strpos( $cest, 'value="ripristina"' ) && false !== strpos( $cest, 'value="elimina"' ) );
advtr_test_verifica( 'e non "cestina"', false === strpos( $cest, 'value="cestina"' ) );

Salva::cestina( $id );
$zona = AdminConsole::zona_pericolosa( 'poi', $id );
advtr_test_verifica( 'la zona pericolosa legge lo stato reale del post', false !== strpos( $zona, 'value="elimina"' ) );
advtr_test_verifica( 'e spiega cosa succede', false !== strpos( $zona, 'ac-zona-fragile' ) );
advtr_test_verifica( 'su un id inesistente non stampa nulla', '' === AdminConsole::zona_pericolosa( 'poi', 999999 ) );
advtr_test_verifica( "la zona account non compare per l'amministratore", '' === AdminConsole::zona_pericolosa_cliente( $admin->ID ) );
wp_delete_post( $id, true );

advtr_test_riepilogo();
