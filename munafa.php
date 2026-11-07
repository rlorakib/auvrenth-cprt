<?php
/**
 * Plugin Name:          Auvrenth COD Profit & Return Tracker for WooCommerce
 * Description:          See the real net profit of every COD order: product cost, courier charge, fees and return loss.
 * Version:              0.1.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Auvrenth Studio
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          auvrenth-cprt
 * Domain Path:          /languages
 * WC requires at least: 9.0
 * WC tested up to:      11.1
 *
 * @package Cprt
 */

defined( 'ABSPATH' ) || exit;

define( 'CPRT_VERSION', '0.1.0' );
define( 'CPRT_FILE', __FILE__ );
define( 'CPRT_PATH', plugin_dir_path( __FILE__ ) );
define( 'CPRT_URL', plugin_dir_url( __FILE__ ) );

if ( ! file_exists( CPRT_PATH . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Auvrenth CPRT: dependencies are missing. Run "composer install" in the plugin folder.', 'auvrenth-cprt' );
			echo '</p></div>';
		}
	);
	return;
}

require_once CPRT_PATH . 'vendor/autoload.php';

register_activation_hook( __FILE__, array( Cprt\Db\Installer::class, 'install' ) );

add_action( 'before_woocommerce_init', array( Cprt\Compatibility::class, 'declare' ) );
add_action( 'plugins_loaded', array( Cprt\Plugin::class, 'boot' ) );
