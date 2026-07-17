<?php
/**
 * Autoloader delle classi del plugin.
 *
 * Mappa il namespace `AdverTrieste\` sulla cartella `includes/`, rispettando la
 * convenzione di naming dei file dei WordPress Coding Standards (`class-*.php`).
 * Esempi:
 *   AdverTrieste\Plugin          -> includes/class-plugin.php
 *   AdverTrieste\Cpt\Locale      -> includes/cpt/class-locale.php
 *   AdverTrieste\Cpt\PuntoQr     -> includes/cpt/class-punto-qr.php
 *
 * @package AdverTrieste
 */

namespace AdverTrieste;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra e risolve l'autoload per le classi del namespace del plugin.
 */
class Autoloader {

	/**
	 * Prefisso di namespace gestito da questo autoloader.
	 *
	 * @var string
	 */
	const PREFIX = 'AdverTrieste\\';

	/**
	 * Registra l'autoloader sullo stack SPL.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Risolve una classe del plugin nel percorso del file corrispondente.
	 *
	 * @param string $class Nome completo della classe (con namespace).
	 * @return void
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$parts    = explode( '\\', $relative );
		$class    = array_pop( $parts );

		$subdir = '';
		if ( ! empty( $parts ) ) {
			$subdir = strtolower( implode( '/', $parts ) ) . '/';
		}

		$file = 'class-' . self::hyphenate( $class ) . '.php';
		$path = ADVTR_PATH . 'includes/' . $subdir . $file;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}

	/**
	 * Converte un nome di classe PascalCase nel formato con trattini minuscolo.
	 *
	 * Es. `PuntoQr` -> `punto-qr`, `Locale` -> `locale`.
	 *
	 * @param string $name Nome della classe (senza namespace).
	 * @return string
	 */
	private static function hyphenate( $name ) {
		$name = preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $name );
		return strtolower( $name );
	}
}
