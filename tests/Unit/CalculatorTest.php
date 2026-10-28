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
	 */
	private function calculator(): Calculator {
		return new Calculator(
			array(
				'courier_inside_dhaka'  => 60,
				'courier_outside_dhaka' => 120,
				'cod_fee_percent'       => 1,
				'gateway_fee_percent'   => 2,
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
	 * Return cost is subtracted from profit.
	 */
	public function test_return_cost_is_subtracted(): void {
		$result = $this->calculator()->calculate(
			array(
				'total'        => 1000,
				'item_costs'   => array( 500 ),
				'is_cod'       => true,
				'inside_dhaka' => true,
				'return_cost'  => 80,
			)
		);

		$this->assertSame( 350.0, $result['net_profit'] );
	}
}
