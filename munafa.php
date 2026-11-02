<?php
/**
 * Plugin Name:          Munafa – COD Profit & Return Tracker for WooCommerce
 * Description:          See the real net profit of every COD order: product cost, courier charge, fees and return loss.
 * Version:              0.1.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Auvrenth Studio
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          munafa
 * Domain Path:          /languages
 * WC requires at least: 9.0
 * WC tested up to:      11.1
 *
 * @package Munafa
 */

defined( 'ABSPATH' ) || exit;

define( 'MUNAFA_VERSION', '0.1.0' );
define( 'MUNAFA_FILE', __FILE__ );
define( 'MUNAFA_PATH', plugin_dir_path( __FILE__ ) );
define( 'MUNAFA_URL', plugin_dir_url( __FILE__ ) );

if ( ! file_exists( MUNAFA_PATH . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Munafa: dependencies are missing. Run "composer install" in the plugin folder.', 'munafa' );
			echo '</p></div>';
		}
	);
	return;
}

require_once MUNAFA_PATH . 'vendor/autoload.php';

register_activation_hook( __FILE__, array( Munafa\Db\Installer::class, 'install' ) );

add_action( 'before_woocommerce_init', array( Munafa\Compatibility::class, 'declare' ) );
add_action( 'plugins_loaded', array( Munafa\Plugin::class, 'boot' ) );
