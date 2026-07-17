<?php
/**
 * Plugin Name:       AdverTrieste
 * Plugin URI:        https://github.com/lovefromgian/advertrieste
 * Description:       Directory su mappa di attività e luoghi del territorio di Trieste: schede clienti, statistiche, offerte/coupon, eventi e area riservata.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.3
 * Author:            lovefromgian
 * Author URI:        https://github.com/lovefromgian
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       advertrieste
 * Domain Path:       /languages
 *
 * @package AdverTrieste
 */

namespace AdverTrieste;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Costanti del plugin.
define( 'ADVTR_VERSION', '0.1.0' );
define( 'ADVTR_FILE', __FILE__ );
define( 'ADVTR_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADVTR_URL', plugin_dir_url( __FILE__ ) );

// Autoloader delle classi del plugin (deve essere caricato a mano: si autocarica il resto).
require_once ADVTR_PATH . 'includes/class-autoloader.php';
Autoloader::register();

// Avvio del plugin.
Plugin::instance()->boot();
