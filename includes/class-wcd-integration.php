<?php
/**
 * Welcart フックへの接続を担うアダプタ。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Welcart のフックと WCD_Calculator を接続する薄いアダプタ層。
 */
class WCD_Integration {

	/**
	 * usces_order_discount フィルタ。割引額を注入する。
	 *
	 * Welcart は割引額を負値で扱うため、既存の割引額から算出した割引額を減算する。
	 * このフィルタは Welcart 側（classes/usceshop.class.php:8318, classes/tax.class.php:368）
	 * から `apply_filters( 'usces_order_discount', $discount, $cart )` として2引数で
	 * 呼ばれるため、$cart はここで取得し直さずフィルタ引数をそのまま使う。
	 *
	 * @param float $discount 既存の割引額（負値、または0）。
	 * @param array $cart     カート情報。
	 * @return float 加算後の割引額（負値）。
	 */
	public static function filter_order_discount( $discount, $cart ) {
		return $discount - self::calculate_amount( $cart );
	}

	/**
	 * usces_filter_order_discount_recalculation フィルタ。受注編集時の再計算。
	 *
	 * この $cart は管理画面の受注編集フォームから組み立てられた配列
	 * （post_id/price/quantity のみを持つ0始まり配列）であり、フロントの
	 * セッションカートではない。フィルタ引数として受け取った $cart をそのまま
	 * 使うこと。$usces->cart->get_cart() に差し替えると、編集対象の受注と
	 * 無関係な管理者自身のセッションカートを参照してしまうため行わない。
	 *
	 * @param float  $discount  既存の割引額。
	 * @param array  $cart      受注編集フォームから組み立てられたカート相当の配列。
	 * @param string $condition 再計算の条件。
	 * @param int    $order_id  受注ID。
	 * @return float
	 */
	public static function filter_order_recalculation( $discount, $cart, $condition, $order_id ) {
		return $discount - self::calculate_amount( $cart );
	}

	/**
	 * 現在の設定とカートから割引額を計算する。独自フィルタの適用点でもある。
	 *
	 * @param array $cart カート情報。
	 * @return float 割引額（正の値）。
	 */
	private static function calculate_amount( $cart ) {
		global $usces;

		$subtotal = ( isset( $usces ) && is_object( $usces ) )
			? (float) $usces->get_total_price( $cart )
			: 0.0;

		/**
		 * 割引判定の対象となる小計を変更する。第二段階の商品カテゴリ除外で使用する。
		 *
		 * @param float $subtotal 小計。
		 * @param array $cart     カート情報。
		 */
		$subtotal = apply_filters( 'wcd_eligible_subtotal', $subtotal, $cart );

		/**
		 * 適用可能な割引ルールを変更する。第二段階の会員ランク除外で使用する。
		 *
		 * @param WCD_Rule[] $rules 割引ルールの配列。
		 * @param array      $cart  カート情報。
		 */
		$rules = apply_filters( 'wcd_available_rules', WCD_Settings::get_rules(), $cart );

		return WCD_Calculator::calculate( $subtotal, $rules );
	}
}
