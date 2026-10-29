<?php
/**
 * Simple checkout module.
 *
 * @package Munafa
 */

namespace Munafa\Modules\Checkout;

use Munafa\Modules\Module;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Shorter checkout for COD customers: hides or relaxes address fields they
 * struggle with, and stops orders whose phone number can't be right.
 *
 * Works with both the Checkout block and the classic checkout, because both
 * read field rules from WooCommerce's country locale.
 */
final class CheckoutModule extends Module {

	/**
	 * Address fields the seller can change. First name, address, state and country always stay.
	 */
	const FIELDS = array( 'last_name', 'company', 'address_2', 'city', 'postcode' );

	/**
	 * What can be done with a field. `default` leaves it to WooCommerce.
	 */
	const RULES = array( 'default', 'required', 'optional', 'hidden' );

	/**
	 * Most phone patterns accepted.
	 */
	const MAX_PATTERNS = 10;

	/**
	 * Module ID.
	 */
	public function id(): string {
		return 'checkout';
	}

	/**
	 * Name shown on the Modules tab.
	 */
	public function name(): string {
		return __( 'Simple checkout', 'munafa' );
	}

	/**
	 * What the module does.
	 */
	public function description(): string {
		return __( 'Hide the checkout fields your customers struggle with, and stop orders with a wrong phone number before they are placed.', 'munafa' );
	}

	/**
	 * Everything starts as WooCommerce has it, with no phone check.
	 */
	public function defaults(): array {
		return array(
			'fields'         => array_fill_keys( self::FIELDS, 'default' ),
			'phone_patterns' => array(),
		);
	}

	/**
	 * Validate settings.
	 *
	 * @param array $input Settings in the shape of defaults().
	 * @return array|WP_Error
	 */
	public function sanitize( array $input ) {
		$fields = array();
		$rules  = (array) ( $input['fields'] ?? array() );
		foreach ( self::FIELDS as $field ) {
			$rule             = isset( $rules[ $field ] ) && is_scalar( $rules[ $field ] ) ? sanitize_key( $rules[ $field ] ) : 'default';
			$fields[ $field ] = in_array( $rule, self::RULES, true ) ? $rule : 'default';
		}

		$patterns = array();
		foreach ( (array) ( $input['phone_patterns'] ?? array() ) as $pattern ) {
			$pattern = is_scalar( $pattern ) ? (string) preg_replace( '/\s+/', '', (string) $pattern ) : '';
			if ( '' === $pattern ) {
				continue;
			}
			if ( ! PhoneMask::is_valid_pattern( $pattern ) ) {
				return new WP_Error(
					'munafa_invalid_settings',
					/* translators: %s: phone pattern the seller typed */
					sprintf( __( '"%s" is not a phone pattern. Use digits, # for any digit, and + only at the start.', 'munafa' ), sanitize_text_field( $pattern ) ),
					array( 'status' => 400 )
				);
			}
			$patterns[] = $pattern;
		}

		$patterns = array_values( array_unique( $patterns ) );
		if ( count( $patterns ) > self::MAX_PATTERNS ) {
			return new WP_Error(
				'munafa_invalid_settings',
				/* translators: %d: maximum number of patterns */
				sprintf( __( 'Use at most %d phone patterns.', 'munafa' ), self::MAX_PATTERNS ),
				array( 'status' => 400 )
			);
		}

		return array(
			'fields'         => $fields,
			'phone_patterns' => $patterns,
		);
	}

	/**
	 * How WooCommerce's own Phone setting is set, so the tab can warn when the phone is optional.
	 */
	public function choices(): array {
		return array(
			'phone_field' => (string) get_option( 'woocommerce_checkout_phone_field', 'optional' ),
		);
	}

	/**
	 * Add hooks.
	 */
	public function register(): void {
		// Every country's locale and the default one, so the rules hold whatever country is picked.
		add_filter( 'woocommerce_get_country_locale', array( $this, 'filter_country_locales' ) );
		// After the Checkout block adds its own fields to the default locale (priority 10).
		add_filter( 'woocommerce_get_country_locale_default', array( $this, 'apply_field_rules' ), 20 );
		// The classic checkout script only shows, hides and requires fields it has selectors for.
		add_filter( 'woocommerce_country_locale_field_selectors', array( $this, 'add_field_selectors' ) );

		// Store phone numbers with plain digits, then check them.
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'normalize_posted_phones' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'normalize_order_phones' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_classic_checkout' ), 10, 2 );
		add_action( 'woocommerce_blocks_validate_location_address_fields', array( $this, 'validate_block_checkout' ), 10, 3 );
	}

