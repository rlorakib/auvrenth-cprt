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
	 * Calculator with typical BD settings.
	 *
	 * @param array $overrides Settings to change.
	 */
	private function calculator( array $overrides = array() ): Calculator {
		return new Calculator(
			array_merge(
				array(
					'courier_inside_dhaka'  => 60,
					'courier_outside_dhaka' => 120,
					'cod_fee_percent'       => 1,
					'gateway_fee_percent'   => 2,
					'return_charge_percent' => 50,
					'packaging_cost'        => 0,
				),
				$overrides
			)
		);
	}

	/**
	 * COD order delivered inside Dhaka.
	 */
	public function test_cod_order_inside_dhaka(): void {
		$result = $this->calculator()->calculate(
			array(
				'total'        => 1060,
				'item_costs'   => array( 400, 200 ),
				'is_cod'       => true,
				'inside_dhaka' => true,
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
	 * Online paid order delivered outside Dhaka, with tax.
	 */
	public function test_online_order_outside_dhaka(): void {
		$result = $this->calculator()->calculate(
			array(
				'total'        => 2150,
				'tax'          => 150,
				'item_costs'   => array( 1200 ),
				'is_cod'       => false,
				'inside_dhaka' => false,
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
		$result = $this->calculator()->calculate(
			array(
				'total'        => 1000,
				'refunded'     => 300,
				'item_costs'   => array( 350 ),
				'is_cod'       => true,
				'inside_dhaka' => true,
			)
		);

		$this->assertSame( 700.0, $result['revenue'] );
		$this->assertSame( 280.0, $result['net_profit'] );
	}

	/**
	 * Items without a cost are flagged and counted as zero.
	 */
	public function test_missing_cost_is_flagged(): void {
		$result = $this->calculator()->calculate(
			array(
				'total'        => 500,
				'item_costs'   => array( 200, null ),
				'is_cod'       => true,
				'inside_dhaka' => true,
			)
		);

		$this->assertTrue( $result['missing_cost'] );
		$this->assertSame( 200.0, $result['product_cost'] );
	}

	/**
	 * A free order still pays the courier but no percentage fee.
	 */
	public function test_zero_total_order(): void {
		$result = $this->calculator()->calculate(
			array(
				'total'        => 0,
				'item_costs'   => array( 150 ),
				'is_cod'       => true,
				'inside_dhaka' => false,
			)
		);

		$this->assertSame( 0.0, $result['cod_fee'] );
		$this->assertSame( -270.0, $result['net_profit'] );
	}

	/**
	 * A returned COD order earns nothing and loses the courier charge both ways.
	 */
	public function test_returned_cod_order(): void {
		$result = $this->calculator()->calculate(
			array(
				'total'        => 1000,
				'item_costs'   => array( 500, null ),
				'is_cod'       => true,
				'inside_dhaka' => true,
				'is_returned'  => true,
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
		$result = $this->calculator()->calculate(
			array(
				'total'        => 1000,
				'item_costs'   => array( 500 ),
				'is_cod'       => false,
				'inside_dhaka' => false,
				'is_returned'  => true,
			)
		);

		$this->assertSame( 20.0, $result['gateway_fee'] );
		$this->assertSame( 60.0, $result['return_cost'] );
		$this->assertSame( -200.0, $result['net_profit'] );
	}

	/**
	 * An order's own courier charge replaces the default and drives the return charge.
	 */
	public function test_courier_override(): void {
		$result = $this->calculator()->calculate(
			array(
				'total'            => 1000,
				'item_costs'       => array( 500 ),
				'is_cod'           => true,
				'inside_dhaka'     => true,
				'is_returned'      => true,
				'courier_override' => 150,
			)
		);

		$this->assertSame( 150.0, $result['courier_cost'] );
		$this->assertSame( 75.0, $result['return_cost'] );
		$this->assertSame( -225.0, $result['net_profit'] );
	}

	/**
	 * A zero courier override means free delivery, not "use the default".
	 */
	public function test_zero_courier_override(): void {
		$result = $this->calculator()->calculate(
			array(
				'total'            => 1000,
				'item_costs'       => array( 500 ),
				'is_cod'           => true,
				'inside_dhaka'     => true,
				'courier_override' => 0,
			)
		);

		$this->assertSame( 0.0, $result['courier_cost'] );
		$this->assertSame( 490.0, $result['net_profit'] );
	}

	/**
	 * Packaging is a cost on delivered and returned orders alike.
	 */
	public function test_packaging_on_every_order(): void {
		$calculator = $this->calculator( array( 'packaging_cost' => 15 ) );
		$order      = array(
			'total'        => 1000,
			'item_costs'   => array( 500 ),
			'is_cod'       => true,
			'inside_dhaka' => true,
		);

		$delivered = $calculator->calculate( $order );
		$returned  = $calculator->calculate( array_merge( $order, array( 'is_returned' => true ) ) );

		$this->assertSame( 15.0, $delivered['packaging_cost'] );
		$this->assertSame( 415.0, $delivered['net_profit'] );
		$this->assertSame( -105.0, $returned['net_profit'] );
	}
}
