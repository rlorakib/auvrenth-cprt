<?php
/**
 * Runs when Auvrenth CPRT is deleted from the Plugins screen.
 *
 * Deletes Auvrenth CPRT's data only when the seller turned on "Delete all Auvrenth CPRT data
 * when the plugin is deleted" in the settings. Orders and products are never touched,
 * only the data Auvrenth CPRT added to them.
 *
 * The plugin is not loaded here, so names are spelled out instead of using the class constants.
 *
 * @package Auvrenth CPRT
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete Auvrenth CPRT's data from the current site, if the seller asked for it.
 */
function cprt_uninstall_site(): void {
	global $wpdb;

	if ( 'yes' !== get_option( 'cprt_delete_data' ) ) {
		return;
	}

	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'cprt_recalculate_batch' );
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing the plugin's own tables.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cprt_order_profit" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cprt_order_items" );
	// phpcs:enable

	$options = array(
		'cprt_db_version',
		'cprt_needs_recalculation',
		'cprt_settings_version',
		'cprt_courier_zones',
		'cprt_courier_default',
		'cprt_payment_methods',
		'cprt_return_reasons',
		'cprt_report_statuses',
		'cprt_packaging_cost',
		'cprt_setup_done',
		'cprt_delete_data',
		'cprt_modules',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Module settings: "cprt_module_checkout" and any added by add-ons.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$module_options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'cprt_module_' ) . '%' ) );
	foreach ( $module_options as $option ) {
		delete_option( $option );
	}

	$meta_keys = array( '_cprt_cost', '_cprt_courier_cost', '_cprt_return_reason' );

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
	) as $cprt_site_id ) {
		switch_to_blog( $cprt_site_id );
		cprt_uninstall_site();
		restore_current_blog();
	}
} else {
	cprt_uninstall_site();
}
