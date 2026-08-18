<?php
/**
 * WCD_Settings::normalize() のテスト。
 *
 * @package Welcart_Cart_Discount
 */

use PHPUnit\Framework\TestCase;

class WCD_SettingsTest extends TestCase {

	public function test_casts_values_to_absint() {
		$result = WCD_Settings::normalize(
			array(
				array(
					'threshold' => '10000',
					'amount'    => '500',
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'threshold' => 10000,
					'amount'    => 500,
				),
			),
			$result
		);
	}

	public function test_discards_non_positive_rows() {
		$result = WCD_Settings::normalize(
			array(
				array(
					'threshold' => 0,
					'amount'    => 500,
				),
				array(
					'threshold' => 10000,
					'amount'    => 0,
				),
				array(
					'threshold' => -1,
					'amount'    => 500,
				),
			)
		);

		$this->assertSame( array(), $result );
	}

	public function test_ignores_malformed_rows() {
		$result = WCD_Settings::normalize(
			array(
				'not-an-array',
				array( 'threshold' => 10000 ),
				array(
					'threshold' => 10000,
					'amount'    => 500,
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'threshold' => 10000,
					'amount'    => 500,
				),
			),
			$result
		);
	}

	public function test_duplicate_threshold_last_wins() {
		$result = WCD_Settings::normalize(
			array(
				array(
					'threshold' => 10000,
					'amount'    => 500,
				),
				array(
					'threshold' => 10000,
					'amount'    => 800,
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'threshold' => 10000,
					'amount'    => 800,
				),
			),
			$result
		);
	}

	public function test_sorted_by_threshold_ascending() {
		$result = WCD_Settings::normalize(
			array(
				array(
					'threshold' => 30000,
					'amount'    => 2000,
				),
				array(
					'threshold' => 10000,
					'amount'    => 500,
				),
			)
		);

		$this->assertSame( 10000, $result[0]['threshold'] );
		$this->assertSame( 30000, $result[1]['threshold'] );
	}
}
