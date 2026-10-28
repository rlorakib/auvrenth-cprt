<?php
/**
 * Database table setup.
 *
 * @package Munafa
 */

namespace Munafa\Db;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the order profit table.
 */
final class Installer {

	/**
	 * Bump this when the table schema changes.
	 */
	const DB_VERSION = '1';

	/**
	 * Option that stores the installed schema version.
	 */
	const VERSION_OPTION = 'munafa_db_version';

	/**
	 * Option that asks for a full recalculation once Action Scheduler is ready.
	 */
	const RECALC_OPTION = 'munafa_needs_recalculation';

	/**
	 * Full name of the order profit table.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'munafa_order_profit';
	}

	/**
	 * Run on activation, and on load when the schema version is outdated.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		// dbDelta needs two spaces after PRIMARY KEY and one field per line.
		dbDelta(
			"CREATE TABLE {$table} (
				order_id bigint(20) unsigned NOT NULL,
				order_date_gmt datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				order_status varchar(32) NOT NULL DEFAULT '',
				payment_method varchar(100) NOT NULL DEFAULT '',
				delivery_state varchar(32) NOT NULL DEFAULT '',
				revenue decimal(19,4) NOT NULL DEFAULT 0,
				product_cost decimal(19,4) NOT NULL DEFAULT 0,
				courier_cost decimal(19,4) NOT NULL DEFAULT 0,
				cod_fee decimal(19,4) NOT NULL DEFAULT 0,
				gateway_fee decimal(19,4) NOT NULL DEFAULT 0,
				return_cost decimal(19,4) NOT NULL DEFAULT 0,
				net_profit decimal(19,4) NOT NULL DEFAULT 0,
				missing_cost tinyint(1) NOT NULL DEFAULT 0,
				calculated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (order_id),
				KEY order_date_gmt (order_date_gmt),
				KEY order_status (order_status)
			) {$collate};"
		);

		$first_install = false === get_option( self::VERSION_OPTION );
		update_option( self::VERSION_OPTION, self::DB_VERSION );

		if ( $first_install ) {
			update_option( self::RECALC_OPTION, 'yes' );
		}
	}

	/**
	 * Install or upgrade the table if the stored version is behind.
	 */
	public static function maybe_upgrade(): void {
		if ( self::DB_VERSION !== get_option( self::VERSION_OPTION ) ) {
			self::install();
		}
	}
}
