<?php
/**
 * Invio email del plugin.
 *
 * Piccolo wrapper attorno a `wp_mail` con corpo HTML minimale, per centralizzare
 * mittente/formattazione delle notifiche (scadenze, ecc.).
 *
 * @package AdverTrieste
 */

namespace AdverTrieste\Email;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility di invio email.
 */
class Mailer {

	/**
	 * Invia un'email HTML a uno o più destinatari.
	 *
	 * @param string[]|string $to      Destinatario/i.
	 * @param string          $subject Oggetto.
	 * @param string[]        $lines   Righe del corpo (testo semplice, verranno escapate).
	 * @return bool Esito di wp_mail.
	 */
	public static function send( $to, $subject, $lines ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$html = '<div style="font-family:sans-serif;font-size:14px;line-height:1.5;color:#1d2327">';
		foreach ( (array) $lines as $line ) {
			$html .= '<p>' . wp_kses_post( $line ) . '</p>';
		}
		$html .= '<p style="color:#787c82;font-size:12px">AdverTrieste</p></div>';

		return wp_mail( $to, $subject, $html, $headers );
	}
}
