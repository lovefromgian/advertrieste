<?php
/**
 * Scritture della console amministratore per gli oggetti diversi dal locale.
 *
 * Punti d'interesse, offerte, eventi, punti QR e account clienti. Il locale ha
 * una classe sua (`Admin\Scheda`) perché deve comporsi con il salvataggio del
 * cliente; qui invece si tratta di oggetti che il cliente non modifica, o che
 * modifica per altra via.
 *
 * Ogni metodo verifica `manage_options` per conto proprio: il controllo del
 * controller resta, ma questi metodi sono pubblici e non devono dipendere da
 * chi li chiama.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Admin;

use AdverTrieste\Cpt\Poi;
use AdverTrieste\Cpt\Offerta;
use AdverTrieste\Cpt\Evento;
use AdverTrieste\Cpt\PuntoQr;
use AdverTrieste\Cpt\Categoria;
use AdverTrieste\Access\Roles;
use AdverTrieste\Evento\Workflow;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Salvataggi della console amministratore.
 */
class Salva {

	/**
	 * Legge un campo POST come testo sanitizzato.
	 *
	 * @param string $nome Nome del campo.
	 * @return string
	 */
	private static function testo( $nome ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verificato dal controller.
		return isset( $_POST[ $nome ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nome ] ) ) : '';
	}

	/**
	 * Scrive o cancella un meta a seconda che il valore sia vuoto.
	 *
	 * @param int    $post_id ID.
	 * @param string $chiave  Meta.
	 * @param string $valore  Valore.
	 * @return void
	 */
	private static function meta( $post_id, $chiave, $valore ) {
		if ( '' === $valore ) {
			delete_post_meta( $post_id, $chiave );
			return;
		}
		update_post_meta( $post_id, $chiave, $valore );
	}

	/**
	 * Aggiorna titolo, contenuto e stato di un post.
	 *
	 * @param int    $post_id ID.
	 * @param string $tipo    Post type atteso.
	 * @return \WP_Post|null Il post, se valido.
	 */
	private static function base( $post_id, $tipo ) {
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || $tipo !== $post->post_type ) {
			return null;
		}

		$titolo = self::testo( 'advtr_titolo' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verificato dal controller.
		$testo = isset( $_POST['advtr_descrizione'] ) ? wp_kses_post( wp_unslash( $_POST['advtr_descrizione'] ) ) : '';
		$stato = self::testo( 'advtr_stato_post' );

		$args = array( 'ID' => $post_id );
		if ( '' !== $titolo ) {
			$args['post_title'] = $titolo;
		}
		$args['post_content'] = $testo;
		if ( in_array( $stato, array( 'publish', 'draft' ), true ) ) {
			$args['post_status'] = $stato;
		}
		wp_update_post( $args );

		return $post;
	}

	/**
	 * Salva un punto d'interesse.
	 *
	 * @return string Codice di esito.
	 */
	public static function poi() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return 'negato';
		}
		$id = absint( self::testo( 'advtr_id' ) );
		if ( ! self::base( $id, Poi::POST_TYPE ) ) {
			return 'negato';
		}

		self::meta( $id, 'advtr_tipo', self::testo( 'advtr_tipo' ) );
		self::coordinate( $id );

		$zoom = self::testo( 'advtr_zoom_min' );
		self::meta( $id, 'advtr_zoom_min', '' === $zoom ? '' : (string) max( 0, min( 22, (int) $zoom ) ) );

		self::categorie( $id );
		return 'salvato';
	}

	/**
	 * Salva un punto QR.
	 *
	 * @return string
	 */
	public static function qr() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return 'negato';
		}
		$id = absint( self::testo( 'advtr_id' ) );
		if ( ! self::base( $id, PuntoQr::POST_TYPE ) ) {
			return 'negato';
		}

		self::meta( $id, 'advtr_indirizzo', self::testo( 'advtr_indirizzo' ) );
		self::coordinate( $id );

		$stato = self::testo( 'advtr_stato' );
		self::meta( $id, 'advtr_stato', in_array( $stato, array( 'attivo', 'inattivo' ), true ) ? $stato : '' );

		return 'salvato';
	}

	/**
	 * Salva un'offerta.
	 *
	 * @return string
	 */
	public static function offerta() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return 'negato';
		}
		$id = absint( self::testo( 'advtr_id' ) );
		if ( ! self::base( $id, Offerta::POST_TYPE ) ) {
			return 'negato';
		}

		$locale = absint( self::testo( 'advtr_locale_id' ) );
		if ( $locale && 'locale' === get_post_type( $locale ) ) {
			update_post_meta( $id, 'advtr_locale_id', $locale );
		}

		$inizio   = self::testo( 'advtr_data_inizio' );
		$scadenza = self::testo( 'advtr_data_scadenza' );
		$ts_i     = $inizio ? strtotime( $inizio ) : 0;
		$ts_s     = $scadenza ? strtotime( $scadenza ) : 0;
		if ( $ts_i && $ts_s && $ts_s <= $ts_i ) {
			return 'date_ko';
		}

		self::meta( $id, 'advtr_data_inizio', $ts_i ? gmdate( 'Y-m-d H:i:s', $ts_i ) : '' );
		self::meta( $id, 'advtr_data_scadenza', $ts_s ? gmdate( 'Y-m-d H:i:s', $ts_s ) : '' );

		$tipo = self::testo( 'advtr_tipo_coupon' );
		update_post_meta( $id, 'advtr_tipo_coupon', 'qr' === $tipo ? 'qr' : 'codice' );
		update_post_meta( $id, 'advtr_codice', self::testo( 'advtr_codice' ) );

		// Modificare un'offerta la riapre: lo stato "scaduto" lo rimette il cron.
		delete_post_meta( $id, 'advtr_stato' );

		return 'salvato';
	}

	/**
	 * Salva un evento.
	 *
	 * @return string
	 */
	public static function evento() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return 'negato';
		}
		$id = absint( self::testo( 'advtr_id' ) );
		if ( ! self::base( $id, Evento::POST_TYPE ) ) {
			return 'negato';
		}

		$tipo = self::testo( 'advtr_tipo_evento' );
		update_post_meta( $id, 'advtr_tipo_evento', 'grande' === $tipo ? 'grande' : 'organizzatore' );

		self::meta( $id, 'advtr_data_inizio', self::testo( 'advtr_data_inizio' ) );
		self::meta( $id, 'advtr_data_fine', self::testo( 'advtr_data_fine' ) );

		// Un solo commento per entrambi i sniff: due `phpcs:ignore` sulla stessa
		// riga si annullano a vicenda.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verificato dal controller; sanitizzato con array_map subito sotto.
		$grezzi    = isset( $_POST['advtr_locali_collegati'] ) ? (array) wp_unslash( $_POST['advtr_locali_collegati'] ) : array();
		$collegati = array_map( 'absint', $grezzi );
		$validi    = array();
		foreach ( $collegati as $lid ) {
			if ( $lid && 'locale' === get_post_type( $lid ) ) {
				$validi[] = $lid;
			}
		}
		update_post_meta( $id, 'advtr_locali_collegati', $validi );

		// Salvare NON pubblica: la modifica torna in bozza e il pubblico continua
		// a vedere l'ultima versione approvata, com'è per gli organizzatori.
		Workflow::mark_dirty( $id );

		return 'salvato';
	}

	/**
	 * Salva un account cliente.
	 *
	 * @return string
	 */
	public static function cliente() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return 'negato';
		}
		$id   = absint( self::testo( 'advtr_id' ) );
		$user = $id ? get_userdata( $id ) : null;
		if ( ! $user ) {
			return 'negato';
		}

		// Da qui non si modificano altri amministratori: la gestione degli account
		// con pieni poteri resta dove ci sono tutte le tutele del core.
		if ( user_can( $user, 'manage_options' ) ) {
			return 'negato';
		}

		$nome  = self::testo( 'advtr_nome' );
		$email = sanitize_email( self::testo( 'advtr_email' ) );
		$ruolo = self::testo( 'advtr_ruolo' );

		if ( '' === $nome || ! is_email( $email ) ) {
			return 'account_ko';
		}
		$occupante = get_user_by( 'email', $email );
		if ( $occupante && (int) $occupante->ID !== $id ) {
			return 'account_email';
		}

		$args = array(
			'ID'           => $id,
			'display_name' => $nome,
			'user_email'   => $email,
		);
		if ( in_array( $ruolo, array( Roles::CLIENTE, Roles::ORGANIZZATORE ), true ) ) {
			$args['role'] = $ruolo;
		}

		$esito = wp_update_user( $args );
		return is_wp_error( $esito ) ? 'account_ko' : 'salvato';
	}

	/**
	 * Tipi creabili dalla console: sezione => post type.
	 *
	 * @var array<string,string>
	 */
	const CREABILI = array(
		'locali'  => 'locale',
		'poi'     => 'poi',
		'offerte' => 'offerta',
		'eventi'  => 'evento',
		'qr'      => 'punto_qr',
	);

	/**
	 * Titolo provvisorio di un nuovo elemento.
	 *
	 * Non si crea senza titolo: un elenco pieno di righe vuote è illeggibile, e
	 * un titolo provvisorio dice subito che il lavoro è da finire.
	 *
	 * @param string $sezione Sezione.
	 * @return string
	 */
	private static function titolo_nuovo( $sezione ) {
		$titoli = array(
			'locali'  => __( 'Nuova attività (da completare)', 'advertrieste' ),
			'poi'     => __( 'Nuovo punto d\'interesse (da completare)', 'advertrieste' ),
			'offerte' => __( 'Nuova offerta (da completare)', 'advertrieste' ),
			'eventi'  => __( 'Nuovo evento (da completare)', 'advertrieste' ),
			'qr'      => __( 'Nuovo espositore (da completare)', 'advertrieste' ),
		);
		return $titoli[ $sezione ] ?? __( 'Nuovo elemento', 'advertrieste' );
	}

	/**
	 * Crea un elemento in bozza e ne restituisce l'ID.
	 *
	 * Nasce sempre NON pubblicato: un contenuto vuoto non deve comparire sulla
	 * mappa nell'istante in cui si preme "Aggiungi".
	 *
	 * @param string $sezione Sezione di provenienza.
	 * @return int ID creato, 0 se non consentito.
	 */
	public static function crea( $sezione ) {
		if ( ! current_user_can( 'manage_options' ) || ! isset( self::CREABILI[ $sezione ] ) ) {
			return 0;
		}

		$id = wp_insert_post(
			array(
				'post_type'   => self::CREABILI[ $sezione ],
				'post_status' => 'draft',
				'post_title'  => self::titolo_nuovo( $sezione ),
				'post_author' => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return 0;
		}

		// Valori di partenza sensati, così la scheda non nasce già sbagliata.
		if ( 'locali' === $sezione ) {
			update_post_meta( $id, 'advtr_zoom_min', 14 );
			update_post_meta( $id, 'advtr_data_inizio', current_time( 'Y-m-d' ) );
			update_post_meta( $id, 'advtr_data_fine', wp_date( 'Y-m-d', time() + YEAR_IN_SECONDS ) );
		} elseif ( 'poi' === $sezione ) {
			update_post_meta( $id, 'advtr_zoom_min', 0 );
		} elseif ( 'qr' === $sezione ) {
			update_post_meta( $id, 'advtr_stato', 'attivo' );
		} elseif ( 'eventi' === $sezione ) {
			update_post_meta( $id, 'advtr_tipo_evento', 'grande' );
		}

		return (int) $id;
	}

	/**
	 * Crea un account cliente.
	 *
	 * La password NON viene scelta qui e non viene mostrata: l'account nasce con
	 * una password casuale e al cliente parte l'email con il link per impostarne
	 * una propria. È il flusso del core, ed evita che una password transiti da un
	 * modulo o finisca scritta in una schermata.
	 *
	 * @return array{esito:string,id:int}
	 */
	public static function crea_cliente() {
		if ( ! current_user_can( 'create_users' ) ) {
			return array(
				'esito' => 'negato',
				'id'    => 0,
			);
		}

		$nome  = self::testo( 'advtr_nome' );
		$email = sanitize_email( self::testo( 'advtr_email' ) );
		$ruolo = self::testo( 'advtr_ruolo' );
		$ruolo = in_array( $ruolo, array( Roles::CLIENTE, Roles::ORGANIZZATORE ), true ) ? $ruolo : Roles::CLIENTE;

		if ( '' === $nome || ! is_email( $email ) ) {
			return array(
				'esito' => 'account_ko',
				'id'    => 0,
			);
		}
		if ( email_exists( $email ) ) {
			return array(
				'esito' => 'account_email',
				'id'    => 0,
			);
		}

		// Nome utente derivato dall'email, reso unico se già preso.
		$base  = sanitize_user( current( explode( '@', $email ) ), true );
		$login = '' !== $base ? $base : 'cliente';
		$n     = 1;
		while ( username_exists( $login ) ) {
			++$n;
			$login = $base . $n;
		}

		$id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'display_name' => $nome,
				'role'         => $ruolo,
			)
		);

		if ( is_wp_error( $id ) ) {
			return array(
				'esito' => 'account_ko',
				'id'    => 0,
			);
		}

		// Email al nuovo utente con il link per impostare la password.
		wp_new_user_notification( $id, null, 'user' );

		return array(
			'esito' => 'creato_cliente',
			'id'    => (int) $id,
		);
	}

	/**
	 * Salva le coordinate, se numeriche.
	 *
	 * @param int $post_id ID.
	 * @return void
	 */
	private static function coordinate( $post_id ) {
		foreach ( array( 'advtr_lat', 'advtr_lng' ) as $campo ) {
			$valore = self::testo( $campo );
			if ( '' === $valore ) {
				delete_post_meta( $post_id, $campo );
				continue;
			}
			if ( is_numeric( $valore ) ) {
				update_post_meta( $post_id, $campo, (float) $valore );
			}
		}
	}

	/**
	 * Assegna le categorie, scartando gli slug inesistenti.
	 *
	 * @param int $post_id ID.
	 * @return void
	 */
	private static function categorie( $post_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verificato dal controller.
		if ( ! isset( $_POST['advtr_categorie'] ) || ! is_array( $_POST['advtr_categorie'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizzato con array_map.
		$slugs  = array_map( 'sanitize_key', wp_unslash( $_POST['advtr_categorie'] ) );
		$validi = array();
		foreach ( $slugs as $slug ) {
			if ( term_exists( $slug, Categoria::TAXONOMY ) ) {
				$validi[] = $slug;
			}
		}
		wp_set_object_terms( $post_id, $validi, Categoria::TAXONOMY );
	}
}
