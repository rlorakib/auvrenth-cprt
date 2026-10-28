<?php
/**
 * Report date range.
 *
 * @package Munafa
 */

namespace Munafa\Reports;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Whole days in the site's timezone, converted to the GMT range stored in the Munafa tables.
 */
final class DateRange {

	/**
	 * Default number of days when no start date is given.
	 */
	const DEFAULT_DAYS = 30;

	/**
	 * First day, 00:00 in site time.
	 *
	 * @var DateTimeImmutable
	 */
	private $start;

	/**
	 * Day after the last day, 00:00 in site time (exclusive end).
	 *
	 * @var DateTimeImmutable
	 */
	private $end;

	/**
	 * Constructor.
	 *
	 * @param DateTimeImmutable $start First day, 00:00 site time.
	 * @param DateTimeImmutable $end   Day after the last day, 00:00 site time.
	 */
	private function __construct( DateTimeImmutable $start, DateTimeImmutable $end ) {
		$this->start = $start;
		$this->end   = $end;
	}

	/**
	 * Build a range from `Y-m-d` dates. Missing dates default to the last 30 days.
	 *
	 * @param string|null $after  First day, inclusive.
	 * @param string|null $before Last day, inclusive.
	 */
	public static function from_dates( ?string $after, ?string $before ): self {
		$timezone = wp_timezone();
		$today    = new DateTimeImmutable( 'today', $timezone );

		$last  = $before ? self::parse( $before, $timezone ) : $today;
		$first = $after ? self::parse( $after, $timezone ) : $last->sub( new DateInterval( 'P' . ( self::DEFAULT_DAYS - 1 ) . 'D' ) );

		if ( $first > $last ) {
			list( $first, $last ) = array( $last, $first );
		}

		return new self( $first, $last->add( new DateInterval( 'P1D' ) ) );
	}

	/**
	 * The range of the same length that ends where this one starts.
	 */
	public function previous(): self {
		$length = new DateInterval( 'P' . $this->days() . 'D' );
		return new self( $this->start->sub( $length ), $this->start );
	}

	/**
	 * Number of days in the range.
	 */
	public function days(): int {
		return (int) $this->start->diff( $this->end )->days;
	}

	/**
	 * Inclusive GMT start, as stored in `order_date_gmt`.
	 */
	public function gmt_start(): string {
		return $this->start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Exclusive GMT end.
	 */
	public function gmt_end(): string {
		return $this->end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Every day in the range as `Y-m-d` (site time).
	 *
	 * @return string[]
	 */
	public function each_day(): array {
		$days = array();
		for ( $day = $this->start; $day < $this->end; $day = $day->add( new DateInterval( 'P1D' ) ) ) {
			$days[] = $day->format( 'Y-m-d' );
		}
		return $days;
	}

	/**
	 * First and last day as `Y-m-d`, for the API response.
	 *
	 * @return array{after: string, before: string}
	 */
	public function to_array(): array {
		return array(
			'after'  => $this->start->format( 'Y-m-d' ),
			'before' => $this->end->sub( new DateInterval( 'P1D' ) )->format( 'Y-m-d' ),
		);
	}

	/**
	 * Parse a `Y-m-d` date as 00:00 in the given timezone.
	 *
	 * @param string       $date     Date.
	 * @param DateTimeZone $timezone Site timezone.
	 */
	private static function parse( string $date, DateTimeZone $timezone ): DateTimeImmutable {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );
		return $parsed ? $parsed : new DateTimeImmutable( 'today', $timezone );
	}
}
