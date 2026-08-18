<?php
/**
 * 割引計算コア。WordPress・Welcart に非依存の純粋な計算ロジック。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * カート小計と割引ルール群から適用すべき割引額を計算する。
 */
class WCD_Calculator {

	/**
	 * 小計と割引ルール群から割引額（正の値）を計算する。
	 *
	 * 到達した最上位（しきい値が最大）の1段のみを適用する。
	 * 割引額はしきい値超過分を防ぐため小計でクランプする。
	 *
	 * @param float      $subtotal カート小計。
	 * @param WCD_Rule[] $rules    割引ルールの配列。
	 * @return float 割引額（正の値）。0以上。
	 */
	public static function calculate( $subtotal, array $rules ) {
		$sorted = $rules;

		usort(
			$sorted,
			function ( WCD_Rule $a, WCD_Rule $b ) {
				return $b->get_threshold() <=> $a->get_threshold();
			}
		);

		foreach ( $sorted as $rule ) {
			if ( $subtotal >= $rule->get_threshold() ) {
				return (float) min( $rule->get_amount(), $subtotal );
			}
		}

		return 0.0;
	}
}
