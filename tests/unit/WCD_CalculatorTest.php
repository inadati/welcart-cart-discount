<?php
/**
 * WCD_Calculator のテスト。
 *
 * @package Welcart_Cart_Discount
 */

use PHPUnit\Framework\TestCase;

class WCD_CalculatorTest extends TestCase {

	/**
	 * @return WCD_Rule[]
	 */
	private function sample_rules() {
		return array(
			new WCD_Rule( 10000, 500 ),
			new WCD_Rule( 30000, 2000 ),
		);
	}

	public function test_below_threshold_returns_zero() {
		$this->assertSame( 0.0, WCD_Calculator::calculate( 9999, $this->sample_rules() ) );
	}

	public function test_boundary_value_is_inclusive() {
		$this->assertSame( 500.0, WCD_Calculator::calculate( 10000, $this->sample_rules() ) );
	}

	public function test_highest_reached_tier_only() {
		$this->assertSame( 2000.0, WCD_Calculator::calculate( 35000, $this->sample_rules() ) );
	}

	public function test_no_rules_returns_zero() {
		$this->assertSame( 0.0, WCD_Calculator::calculate( 50000, array() ) );
	}

	public function test_amount_exceeding_subtotal_is_clamped() {
		$rules = array( new WCD_Rule( 10000, 50000 ) );

		$this->assertSame( 10000.0, WCD_Calculator::calculate( 10000, $rules ) );
	}

	public function test_unsorted_rules_still_pick_correct_tier() {
		$rules = array(
			new WCD_Rule( 30000, 2000 ),
			new WCD_Rule( 10000, 500 ),
		);

		$this->assertSame( 2000.0, WCD_Calculator::calculate( 35000, $rules ) );
	}
}
