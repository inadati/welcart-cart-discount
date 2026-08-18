<?php
/**
 * WCD_Rule のテスト。
 *
 * @package Welcart_Cart_Discount
 */

use PHPUnit\Framework\TestCase;

class WCD_RuleTest extends TestCase {

	public function test_holds_threshold_and_amount() {
		$rule = new WCD_Rule( 10000, 500 );

		$this->assertSame( 10000, $rule->get_threshold() );
		$this->assertSame( 500, $rule->get_amount() );
	}

	public function test_rejects_zero_threshold() {
		$this->expectException( InvalidArgumentException::class );

		new WCD_Rule( 0, 500 );
	}

	public function test_rejects_negative_amount() {
		$this->expectException( InvalidArgumentException::class );

		new WCD_Rule( 10000, -500 );
	}

	public function test_rejects_non_numeric_threshold() {
		$this->expectException( InvalidArgumentException::class );

		new WCD_Rule( 'abc', 500 );
	}
}
