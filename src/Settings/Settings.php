<?php
/**
 * Plugin settings.
 *
 * @package Munafa
 */

namespace Munafa\Settings;

use Munafa\Orders\ReturnStatus;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates and saves every Munafa setting.
 *
 * Nothing here is tied to one country: sellers define their own courier zones,
 * payment method fees and return reasons.
 */
final class Settings {

	/**
	 * Bump when settings need migrating.
	 */
	const VERSION = '2';

	const VERSION_OPTION   = 'munafa_settings_version';
	const ZONES_OPTION     = 'munafa_courier_zones';
	const DEFAULT_OPTION   = 'munafa_courier_default';
	const PAYMENTS_OPTION  = 'munafa_payment_methods';
	const REASONS_OPTION   = 'munafa_return_reasons';
	const STATUSES_OPTION  = 'munafa_report_statuses';
	const PACKAGING_OPTION = 'munafa_packaging_cost';
	const SETUP_OPTION     = 'munafa_setup_done';

	/**
	 * Highest return charge accepted, in percent of the delivery charge.
	 */
	const MAX_RETURN_PERCENT = 200;

	/**
	 * Courier zones in the order they are matched.
	 *
	 * @return array<int, array{id: string, name: string, areas: string[], delivery_charge: float, return_percent: float}>
	 */
	public static function zones(): array {
		$zones = get_option( self::ZONES_OPTION, array() );
		return is_array( $zones ) ? array_values( $zones ) : array();
	}

	/**
	 * Charges for addresses that match no zone.
	 *
	 * @return array{id: string, name: string, areas: string[], delivery_charge: float, return_percent: float}
	 */
	public static function default_zone(): array {
		$zone = get_option( self::DEFAULT_OPTION, array() );
		$zone = is_array( $zone ) ? $zone : array();

		return array(
			'id'              => 'default',
			'name'            => __( 'Everywhere else', 'munafa' ),
			'areas'           => array(),
			'delivery_charge' => (float) ( $zone['delivery_charge'] ?? 0 ),
			'return_percent'  => (float) ( $zone['return_percent'] ?? 0 ),
		);
	}

	/**
	 * Fee settings for every configured payment method, keyed by gateway ID.
	 *
	 * @return array<string, array{is_cod: bool, fee_percent: float}>
	 */
	public static function payment_methods(): array {
		$methods = get_option( self::PAYMENTS_OPTION, array() );
		return is_array( $methods ) ? $methods : array();
	}

	/**
	 * Fee settings for one payment method. Unconfigured methods cost nothing,
	 * and only WooCommerce's own `cod` gateway counts as cash on delivery.
	 *
	 * @param string $gateway_id Payment method ID stored on the order.
	 * @return array{is_cod: bool, fee_percent: float}
	 */
	public static function payment_method( string $gateway_id ): array {
		$methods = self::payment_methods();
		if ( isset( $methods[ $gateway_id ] ) ) {
			return array(
				'is_cod'      => (bool) $methods[ $gateway_id ]['is_cod'],
				'fee_percent' => (float) $methods[ $gateway_id ]['fee_percent'],
			);
		}

		return array(
			'is_cod'      => 'cod' === $gateway_id,
			'fee_percent' => 0.0,
		);
	}

	/**
	 * Return reasons, keyed by the value stored on orders.
	 *
	 * @return array<string, string>
	 */
	public static function return_reasons(): array {
		$reasons = get_option( self::REASONS_OPTION, false );
		if ( ! is_array( $reasons ) ) {
			return self::default_reasons();
		}

		return wp_list_pluck( $reasons, 'label', 'key' );
	}

	/**
	 * Order statuses counted in reports (without the `wc-` prefix).
	 *
	 * @return string[]
	 */
	public static function report_statuses(): array {
		$statuses = get_option( self::STATUSES_OPTION, false );
		return is_array( $statuses ) ? $statuses : self::default_statuses();
	}

