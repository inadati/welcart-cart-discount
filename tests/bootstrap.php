<?php
/**
 * PHPUnit ブートストラップ。WordPress を起動せず、
 * テスト対象が依存する最小限の WordPress 関数のみを代替実装する。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! function_exists( 'absint' ) ) {
	/**
	 * WordPress の absint() の簡易代替。
	 *
	 * @param mixed $value 変換対象。
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../includes/class-wcd-rule.php';
require_once __DIR__ . '/../includes/class-wcd-cart-row-builder.php';
require_once __DIR__ . '/../includes/class-wcd-calculator.php';
require_once __DIR__ . '/../includes/class-wcd-settings.php';
require_once __DIR__ . '/../includes/class-wcd-exclusion-calculator.php';
