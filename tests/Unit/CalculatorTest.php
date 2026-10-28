<?php
/**
 * Tests for the profit calculator.
 *
 * @package Munafa
 */

namespace Munafa\Tests\Unit;

use Munafa\Profit\Calculator;
use PHPUnit\Framework\TestCase;

/**
 * Calculator tests.
 */
final class CalculatorTest extends TestCase {

	/**
	 * Calculate an order on top of typical COD settings: ৳60 delivery, 1% COD charge, 50% return charge.
	 *
	 * @param array $order Order numbers to change.
	 */
	private function calculate( array $order ): array {
		return ( new Calculator() )->calculate(
			array_merge(
				array(
					'total'          => 0,
					'item_costs'     => array(),
					'is_cod'         => true,
					'fee_percent'    => 1,
					'courier_charge' => 60,
					'return_percent' => 50,
					'packaging_cost' => 0,
				),
				$order
			)
		);
	}

	/**
	 * Delivered COD order.
	 */
	public function test_cod_order(): void {
		$result = $this->calculate(
			array(
				'total'      => 1060,
				'item_costs' => array( 400, 200 ),
			)
		);

		$this->assertSame( 1060.0, $result['revenue'] );
		$this->assertSame( 600.0, $result['product_cost'] );
		$this->assertSame( 60.0, $result['courier_cost'] );
		$this->assertSame( 10.6, $result['cod_fee'] );
		$this->assertSame( 0.0, $result['gateway_fee'] );
		$this->assertSame( 389.4, $result['net_profit'] );
		$this->assertFalse( $result['missing_cost'] );
	}

	/**
	 * Online paid order with tax and a different zone charge.
	 */
	public function test_online_order(): void {
		$result = $this->calculate(
			array(
				'total'          => 2150,
				'tax'            => 150,
				'item_costs'     => array( 1200 ),
				'is_cod'         => false,
				'fee_percent'    => 2,
				'courier_charge' => 120,
			)
		);

		$this->assertSame( 2000.0, $result['revenue'] );
		$this->assertSame( 120.0, $result['courier_cost'] );
		$this->assertSame( 0.0, $result['cod_fee'] );
		$this->assertSame( 43.0, $result['gateway_fee'] );
		$this->assertSame( 637.0, $result['net_profit'] );
	}

	/**
	 * Refunds reduce revenue.
	 */
	public function test_refund_reduces_revenue(): void {
		$result = $this->calculate(
			array(
				'total'      => 1000,
				'refunded'   => 300,
				'item_costs' => array( 350 ),
			)
		);

		$this->assertSame( 700.0, $result['revenue'] );
		$this->assertSame( 280.0, $result['net_profit'] );
	}

	/**
	 * Items without a cost are flagged and counted as zero.
	 */
	public function test_missing_cost_is_flagged(): void {
		$result = $this->calculate(
			array(
				'total'      => 500,
				'item_costs' => array( 200, null ),
			)
		);

		$this->assertTrue( $result['missing_cost'] );
		$this->assertSame( 200.0, $result['product_cost'] );
	}

	/**
	 * A free order still pays the courier but no percentage fee.
	 */
	public function test_zero_total_order(): void {
		$result = $this->calculate(
			array(
				'item_costs'     => array( 150 ),
				'courier_charge' => 120,
			)
		);

		$this->assertSame( 0.0, $result['cod_fee'] );
		$this->assertSame( -270.0, $result['net_profit'] );
	}

	/**
	 * A returned COD order earns nothing and loses the courier charge both ways.
	 */
	public function test_returned_cod_order(): void {
		$result = $this->calculate(
			array(
				'total'       => 1000,
				'item_costs'  => array( 500, null ),
				'is_returned' => true,
			)
		);

		$this->assertSame( 0.0, $result['revenue'] );
		$this->assertSame( 0.0, $result['product_cost'] );
		$this->assertSame( 60.0, $result['courier_cost'] );
		$this->assertSame( 0.0, $result['cod_fee'] );
		$this->assertSame( 30.0, $result['return_cost'] );
		$this->assertSame( -90.0, $result['net_profit'] );
		$this->assertFalse( $result['missing_cost'] );
	}

	/**
	 * A returned online order also loses the payment fee.
	 */
	public function test_returned_online_order_keeps_gateway_fee(): void {
		$result = $this->calculate(
			array(
				'total'          => 1000,
				'item_costs'     => array( 500 ),
				'is_cod'         => false,
				'fee_percent'    => 2,
				'courier_charge' => 120,
				'is_returned'    => true,
			)
		);

		$this->assertSame( 20.0, $result['gateway_fee'] );
		$this->assertSame( 60.0, $result['return_cost'] );
		$this->assertSame( -200.0, $result['net_profit'] );
	}

	/**
	 * The return charge follows the order's own courier charge and the zone's percentage.
	 */
	public function test_return_charge_uses_zone_percent(): void {
		$result = $this->calculate(
			array(
				'total'          => 1000,
				'item_costs'     => array( 500 ),
				'courier_charge' => 150,
				'return_percent' => 100,
				'is_returned'    => true,
			)
		);

		$this->assertSame( 150.0, $result['return_cost'] );
		$this->assertSame( -300.0, $result['net_profit'] );
	}

	/**
	 * Free delivery costs nothing.
	 */
	public function test_zero_courier_charge(): void {
		$result = $this->calculate(
			array(
				'total'          => 1000,
				'item_costs'     => array( 500 ),
				'courier_charge' => 0,
			)
		);

		$this->assertSame( 0.0, $result['courier_cost'] );
		$this->assertSame( 490.0, $result['net_profit'] );
	}

	/**
	 * Packaging is a cost on delivered and returned orders alike.
	 */
	public function test_packaging_on_every_order(): void {
		$order = array(
			'total'          => 1000,
			'item_costs'     => array( 500 ),
			'packaging_cost' => 15,
		);

		$delivered = $this->calculate( $order );
		$returned  = $this->calculate( array_merge( $order, array( 'is_returned' => true ) ) );

		$this->assertSame( 15.0, $delivered['packaging_cost'] );
		$this->assertSame( 415.0, $delivered['net_profit'] );
		$this->assertSame( -105.0, $returned['net_profit'] );
	}
}