	/**
	 * Packaging cost of every parcel.
	 */
	public static function packaging_cost(): float {
		return (float) get_option( self::PACKAGING_OPTION, 0 );
	}

	/**
	 * Whether the seller has saved the settings at least once.
	 */
	public static function is_setup_done(): bool {
		return 'yes' === get_option( self::SETUP_OPTION );
	}

	/**
	 * Store currency symbol for labels, without the trailing space WooCommerce adds to some (e.g. "৳&nbsp;").
	 */
	public static function currency_symbol(): string {
		return trim( str_replace( '&nbsp;', '', get_woocommerce_currency_symbol() ) );
	}

	/**
	 * Every setting, in the shape the settings screen edits.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$default = self::default_zone();
		$methods = array();
		foreach ( self::payment_methods() as $id => $method ) {
			$methods[ $id ] = self::payment_method( $id );
		}

		$reasons = array();
		foreach ( self::return_reasons() as $key => $label ) {
			$reasons[] = array(
				'key'   => $key,
				'label' => $label,
			);
		}

		return array(
			'zones'           => self::zones(),
			'default_zone'    => array(
				'delivery_charge' => $default['delivery_charge'],
				'return_percent'  => $default['return_percent'],
			),
			'payment_methods' => (object) $methods,
			'return_reasons'  => $reasons,
			'report_statuses' => self::report_statuses(),
			'packaging_cost'  => self::packaging_cost(),
			'setup_done'      => self::is_setup_done(),
		);
	}

	/**
	 * Validate and save settings from the settings screen.
	 *
	 * @param array $input Settings in the shape of all().
	 * @return array|WP_Error Saved settings, or the first problem found.
	 */
	public static function save( array $input ) {
		$clean = self::sanitize( $input );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		update_option( self::ZONES_OPTION, $clean['zones'], false );
		update_option( self::DEFAULT_OPTION, $clean['default_zone'], false );
		update_option( self::PAYMENTS_OPTION, $clean['payment_methods'], false );
		update_option( self::REASONS_OPTION, $clean['return_reasons'], false );
		update_option( self::STATUSES_OPTION, $clean['report_statuses'], false );
		update_option( self::PACKAGING_OPTION, $clean['packaging_cost'], false );
		update_option( self::SETUP_OPTION, 'yes', false );

		return self::all();
	}

