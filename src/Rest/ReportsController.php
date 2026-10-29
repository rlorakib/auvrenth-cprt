<?php
/**
 * Report REST API.
 *
 * @package Munafa
 */

namespace Munafa\Rest;

use Munafa\Reports\DateRange;
use Munafa\Reports\Repository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under `munafa/v1` that feed the dashboard.
 */
final class ReportsController {

	/**
	 * REST namespace.
	 */
	const NAMESPACE = 'munafa/v1';

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
		$routes = array(
			'/summary'  => array(
				'callback' => array( self::class, 'summary' ),
				'args'     => array(),
			),
			'/trend'    => array(
				'callback' => array( self::class, 'trend' ),
				'args'     => array(
					'interval' => array(
						'type'    => 'string',
						'enum'    => array( 'hour', 'day', 'week' ),
						'default' => 'day',
					),
				),
			),
			'/products' => array(
				'callback' => array( self::class, 'products' ),
				'args'     => array(
					'orderby' => array(
						'type'    => 'string',
						'enum'    => array( 'profit', 'loss' ),
						'default' => 'profit',
					),
					'limit'   => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 50,
						'default' => 10,
					),
				),
			),
			'/returns'  => array(
				'callback' => array( self::class, 'returns' ),
				'args'     => array(),
			),
		);

		foreach ( $routes as $route => $config ) {
			register_rest_route(
				self::NAMESPACE,
				$route,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => $config['callback'],
					'permission_callback' => array( self::class, 'can_view' ),
					'args'                => array_merge( self::date_args(), $config['args'] ),
				)
			);
		}
	}

	/**
	 * Only shop managers and admins may see profit.
	 */
	public static function can_view(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * `GET /summary`: totals, plus the previous period for comparison.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function summary( WP_REST_Request $request ): WP_REST_Response {
		$range = self::range( $request );

		return new WP_REST_Response(
			array(
				'range'    => $range->to_array(),
				'current'  => Repository::summary( $range ),
				'previous' => Repository::summary( $range->previous() ),
			)
		);
	}

	/**
	 * `GET /trend`: revenue and profit per hour (ranges up to a week), day or week.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function trend( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( Repository::trend( self::range( $request ), $request['interval'] ) );
	}

	/**
	 * `GET /products`: most profitable or loss making products.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function products( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( Repository::products( self::range( $request ), $request['orderby'], (int) $request['limit'] ) );
	}

	/**
	 * `GET /returns`: returns per district and reason.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function returns( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( Repository::returns( self::range( $request ) ) );
	}

	/**
	 * `after` and `before` arguments shared by every route.
	 *
	 * @return array<string, array>
	 */
	private static function date_args(): array {
		$date = array(
			'type'    => 'string',
			'pattern' => '^\d{4}-\d{2}-\d{2}$',
		);

		return array(
			'after'  => $date + array( 'description' => __( 'First day (Y-m-d, site time). Defaults to 30 days before `before`.', 'munafa' ) ),
			'before' => $date + array( 'description' => __( 'Last day (Y-m-d, site time). Defaults to today.', 'munafa' ) ),
		);
	}

	/**
	 * Date range from the request.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private static function range( WP_REST_Request $request ): DateRange {
		return DateRange::from_dates( $request['after'], $request['before'] );
	}
}
