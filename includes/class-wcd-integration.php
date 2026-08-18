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
	 * 割引額を注入する usces_order_discount フィルタ用コールバック。
	 *
	 * Welcart は割引額を負値で扱うため、既存の割引額から算出した割引額を減算する。
	 * このフィルタは Welcart 側（classes/usceshop.class.php:8318, classes/tax.class.php:368）
	 * から `apply_filters( 'usces_order_discount', $discount, $cart )` として2引数で
	 * 呼ばれるため、$cart はここで取得し直さずフィルタ引数をそのまま使う。
	 *
	 * @param float $discount 既存の割引額（負値、または0）.
	 * @param array $cart     カート情報.
	 * @return float 加算後の割引額（負値）。
	 */
	public static function filter_order_discount( $discount, $cart ) {
		return $discount - self::calculate_amount( $cart );
	}

	/**
	 * 受注編集時に再計算する usces_filter_order_discount_recalculation フィルタ用コールバック。
	 *
	 * この $cart は管理画面の受注編集フォームから組み立てられた配列
	 * （post_id/price/quantity のみを持つ0始まり配列）であり、フロントの
	 * セッションカートではない。フィルタ引数として受け取った $cart をそのまま
	 * 使うこと。$usces->cart->get_cart() に差し替えると、編集対象の受注と
	 * 無関係な管理者自身のセッションカートを参照してしまうため行わない。
	 *
	 * $condition・$order_id は Welcart 側のフィルタシグネチャに合わせるためだけに
	 * 受け取っており、本コールバックの計算では使用しない。
	 *
	 * 【$discount の意味が usces_order_discount とは異なる点に注意】
	 * `usces_order_discount` は Welcart が毎回ゼロから計算し直す割引額を渡すため、
	 * 「既存の割引額から加算」で正しい（確定した仕様判断の「既存割引との関係」を参照）。
	 * 一方こちらのフックは `functions/item_post.php:2805` の
	 * `usces_order_recalculation()` から呼ばれ、$discount の実体は
	 * `$_POST['discount']`、つまり受注編集画面の「Campaign discount」欄に
	 * 現在表示されている値そのもの（＝前回保存時に本プラグインが書き込んだ
	 * 割引額を含む）である。実機検証で「1回目の再計算後に -2,000 → -2,500 と
	 * 二重計上される」不具合を確認したため、$discount に対して加算するのではなく
	 * 本プラグインの計算結果で置き換える。`change_taxrate=change` 時に Welcart
	 * 自身のキャンペーン割引が $discount に入るケースでは上書きにより
	 * その値が失われるが、二重計上という常に再現するバグを優先して回避する
	 * トレードオフとして許容する（docs/design-notes.md に記録）。
	 *
	 * @param float  $discount  受注編集フォームの割引額欄の現在値（本プラグイン自身の前回出力を含みうる）.
	 * @param array  $cart      受注編集フォームから組み立てられたカート相当の配列.
	 * @param string $condition 再計算の条件.
	 * @param int    $order_id  受注ID.
	 * @return float
	 */
	public static function filter_order_recalculation( $discount, $cart, $condition, $order_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Welcart 側のフィルタシグネチャ（4引数）に合わせるため受け取る。
		return -self::calculate_amount( $cart );
	}

	/**
	 * 割引行をカート表に挿入する usces_filter_cart_table_footer フィルタ用コールバック。
	 *
	 * Welcart 側の呼び出しは `apply_filters( 'usces_filter_cart_table_footer', $cart_table_footer )`
	 * であり、引数は $cart_table_footer の1つのみで $cart は渡されない。
	 * そのため $cart はここで global $usces; $usces->cart->get_cart(); により
	 * 明示的に取得する。
	 *
	 * @param string $footer カート表フッターの HTML.
	 * @return string
	 */
	public static function filter_cart_table_footer( $footer ) {
		global $usces;

		if ( ! isset( $usces ) || ! is_object( $usces ) ) {
			return $footer;
		}

		$cart   = $usces->cart->get_cart();
		$amount = self::calculate_amount( $cart );

		if ( $amount <= 0 ) {
			return $footer;
		}

		$needle = '</tfoot>';
		if ( false === strpos( $footer, $needle ) ) {
			return $footer;
		}

		$subtotal = (float) $usces->get_total_price( $cart );
		$row      = sprintf(
			'<tr class="wcd-discount-row"><th>%1$s</th><td>-&yen;%2$s</td></tr>' .
			'<tr class="wcd-discounted-total-row"><th>%3$s</th><td>&yen;%4$s</td></tr>',
			esc_html__( '自動割引', 'welcart-cart-discount' ),
			esc_html( number_format( $amount ) ),
			esc_html__( '割引後合計', 'welcart-cart-discount' ),
			esc_html( number_format( max( 0, $subtotal - $amount ) ) )
		);

		return str_replace( $needle, $row . $needle, $footer );
	}

	/**
	 * 現在の設定とカートから割引額を計算する。独自フィルタの適用点でもある。
	 *
	 * @param array $cart カート情報.
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
