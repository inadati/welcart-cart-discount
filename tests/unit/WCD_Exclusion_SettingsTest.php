<?php
/**
 * WCD_Exclusion_Settings::normalize() のテスト。
 *
 * @package Welcart_Cart_Discount
 */

use PHPUnit\Framework\TestCase;

class WCD_Exclusion_SettingsTest extends TestCase {

	public function test_keeps_guest_as_string() {
		$result = WCD_Exclusion_Settings::normalize(
			array( 'ranks' => array( 'guest' ) ),
			array( 1, 2, 3, 4 )
		);

		$this->assertSame( array( 'guest' ), $result['ranks'] );
	}

	public function test_casts_rank_to_absint() {
		$result = WCD_Exclusion_Settings::normalize(
			array( 'ranks' => array( '2' ) ),
			array( 1, 2, 3, 4 )
		);

		$this->assertSame( array( 2 ), $result['ranks'] );
	}

	public function test_discards_unknown_rank_keys() {
		$result = WCD_Exclusion_Settings::normalize(
			array( 'ranks' => array( 2, 99 ) ),
			array( 1, 2, 3, 4 )
		);

		$this->assertSame( array( 2 ), $result['ranks'] );
	}

	public function test_discards_duplicate_ranks() {
		$result = WCD_Exclusion_Settings::normalize(
			array( 'ranks' => array( 2, 2, 'guest', 'guest' ) ),
			array( 1, 2, 3, 4 )
		);

		$this->assertSame( array( 2, 'guest' ), $result['ranks'] );
	}

	public function test_casts_category_to_absint_and_discards_non_positive() {
		$result = WCD_Exclusion_Settings::normalize(
			array( 'categories' => array( '5', 0, -1 ) ),
			array()
		);

		$this->assertSame( array( 5 ), $result['categories'] );
	}

	public function test_discards_duplicate_categories() {
		$result = WCD_Exclusion_Settings::normalize(
			array( 'categories' => array( 5, 5, 9 ) ),
			array()
		);

		$this->assertSame( array( 5, 9 ), $result['categories'] );
	}

	public function test_missing_keys_default_to_empty_arrays() {
		$result = WCD_Exclusion_Settings::normalize( array(), array( 1, 2 ) );

		$this->assertSame(
			array(
				'ranks'      => array(),
				'categories' => array(),
			),
			$result
		);
	}

	public function test_rank_zero_is_preserved_when_in_known_ranks() {
		$result = WCD_Exclusion_Settings::normalize(
			array( 'ranks' => array( 0 ) ),
			array( 0, 1, 2, 99 )
		);

		$this->assertSame( array( 0 ), $result['ranks'] );
	}

	public function test_rank_zero_is_discarded_when_not_in_known_ranks() {
		$result = WCD_Exclusion_Settings::normalize(
			array( 'ranks' => array( 0 ) ),
			array( 1, 2, 3, 4 )
		);

		$this->assertSame( array(), $result['ranks'] );
	}
}
