<?php
/**
 * 割引ルール1段を表す値オブジェクト。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 割引ルール1段（しきい値と割引額の組）を表す不変オブジェクト。
 */
class WCD_Rule {

	/**
	 * しきい値金額（円）。
	 *
	 * @var int
	 */
	private $threshold;

	/**
	 * 割引額（円）。
	 *
	 * @var int
	 */
	private $amount;

	/**
	 * コンストラクタ。
	 *
	 * @param mixed $threshold しきい値金額。0より大きい整数値であること。
	 * @param mixed $amount    割引額。0より大きい整数値であること。
	 * @throws InvalidArgumentException しきい値または割引額が数値でない、または0以下の場合。
	 */
	public function __construct( $threshold, $amount ) {
		if ( ! is_numeric( $threshold ) || ! is_numeric( $amount ) ) {
			throw new InvalidArgumentException( 'threshold and amount must be numeric.' );
		}

		$threshold = (int) $threshold;
		$amount    = (int) $amount;

		if ( $threshold <= 0 || $amount <= 0 ) {
			throw new InvalidArgumentException( 'threshold and amount must be positive integers.' );
		}

		$this->threshold = $threshold;
		$this->amount    = $amount;
	}

	/**
	 * しきい値金額を返す。
	 *
	 * @return int
	 */
	public function get_threshold() {
		return $this->threshold;
	}

	/**
	 * 割引額を返す。
	 *
	 * @return int
	 */
	public function get_amount() {
		return $this->amount;
	}
}
