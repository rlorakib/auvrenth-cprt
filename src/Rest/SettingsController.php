<?php
/**
 * Settings REST API.
 *
 * @package Munafa
 */

namespace Munafa\Rest;

use Munafa\Modules\Registry;
use Munafa\Settings\Settings;
use Munafa\Tools\Recalculator;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `munafa/v1/settings` and `munafa/v1/recalculate`, used by the settings screen.
 */
final class SettingsController {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register the routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			ReportsController::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get' ),
					'permission_callback' => array( ReportsController::class, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update' ),
					'permission_callback' => array( ReportsController::class, 'can_view' ),
				),
			)
		);

		register_rest_route(
			ReportsController::NAMESPACE,
			'/recalculate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'recalculate' ),
				'permission_callback' => array( ReportsController::class, 'can_view' ),
			)
		);
	}

	/**
	 * `GET /settings`: current settings plus the choices the screen offers.
	 */
	public static function get(): WP_REST_Response {
		return new WP_REST_Response( self::response( Settings::all() ) );
	}

	/**
	 * `POST /settings`: validate and save.
	 *
	 * @param WP_REST_Request $request Request with the settings as JSON body.
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function update( WP_REST_Request $request ) {
		$saved = Settings::save( (array) $request->get_json_params() );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return new WP_REST_Response( self::response( $saved ) );
	}

	/**
	 * `POST /recalculate`: recalculate every order in the background.
	 */
	public static function recalculate(): WP_REST_Response {
		Recalculator::start();
		return new WP_REST_Response( array( 'recalculating' => true ) );
	}

	/**
	 * Settings with the lists the screen needs to edit them.
	 *
	 * @param array $settings Settings from Settings::all().
	 */
	private static function response( array $settings ): array {
		$countries = array();
		foreach ( WC()->countries->get_allowed_countries() as $code => $name ) {
			$states = array();
			foreach ( (array) WC()->countries->get_states( $code ) as $state_code => $state_name ) {
				$states[] = array(
					'code' => (string) $state_code,
					'name' => html_entity_decode( $state_name, ENT_QUOTES, 'UTF-8' ),
				);
			}
			$countries[] = array(
				'code'   => $code,
				'name'   => html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ),
				'states' => $states,
			);
		}

		$gateways = array();
		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
			$gateways[] = array(
				'id'      => $id,
				'title'   => wp_strip_all_tags( $gateway->get_method_title() ? $gateway->get_method_title() : $gateway->get_title() ),
				'enabled' => 'yes' === $gateway->enabled,
			);
		}

		$statuses = array();
		foreach ( wc_get_order_statuses() as $key => $label ) {
			$statuses[] = array(
				'key'   => 0 === strpos( $key, 'wc-' ) ? substr( $key, 3 ) : $key,
				'label' => $label,
			);
		}

		$modules = array();
		foreach ( Registry::all() as $id => $module ) {
			$modules[] = array(
				'id'          => $id,
				'name'        => $module->name(),
				'description' => $module->description(),
				'choices'     => (object) $module->choices(),
			);
		}

		return array(
			'settings'      => $settings,
			'choices'       => array(
				'countries' => $countries,
				'gateways'  => $gateways,
				'statuses'  => $statuses,
				'modules'   => $modules,
			),
			'recalculating' => Recalculator::is_running(),
		);
	}
}