	/**
	 * Apply the field rules to every country with its own locale.
	 *
	 * @param array $locales Locales keyed by country code.
	 * @return array
	 */
	public function filter_country_locales( $locales ) {
		foreach ( (array) $locales as $country => $fields ) {
			$locales[ $country ] = $this->apply_field_rules( (array) $fields );
		}
		return $locales;
	}

	/**
	 * Apply the field rules to one locale.
	 *
	 * @param array $fields Field settings keyed by field name.
	 * @return array
	 */
	public function apply_field_rules( $fields ) {
		$fields = (array) $fields;
		foreach ( $this->settings()['fields'] as $field => $rule ) {
			if ( 'default' === $rule || ! in_array( $field, self::FIELDS, true ) ) {
				continue;
			}
			$fields[ $field ] = array_merge(
				(array) ( $fields[ $field ] ?? array() ),
				array(
					'required' => 'required' === $rule,
					'hidden'   => 'hidden' === $rule,
				)
			);
		}
		return $fields;
	}

	/**
	 * Selectors for the fields the classic checkout script doesn't know about.
	 *
	 * @param array<string, string> $selectors Selectors keyed by field name.
	 * @return array<string, string>
	 */
	public function add_field_selectors( $selectors ) {
		foreach ( self::FIELDS as $field ) {
			if ( ! isset( $selectors[ $field ] ) ) {
				$selectors[ $field ] = "#billing_{$field}_field, #shipping_{$field}_field";
			}
		}
		return $selectors;
	}

	/**
	 * Classic checkout: clean phone numbers before they are validated and saved.
	 *
	 * @param array $data Posted checkout data.
	 * @return array
	 */
	public function normalize_posted_phones( $data ) {
		foreach ( array( 'billing_phone', 'shipping_phone' ) as $key ) {
			if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				$data[ $key ] = PhoneMask::normalize( $data[ $key ] );
			}
		}
		return $data;
	}

	/**
	 * Checkout block: clean phone numbers on the order before it is validated.
	 *
	 * @param WC_Order $order Order being placed.
	 */
	public function normalize_order_phones( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( $order->get_billing_phone() ) {
			$order->set_billing_phone( PhoneMask::normalize( $order->get_billing_phone() ) );
		}
		if ( $order->get_shipping_phone() ) {
			$order->set_shipping_phone( PhoneMask::normalize( $order->get_shipping_phone() ) );
		}
	}

	/**
	 * Classic checkout: reject phone numbers that fit none of the patterns.
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Checkout errors.
	 */
	public function validate_classic_checkout( $data, $errors ): void {
		foreach ( array( 'billing_phone', 'shipping_phone' ) as $key ) {
			$message = $this->phone_error( (string) ( $data[ $key ] ?? '' ) );
			if ( $message ) {
				$errors->add( $key . '_validation', $message, array( 'id' => $key ) );
			}
		}
	}

	/**
	 * Checkout block: reject phone numbers that fit none of the patterns.
	 *
	 * @param WP_Error $errors Address errors.
	 * @param array    $fields Address fields with their values.
	 * @param string   $group  `billing` or `shipping`.
	 */
	public function validate_block_checkout( $errors, $fields, $group ): void {
		unset( $group );
		$message = $this->phone_error( (string) ( $fields['phone'] ?? '' ) );
		if ( $message ) {
			$errors->add( 'munafa_invalid_phone', $message );
		}
	}

	/**
	 * Error message for a phone number that fits no pattern, or '' when it is fine.
	 * Empty numbers are left to WooCommerce's own Phone setting.
	 *
	 * @param string $phone Phone number.
	 */
	private function phone_error( string $phone ): string {
		$patterns = $this->settings()['phone_patterns'];
		if ( '' === trim( $phone ) || ! $patterns || PhoneMask::matches( $phone, $patterns ) ) {
			return '';
		}

		return sprintf(
			/* translators: 1: phone number the customer typed, 2: accepted formats, e.g. "01XXXXXXXXX, +8801XXXXXXXXX" */
			__( '%1$s is not a valid phone number. Accepted formats: %2$s', 'munafa' ),
			esc_html( $phone ),
			esc_html( implode( ', ', array_map( array( PhoneMask::class, 'example' ), $patterns ) ) )
		);
	}
}
