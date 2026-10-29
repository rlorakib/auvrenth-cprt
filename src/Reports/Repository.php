<?php
/**
 * Report queries.
 *
 * @package Munafa
 */

namespace Munafa\Reports;

use Munafa\Db\Installer;
use Munafa\Orders\ReturnStatus;
use Munafa\Settings\Settings;

defined( 'ABSPATH' ) || exit;

// Table names come from Installer and status placeholders are built from a fixed list,
// so the interpolated SQL below is safe; every value goes through $wpdb->prepare().
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
// The sniff can't count arguments passed as one merged array.
// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

/**
 * Reads profit reports from the Munafa tables.
 */
final class Repository {

	/**
	 * Longest range the trend is shown per hour for; longer ones fall back to days.
	 */
	const MAX_HOURLY_DAYS = 7;

	/**
	 * Order statuses counted in reports.
	 *
	 * @return string[]
	 */
	public static function statuses(): array {
		/**
		 * Filters which order statuses Munafa reports count (without the `wc-` prefix).
		 *
		 * @param string[] $statuses Order statuses.
		 */
		$statuses = apply_filters( 'munafa_report_statuses', Settings::report_statuses() );
		return array_values( array_filter( array_map( 'sanitize_key', (array) $statuses ) ) );
	}

	/**
	 * Totals for a date range.
	 *
	 * @param DateRange $range Dates.
	 * @return array<string, float|int>
	 */
	public static function summary( DateRange $range ): array {
		global $wpdb;

		list( $where, $args ) = self::where( $range, 'o' );
		$table                = Installer::table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS orders,
					COALESCE( SUM( o.revenue ), 0 ) AS revenue,
					COALESCE( SUM( o.net_profit ), 0 ) AS net_profit,
					COALESCE( SUM( o.order_status = %s ), 0 ) AS returned,
					COALESCE( SUM( CASE WHEN o.order_status = %s THEN -o.net_profit ELSE 0 END ), 0 ) AS return_loss,
					COALESCE( SUM( o.missing_cost ), 0 ) AS missing_cost
				FROM {$table} o
				WHERE {$where}",
				array_merge( array( ReturnStatus::STATUS, ReturnStatus::STATUS ), $args )
			),
			ARRAY_A
		);

		$orders  = (int) $row['orders'];
		$revenue = (float) $row['revenue'];
		$profit  = (float) $row['net_profit'];

		return array(
			'orders'       => $orders,
			'revenue'      => round( $revenue, 2 ),
			'net_profit'   => round( $profit, 2 ),
			'margin'       => $revenue > 0 ? round( $profit / $revenue * 100, 1 ) : 0.0,
			'returned'     => (int) $row['returned'],
			'return_rate'  => $orders > 0 ? round( (int) $row['returned'] / $orders * 100, 1 ) : 0.0,
			'return_loss'  => round( (float) $row['return_loss'], 2 ),
			'missing_cost' => (int) $row['missing_cost'],
		);
	}

	/**
	 * Revenue and profit per hour, day or week, with empty periods filled with zeros.
	 *
	 * @param DateRange $range    Dates.
	 * @param string    $interval `hour`, `day` or `week`. `hour` falls back to `day` past MAX_HOURLY_DAYS.
	 * @return array<int, array<string, float|int|string>>
	 */
	public static function trend( DateRange $range, string $interval ): array {
		global $wpdb;

		if ( 'hour' === $interval && $range->days() > self::MAX_HOURLY_DAYS ) {
			$interval = 'day';
		}

		list( $where, $args ) = self::where( $range, 'o' );
		$table                = Installer::table();
		$offset               = wp_timezone()->getOffset( new \DateTimeImmutable( 'now' ) );
		// Rows are grouped per hour ("Y-m-d H:00") or per day; weeks are added up from days below.
		$format = 'hour' === $interval ? '%%Y-%%m-%%d %%H:00' : '%%Y-%%m-%%d';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT( DATE_ADD( o.order_date_gmt, INTERVAL %d SECOND ), '{$format}' ) AS day,
					COUNT(*) AS orders,
					SUM( o.order_status = %s ) AS returned,
					SUM( o.revenue ) AS revenue,
					SUM( o.net_profit ) AS net_profit
				FROM {$table} o
				WHERE {$where}
				GROUP BY day",
				array_merge( array( $offset, ReturnStatus::STATUS ), $args )
			),
			OBJECT_K
		);

		$slots = array();
		foreach ( $range->each_day() as $day ) {
			if ( 'hour' === $interval ) {
				for ( $hour = 0; $hour < 24; $hour++ ) {
					$slots[] = sprintf( '%s %02d:00', $day, $hour );
				}
			} else {
				$slots[] = $day;
			}
		}

		$periods = array();
		foreach ( $slots as $slot ) {
			$key = 'week' === $interval ? self::week_start( $slot ) : $slot;
			if ( ! isset( $periods[ $key ] ) ) {
				$periods[ $key ] = array(
					'date'       => $key,
					'orders'     => 0,
					'returned'   => 0,
					'revenue'    => 0.0,
					'net_profit' => 0.0,
				);
			}
			if ( isset( $rows[ $slot ] ) ) {
				$periods[ $key ]['orders']     += (int) $rows[ $slot ]->orders;
				$periods[ $key ]['returned']   += (int) $rows[ $slot ]->returned;
				$periods[ $key ]['revenue']    += (float) $rows[ $slot ]->revenue;
				$periods[ $key ]['net_profit'] += (float) $rows[ $slot ]->net_profit;
			}
		}

		foreach ( $periods as &$period ) {
			$period['revenue']    = round( $period['revenue'], 2 );
			$period['net_profit'] = round( $period['net_profit'], 2 );
		}
		unset( $period );

		return array_values( $periods );
	}

	/**
	 * Gross profit per product (before courier charges and fees).
	 *
	 * @param DateRange $range   Dates.
	 * @param string    $orderby `profit` for the most profitable, `loss` for loss makers only.
	 * @param int       $limit   Number of products.
	 * @return array<int, array<string, mixed>>
	 */
	public static function products( DateRange $range, string $orderby, int $limit ): array {
		global $wpdb;

		list( $where, $args ) = self::where( $range, 'o' );
		$table                = Installer::table();
		$items_table          = Installer::items_table();
		$having               = 'loss' === $orderby ? 'HAVING profit < 0' : '';
		$order                = 'loss' === $orderby ? 'ASC' : 'DESC';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.product_id,
					SUM( CASE WHEN o.order_status = %s THEN 0 ELSE i.qty END ) AS qty,
					COUNT( DISTINCT CASE WHEN o.order_status = %s THEN i.order_id END ) AS returned,
					SUM( i.revenue ) AS revenue,
					SUM( i.cost ) AS cost,
					SUM( i.revenue ) - SUM( i.cost ) AS profit,
					MAX( i.missing_cost ) AS missing_cost
				FROM {$items_table} i
				INNER JOIN {$table} o ON o.order_id = i.order_id
				WHERE {$where}
				GROUP BY i.product_id
				{$having}
				ORDER BY profit {$order}
				LIMIT %d",
				array_merge( array( ReturnStatus::STATUS, ReturnStatus::STATUS ), $args, array( $limit ) )
			),
			ARRAY_A
		);

		return array_map(
			static function ( $row ) {
				$product = wc_get_product( (int) $row['product_id'] );
				return array(
					'id'           => (int) $row['product_id'],
					'name'         => $product ? $product->get_name() : __( 'Deleted product', 'munafa' ),
					'edit_url'     => $product ? get_edit_post_link( $product->get_id(), 'raw' ) : '',
					'qty'          => (int) $row['qty'],
					'returned'     => (int) $row['returned'],
					'revenue'      => round( (float) $row['revenue'], 2 ),
					'cost'         => round( (float) $row['cost'], 2 ),
					'profit'       => round( (float) $row['profit'], 2 ),
					'missing_cost' => (bool) $row['missing_cost'],
				);
			},
			$rows
		);
	}

	/**
	 * Returns per district and per reason.
	 *
	 * @param DateRange $range Dates.
	 * @return array{districts: array, reasons: array}
	 */
	public static function returns( DateRange $range ): array {
		global $wpdb;

		list( $where, $args ) = self::where( $range, 'o' );
		$table                = Installer::table();

		$areas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.delivery_country AS country, o.delivery_state AS state,
					COUNT(*) AS orders,
					SUM( o.order_status = %s ) AS returned,
					SUM( CASE WHEN o.order_status = %s THEN -o.net_profit ELSE 0 END ) AS loss
				FROM {$table} o
				WHERE {$where}
				GROUP BY o.delivery_country, o.delivery_state
				ORDER BY returned DESC, orders DESC",
				array_merge( array( ReturnStatus::STATUS, ReturnStatus::STATUS ), $args )
			),
			ARRAY_A
		);

		$reasons = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.return_reason AS reason, COUNT(*) AS returned, SUM( -o.net_profit ) AS loss
				FROM {$table} o
				WHERE {$where} AND o.order_status = %s
				GROUP BY o.return_reason
				ORDER BY returned DESC",
				array_merge( $args, array( ReturnStatus::STATUS ) )
			),
			ARRAY_A
		);

		$reason_names = Settings::return_reasons();

		return array(
			'areas'   => array_map(
				static function ( $row ) {
					$orders = (int) $row['orders'];
					return array(
						'country'     => $row['country'],
						'state'       => $row['state'],
						'name'        => self::area_name( $row['country'], $row['state'] ),
						'orders'      => $orders,
						'returned'    => (int) $row['returned'],
						'return_rate' => $orders > 0 ? round( (int) $row['returned'] / $orders * 100, 1 ) : 0.0,
						'loss'        => round( (float) $row['loss'], 2 ),
					);
				},
				$areas
			),
			'reasons' => array_map(
				static function ( $row ) use ( $reason_names ) {
					if ( '' === $row['reason'] ) {
						$name = __( 'Not set', 'munafa' );
					} else {
						// A reason deleted from the settings still shows its stored key.
						$name = $reason_names[ $row['reason'] ] ?? $row['reason'];
					}
					return array(
						'reason'   => $row['reason'],
						'name'     => $name,
						'returned' => (int) $row['returned'],
						'loss'     => round( (float) $row['loss'], 2 ),
					);
				},
				$reasons
			),
		);
	}

	/**
	 * Readable name of a delivery area, e.g. "Dhaka, Bangladesh".
	 *
	 * @param string $country Country code.
	 * @param string $state   State code.
	 */
	private static function area_name( string $country, string $state ): string {
		if ( '' === $country ) {
			return __( 'Unknown', 'munafa' );
		}

		$countries    = WC()->countries->get_countries();
		$country_name = $countries[ $country ] ?? $country;
		if ( '' === $state ) {
			return $country_name;
		}

		$states = (array) WC()->countries->get_states( $country );
		return sprintf(
			/* translators: 1: state or district, 2: country */
			__( '%1$s, %2$s', 'munafa' ),
			$states[ $state ] ?? $state,
			$country_name
		);
	}

	/**
	 * WHERE clause for the date range and report statuses, with its prepare() arguments.
	 *
	 * @param DateRange $range Dates.
	 * @param string    $alias Order profit table alias.
	 * @return array{0: string, 1: array}
	 */
	private static function where( DateRange $range, string $alias ): array {
		$statuses = self::statuses();
		// A filter can empty the list; "IN ()" is invalid SQL, so match nothing instead.
		$status_sql = $statuses
			? "{$alias}.order_status IN ( " . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ' )'
			: '1 = 0';

		return array(
			"{$alias}.order_date_gmt >= %s AND {$alias}.order_date_gmt < %s AND {$status_sql}",
			array_merge( array( $range->gmt_start(), $range->gmt_end() ), $statuses ),
		);
	}

	/**
	 * First day of the week a day belongs to, using the site's "Week starts on" setting.
	 *
	 * @param string $day `Y-m-d`.
	 */
	private static function week_start( string $day ): string {
		$date       = new \DateTimeImmutable( $day );
		$start_of   = (int) get_option( 'start_of_week', 1 );
		$days_since = ( (int) $date->format( 'w' ) - $start_of + 7 ) % 7;
		return $date->modify( "-{$days_since} days" )->format( 'Y-m-d' );
	}
}
