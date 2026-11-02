<?php
/**
 * Runs when Munafa is deleted from the Plugins screen.
 *
 * Deletes Munafa's data only when the seller turned on "Delete all Munafa data
 * when the plugin is deleted" in the settings. Orders and products are never touched,
 * only the data Munafa added to them.
 *
 * The plugin is not loaded here, so names are spelled out instead of using the class constants.
 *
 * @package Munafa
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete Munafa's data from the current site, if the seller asked for it.
 */
function munafa_uninstall_site(): void {
	global $wpdb;

	if ( 'yes' !== get_option( 'munafa_delete_data' ) ) {
		return;
	}

	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'munafa_recalculate_batch' );
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing the plugin's own tables.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}munafa_order_profit" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}munafa_order_items" );
	// phpcs:enable

	$options = array(
		'munafa_db_version',
		'munafa_needs_recalculation',
		'munafa_settings_version',
		'munafa_courier_zones',
		'munafa_courier_default',
		'munafa_payment_methods',
		'munafa_return_reasons',
		'munafa_report_statuses',
		'munafa_packaging_cost',
		'munafa_setup_done',
		'munafa_delete_data',
		'munafa_modules',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Module settings: "munafa_module_checkout" and any added by add-ons.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$module_options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'munafa_module_' ) . '%' ) );
	foreach ( $module_options as $option ) {
		delete_option( $option );
	}

	$meta_keys = array( '_munafa_cost', '_munafa_courier_cost', '_munafa_return_reason' );

	// Product cost prices, and order meta under legacy order storage (or its sync copy).
	foreach ( $meta_keys as $meta_key ) {
		delete_post_meta_by_key( $meta_key );
	}

	// Order meta under HPOS. WooCommerce may be inactive, so query its table directly.
	$orders_meta = $wpdb->prefix . 'wc_orders_meta';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $orders_meta ) ) ) === $orders_meta ) {
		foreach ( $meta_keys as $meta_key ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name built from $wpdb->prefix.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$orders_meta} WHERE meta_key = %s", $meta_key ) );
		}
	}
	// phpcs:enable
}

if ( is_multisite() ) {
	// Each site has its own tables and its own "delete data" setting.
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $munafa_site_id ) {
		switch_to_blog( $munafa_site_id );
		munafa_uninstall_site();
		restore_current_blog();
	}
} else {
	munafa_uninstall_site();
}
