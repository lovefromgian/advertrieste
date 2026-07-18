<?php
/**
 * Scadenze schede & email automatiche (§3.1).
 *
 * Handler del cron `advtr_check_scadenze`: per ogni `locale` con data di fine
 * validità invia avvisi email (a soglie 30/15/7 gg, una sola volta per soglia) a
 * admin e cliente, sospende le schede scadute (le mette in bozza) e spegne il
 * badge "in evidenza" scaduto.
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Scadenze;

use AdverTrieste\Cpt\Locale;
use AdverTrieste\Email\Mailer;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calcolo scadenze, avvisi e sospensione automatica.
 */
class Scadenze {

	/**
	 * Soglie di avviso di default, in giorni.
	 *
	 * @var int[]
	 */
	const SOGLIE = array( 30, 15, 7 );

	/**
	 * Restituisce le soglie di avviso (filtrabili).
	 *
	 * @return int[] Soglie in giorni, ordinate crescenti.
	 */
	public static function soglie() {
		$soglie = apply_filters( 'advtr_scadenza_soglie', self::SOGLIE );
		$soglie = array_map( 'absint', (array) $soglie );
		$soglie = array_values( array_unique( array_filter( $soglie ) ) );
		sort( $soglie );
		return $soglie;
	}

	/**
	 * Handler del cron: elabora tutte le schede.
	 *
	 * @return array<string,int> Riepilogo: avvisi, sospese, evidenza_spente.
	 */
	public static function check() {
		$summary = array(
			'avvisi'          => 0,
			'sospese'         => 0,
			'evidenza_spente' => 0,
		);

		// Ora locale come epoch, coerente con strtotime() sulle date salvate.
		$now = strtotime( current_time( 'mysql' ) );

		$ids = get_posts(
			array(
				'post_type'      => Locale::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- batch cron.
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'advtr_data_fine',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $ids as $id ) {
			// Spegni evidenza scaduta (indipendente dalla scadenza scheda).
			if ( self::spegni_evidenza_scaduta( $id, $now ) ) {
				++$summary['evidenza_spente'];
			}

			$fine = get_post_meta( $id, 'advtr_data_fine', true );
			if ( '' === $fine ) {
				continue;
			}
			$end       = strtotime( $fine . ' 23:59:59' );
			$days_left = (int) ceil( ( $end - $now ) / DAY_IN_SECONDS );

			if ( $days_left <= 0 ) {
				if ( self::sospendi( $id ) ) {
					++$summary['sospese'];
				}
				continue;
			}

			if ( self::avvisa( $id, $days_left ) ) {
				++$summary['avvisi'];
			}
		}

		return $summary;
	}

	/**
	 * Invia l'avviso per la soglia più urgente non ancora notificata.
	 *
	 * @param int $id        ID scheda.
	 * @param int $days_left Giorni alla scadenza.
	 * @return bool True se un avviso è stato inviato.
	 */
	private static function avvisa( $id, $days_left ) {
		$raw     = get_post_meta( $id, 'advtr_scadenza_avvisi', true );
		$inviati = is_array( $raw ) ? array_map( 'intval', $raw ) : array();
		$soglie  = self::soglie();

		foreach ( $soglie as $soglia ) {
			// Prima soglia applicabile = livello di urgenza corrente.
			if ( $days_left <= $soglia ) {
				if ( in_array( $soglia, $inviati, true ) ) {
					return false; // Già avvisato a questo livello (o più urgente).
				}
				self::invia_avviso( $id, $days_left );
				// Marca come inviate questa soglia e tutte quelle superiori:
				// un avviso più urgente supera quelli meno urgenti futuri.
				foreach ( $soglie as $s ) {
					if ( $s >= $soglia ) {
						$inviati[] = $s;
					}
				}
				update_post_meta( $id, 'advtr_scadenza_avvisi', array_values( array_unique( $inviati ) ) );
				return true;
			}
		}
		return false;
	}

	/**
	 * Sospende una scheda scaduta (bozza) e notifica.
	 *
	 * @param int $id ID scheda.
	 * @return bool True se sospesa in questa esecuzione.
	 */
	private static function sospendi( $id ) {
		if ( get_post_meta( $id, 'advtr_sospesa', true ) ) {
			return false;
		}

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);
		update_post_meta( $id, 'advtr_sospesa', 1 );
		self::invia_sospensione( $id );
		return true;
	}

	/**
	 * Spegne il badge "in evidenza" se il periodo è terminato.
	 *
	 * @param int $id  ID scheda.
	 * @param int $now Timestamp corrente.
	 * @return bool True se spento in questa esecuzione.
	 */
	private static function spegni_evidenza_scaduta( $id, $now ) {
		if ( ! get_post_meta( $id, 'advtr_in_evidenza', true ) ) {
			return false;
		}
		$fine = get_post_meta( $id, 'advtr_evidenza_fine', true );
		if ( '' === $fine ) {
			return false;
		}
		if ( strtotime( $fine . ' 23:59:59' ) >= $now ) {
			return false;
		}
		update_post_meta( $id, 'advtr_in_evidenza', 0 );
		return true;
	}

	/**
	 * Destinatari di una notifica: admin del sito + autore della scheda.
	 *
	 * @param int $id ID scheda.
	 * @return string[]
	 */
	private static function destinatari( $id ) {
		$to     = array( get_option( 'admin_email' ) );
		$autore = get_post_field( 'post_author', $id );
		if ( $autore ) {
			$email = get_the_author_meta( 'user_email', (int) $autore );
			if ( $email ) {
				$to[] = $email;
			}
		}
		return array_values( array_unique( array_filter( $to ) ) );
	}

	/**
	 * Invia l'email di avviso scadenza imminente.
	 *
	 * @param int $id        ID scheda.
	 * @param int $days_left Giorni alla scadenza.
	 * @return void
	 */
	private static function invia_avviso( $id, $days_left ) {
		$titolo = get_the_title( $id );
		/* translators: %s: titolo scheda */
		$subject = sprintf( __( 'AdverTrieste — la scheda "%s" sta per scadere', 'advertrieste' ), $titolo );
		$lines   = array(
			sprintf(
				/* translators: 1: titolo scheda, 2: giorni mancanti */
				__( 'La scheda "%1$s" scadrà tra %2$d giorni.', 'advertrieste' ),
				$titolo,
				$days_left
			),
			__( 'Rinnova per mantenerla visibile sulla mappa.', 'advertrieste' ),
		);
		Mailer::send( self::destinatari( $id ), $subject, $lines );
	}

	/**
	 * Invia l'email di scheda sospesa per scadenza.
	 *
	 * @param int $id ID scheda.
	 * @return void
	 */
	private static function invia_sospensione( $id ) {
		$titolo = get_the_title( $id );
		/* translators: %s: titolo scheda */
		$subject = sprintf( __( 'AdverTrieste — la scheda "%s" è stata sospesa', 'advertrieste' ), $titolo );
		$lines   = array(
			sprintf(
				/* translators: %s: titolo scheda */
				__( 'La scheda "%s" è scaduta ed è stata sospesa: non è più visibile pubblicamente.', 'advertrieste' ),
				$titolo
			),
			__( 'Contatta l\'amministrazione per rinnovarla.', 'advertrieste' ),
		);
		Mailer::send( self::destinatari( $id ), $subject, $lines );
	}
}