	/**
	 * Validate settings.
	 *
	 * @param array $input Settings in the shape of all().
	 * @return array|WP_Error
	 */
	public static function sanitize( array $input ) {
		$known_areas = self::known_areas();

		$zones = array();
		foreach ( (array) ( $input['zones'] ?? array() ) as $index => $zone ) {
			$zone = (array) $zone;
			$name = sanitize_text_field( $zone['name'] ?? '' );
			/* translators: %d: zone number */
			$label = sprintf( __( 'Zone %d', 'munafa' ), $index + 1 );

			if ( '' === $name ) {
				/* translators: %s: zone label, e.g. "Zone 2" */
				return self::error( sprintf( __( '%s needs a name.', 'munafa' ), $label ) );
			}

			$areas = array();
			foreach ( (array) ( $zone['areas'] ?? array() ) as $area ) {
				$area = strtoupper( sanitize_text_field( (string) $area ) );
				if ( ! isset( $known_areas[ $area ] ) ) {
					/* translators: 1: zone name, 2: area code */
					return self::error( sprintf( __( 'Zone "%1$s" has an unknown area: %2$s.', 'munafa' ), $name, $area ) );
				}
				$areas[] = $area;
			}

			if ( ! $areas ) {
				/* translators: %s: zone name */
				return self::error( sprintf( __( 'Zone "%s" needs at least one area.', 'munafa' ), $name ) );
			}

			$charges = self::charges( $zone, $name );
			if ( is_wp_error( $charges ) ) {
				return $charges;
			}

			$id      = sanitize_key( $zone['id'] ?? '' );
			$zones[] = array(
				'id'    => '' !== $id ? $id : 'zone-' . wp_generate_password( 8, false, false ),
				'name'  => $name,
				'areas' => array_values( array_unique( $areas ) ),
			) + $charges;
		}

		$default = self::charges( (array) ( $input['default_zone'] ?? array() ), __( 'Everywhere else', 'munafa' ) );
		if ( is_wp_error( $default ) ) {
			return $default;
		}

		$methods = array();
		foreach ( (array) ( $input['payment_methods'] ?? array() ) as $id => $method ) {
			$method = (array) $method;
			$fee    = self::number( $method['fee_percent'] ?? 0 );
			if ( null === $fee || $fee > 100 ) {
				/* translators: %s: payment method ID */
				return self::error( sprintf( __( 'The fee for "%s" must be between 0 and 100%%.', 'munafa' ), $id ) );
			}
			$methods[ sanitize_key( $id ) ] = array(
				'is_cod'      => ! empty( $method['is_cod'] ),
				'fee_percent' => $fee,
			);
		}

		$reasons = array();
		foreach ( (array) ( $input['return_reasons'] ?? array() ) as $reason ) {
			$reason = (array) $reason;
			$label  = sanitize_text_field( $reason['label'] ?? '' );
			if ( '' === $label ) {
				continue;
			}

			$key = sanitize_key( $reason['key'] ?? '' );
			if ( '' === $key ) {
				$key = sanitize_key( sanitize_title( $label ) );
				$key = '' !== $key ? $key : 'reason';
			}
			// Keep keys unique so two reasons never share stored order data.
			$base = $key;
			for ( $i = 2; isset( $reasons[ $key ] ); $i++ ) {
				$key = "{$base}-{$i}";
			}

			$reasons[ $key ] = array(
				'key'   => $key,
				'label' => $label,
			);
		}

		$valid_statuses = array_map(
			static function ( $status ) {
				return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
			},
			array_keys( wc_get_order_statuses() )
		);
		$statuses       = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input['report_statuses'] ?? array() ) ), $valid_statuses ) );
		if ( ! $statuses ) {
			return self::error( __( 'Choose at least one order status for reports.', 'munafa' ) );
		}

		$packaging = self::number( $input['packaging_cost'] ?? 0 );
		if ( null === $packaging ) {
			return self::error( __( 'Packaging cost must be 0 or more.', 'munafa' ) );
		}

		return array(
			'zones'           => $zones,
			'default_zone'    => $default,
			'payment_methods' => $methods,
			'return_reasons'  => array_values( $reasons ),
			'report_statuses' => $statuses,
			'packaging_cost'  => $packaging,
		);
	}

	/**
	 * Move settings from the old Dhaka/outside-Dhaka options (settings version 1).
	 * Hooked late on `init`, when payment gateways can be loaded.
	 */
	public static function maybe_migrate(): void {
		if ( self::VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		$old_keys = array( 'courier_inside_dhaka', 'courier_outside_dhaka', 'cod_fee_percent', 'gateway_fee_percent', 'return_charge_percent', 'packaging_cost' );
		$old      = array();
		$saved    = false;
		foreach ( $old_keys as $key ) {
			$value       = get_option( 'munafa_' . $key, null );
			$saved       = $saved || null !== $value;
			$old[ $key ] = $value;
		}

		// Values the version 1 settings used when nothing was saved.
		$inside  = (float) ( $old['courier_inside_dhaka'] ?? 60 );
		$outside = (float) ( $old['courier_outside_dhaka'] ?? 120 );
		$cod     = (float) ( $old['cod_fee_percent'] ?? 1 );
		$online  = (float) ( $old['gateway_fee_percent'] ?? 2 );
		$return  = (float) ( $old['return_charge_percent'] ?? 50 );

		if ( false === get_option( self::ZONES_OPTION ) ) {
			update_option(
				self::ZONES_OPTION,
				array(
					array(
						'id'              => 'inside-dhaka',
						'name'            => 'Inside Dhaka',
						'areas'           => array( 'BD:BD-13' ),
						'delivery_charge' => $inside,
						'return_percent'  => $return,
					),
				),
				false
			);
			update_option(
				self::DEFAULT_OPTION,
				array(
					'delivery_charge' => $outside,
					'return_percent'  => $return,
				),
				false
			);

			$methods = array();
			foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
				$methods[ $id ] = array(
					'is_cod'      => 'cod' === $id,
					'fee_percent' => 'cod' === $id ? $cod : $online,
				);
			}
			update_option( self::PAYMENTS_OPTION, $methods, false );
		}

		// packaging_cost keeps its option name, so it carries over by itself.
		foreach ( array_diff( $old_keys, array( 'packaging_cost' ) ) as $key ) {
			delete_option( 'munafa_' . $key );
		}

		if ( $saved ) {
			update_option( self::SETUP_OPTION, 'yes', false );
		}
		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * Countries and states (as `CC` and `CC:STATE`) that zones may use: the store's selling countries.
	 *
	 * @return array<string, true>
	 */
	public static function known_areas(): array {
		$areas = array();
		foreach ( array_keys( WC()->countries->get_allowed_countries() ) as $country ) {
			$areas[ $country ] = true;
			foreach ( array_keys( (array) WC()->countries->get_states( $country ) ) as $state ) {
				$areas[ "{$country}:{$state}" ] = true;
			}
		}
		return $areas;
	}

	/**
	 * Reasons a new install starts with.
	 *
	 * @return array<string, string>
	 */
	private static function default_reasons(): array {
		return array(
			'customer_refused' => __( 'Customer refused', 'munafa' ),
			'wrong_address'    => __( 'Wrong address', 'munafa' ),
			'phone_off'        => __( 'Phone off / unreachable', 'munafa' ),
			'not_available'    => __( 'Customer not available', 'munafa' ),
			'other'            => __( 'Other', 'munafa' ),
		);
	}

	/**
	 * Statuses a new install reports on.
	 *
	 * @return string[]
	 */
	private static function default_statuses(): array {
		return array( 'completed', 'processing', 'on-hold', ReturnStatus::STATUS );
	}

	/**
	 * Validate a zone's delivery charge and return percentage.
	 *
	 * @param array  $zone Zone input.
	 * @param string $name Zone name for error messages.
	 * @return array{delivery_charge: float, return_percent: float}|WP_Error
	 */
	private static function charges( array $zone, string $name ) {
		$charge = self::number( $zone['delivery_charge'] ?? 0 );
		if ( null === $charge ) {
			/* translators: %s: zone name */
			return self::error( sprintf( __( 'The delivery charge for "%s" must be 0 or more.', 'munafa' ), $name ) );
		}

		$percent = self::number( $zone['return_percent'] ?? 0 );
		if ( null === $percent || $percent > self::MAX_RETURN_PERCENT ) {
			/* translators: 1: zone name, 2: maximum percentage */
			return self::error( sprintf( __( 'The return charge for "%1$s" must be between 0 and %2$d%%.', 'munafa' ), $name, self::MAX_RETURN_PERCENT ) );
		}

		return array(
			'delivery_charge' => $charge,
			'return_percent'  => $percent,
		);
	}

	/**
	 * A non-negative number from user input; empty counts as 0.
	 *
	 * @param mixed $value Input.
	 * @return float|null Null when the input is not a number or is negative.
	 */
	private static function number( $value ): ?float {
		if ( '' === $value || null === $value ) {
			return 0.0;
		}
		if ( ! is_numeric( $value ) || (float) $value < 0 ) {
			return null;
		}
		return round( (float) $value, 4 );
	}

	/**
	 * Validation error for the REST response.
	 *
	 * @param string $message Message.
	 */
	private static function error( string $message ): WP_Error {
		return new WP_Error( 'munafa_invalid_settings', $message, array( 'status' => 400 ) );
	}
}
