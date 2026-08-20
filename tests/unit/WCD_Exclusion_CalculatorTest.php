<?php
/**
 * WCD_Exclusion_Calculator のテスト。
 *
 * @package Welcart_Cart_Discount
 */

use PHPUnit\Framework\TestCase;

class WCD_Exclusion_CalculatorTest extends TestCase {

	public function test_excluded_amount_returns_zero_when_no_excluded_categories() {
		$rows = array(
			array(
				'amount'       => 3000.0,
				'category_ids' => array( 5 ),
			),
		);

		$this->assertSame( 0.0, WCD_Exclusion_Calculator::excluded_amount( $rows, array() ) );
	}

	public function test_excluded_amount_sums_only_matching_rows() {
		$rows = array(
			array(
				'amount'       => 3000.0,
				'category_ids' => array( 5 ),
			),
			array(
				'amount'       => 7000.0,
				'category_ids' => array( 9 ),
			),
		);

		$this->assertSame( 3000.0, WCD_Exclusion_Calculator::excluded_amount( $rows, array( 5 ) ) );
	}

	public function test_excluded_amount_sums_all_rows_when_all_match() {
		$rows = array(
			array(
				'amount'       => 3000.0,
				'category_ids' => array( 5 ),
			),
			array(
				'amount'       => 7000.0,
				'category_ids' => array( 5 ),
			),
		);

		$this->assertSame( 10000.0, WCD_Exclusion_Calculator::excluded_amount( $rows, array( 5 ) ) );
	}

	public function test_excluded_amount_matches_row_belonging_to_multiple_categories() {
		$rows = array(
			array(
				'amount'       => 3000.0,
				'category_ids' => array( 5, 9 ),
			),
		);

		$this->assertSame( 3000.0, WCD_Exclusion_Calculator::excluded_amount( $rows, array( 9 ) ) );
	}

	public function test_excluded_amount_ignores_row_with_empty_category_ids() {
		$rows = array(
			array(
				'amount'       => 3000.0,
				'category_ids' => array(),
			),
		);

		$this->assertSame( 0.0, WCD_Exclusion_Calculator::excluded_amount( $rows, array( 5 ) ) );
	}

	public function test_is_rank_excluded_matches_guest() {
		$this->assertTrue( WCD_Exclusion_Calculator::is_rank_excluded( 'guest', array( 'guest' ) ) );
	}

	public function test_is_rank_excluded_guest_not_in_list() {
		$this->assertFalse( WCD_Exclusion_Calculator::is_rank_excluded( 'guest', array( 2 ) ) );
	}

	public function test_is_rank_excluded_matches_int_rank() {
		$this->assertTrue( WCD_Exclusion_Calculator::is_rank_excluded( 2, array( 2, 3 ) ) );
	}

	public function test_is_rank_excluded_int_rank_not_in_list() {
		$this->assertFalse( WCD_Exclusion_Calculator::is_rank_excluded( 1, array( 2, 3 ) ) );
	}

	public function test_is_rank_excluded_does_not_confuse_zero_and_guest() {
		$this->assertFalse( WCD_Exclusion_Calculator::is_rank_excluded( 0, array( 'guest' ) ) );
		$this->assertFalse( WCD_Exclusion_Calculator::is_rank_excluded( 'guest', array( 0 ) ) );
	}
}
