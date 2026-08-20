<?php
/**
 * 除外条件（会員ランク・商品カテゴリ）を Welcart のフックへ接続するアダプタ。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCD_Exclusion_Settings / WCD_Exclusion_Calculator と Welcart/WP の
 * データ取得を接続し、独自フィルタ2本（wcd_eligible_subtotal / wcd_available_rules）
 * に登録するコールバックを提供する。
 */
class WCD_Exclusion {

	/**
	 * 受注編集画面の再計算中、対象受注IDを一時的に保持する静的プロパティ。
	 *
	 * WCD_Integration::filter_order_recalculation() の実行中だけセットし、
	 * 終了時に解除する。この経路のセッション会員は操作中の管理者であり
	 * 受注の持ち主ではないため、ランク解決の分岐に使う。wcd_available_rules
	 * フィルタの引数は ($rules, $cart) の2つのみで $order_id を含まないため、
	 * フィルタ契約を変えずに文脈を受け渡す手段として用いる。
	 *
	 * @var int|null
	 */
	private static $recalculating_order_id = null;

	/**
	 * 受注編集画面の再計算中であることを通知する。
	 *
	 * WCD_Integration::filter_order_recalculation() の実行直前に呼ぶ。
	 *
	 * @param int $order_id 受注ID.
	 * @return void
	 */
	public static function begin_order_recalculation( $order_id ) {
		self::$recalculating_order_id = (int) $order_id;
	}

	/**
	 * 受注編集画面の再計算の文脈（begin_order_recalculation() で設定した状態）を解除する。
	 *
	 * WCD_Integration::filter_order_recalculation() の実行直後に呼ぶ。
	 *
	 * @return void
	 */
	public static function end_order_recalculation() {
		self::$recalculating_order_id = null;
	}

	/**
	 * カテゴリ除外を適用する wcd_eligible_subtotal フィルタ用コールバック。
	 *
	 * @param float $subtotal 小計.
	 * @param array $cart     カート情報. array<int, array{post_id?: int, price?: float, quantity?: float}>.
	 * @return float
	 */
	public static function filter_eligible_subtotal( $subtotal, $cart ) {
		$excluded_categories = WCD_Exclusion_Settings::get()['categories'];

		if ( empty( $excluded_categories ) ) {
			return $subtotal;
		}

		$rows     = self::build_rows( is_array( $cart ) ? $cart : array() );
		$excluded = WCD_Exclusion_Calculator::excluded_amount( $rows, $excluded_categories );

		return max( 0.0, (float) $subtotal - $excluded );
	}

	/**
	 * ランク除外を適用する wcd_available_rules フィルタ用コールバック。
	 *
	 * @param WCD_Rule[] $rules 割引ルールの配列.
	 * @param array      $cart  カート情報（本コールバックでは未使用。フィルタ契約上受け取る）.
	 * @return WCD_Rule[]
	 */
	public static function filter_available_rules( $rules, $cart ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Welcart 側のフィルタシグネチャ（$rules, $cart の2引数）に合わせるため受け取る。
		$excluded_ranks = WCD_Exclusion_Settings::get()['ranks'];

		if ( empty( $excluded_ranks ) ) {
			return $rules;
		}

		$rank = self::resolve_rank();

		if ( WCD_Exclusion_Calculator::is_rank_excluded( $rank, $excluded_ranks ) ) {
			return array();
		}

		return $rules;
	}

	/**
	 * 現在の文脈からランクを解決する。
	 *
	 * 受注編集画面の再計算中（{@see self::$recalculating_order_id} がセット済み）は
	 * 受注の持ち主のランクを、それ以外（カート・確認画面）はセッション中の
	 * ログイン会員のランクを解決する。
	 *
	 * @return int|string 会員は mem_status（int）、非会員は 'guest'。
	 */
	private static function resolve_rank() {
		if ( null !== self::$recalculating_order_id ) {
			return self::resolve_rank_from_order( self::$recalculating_order_id );
		}

		return self::resolve_rank_from_session();
	}

	/**
	 * セッション中のログイン会員のランクを解決する。
	 *
	 * @return int|string
	 */
	private static function resolve_rank_from_session() {
		global $usces;

		if ( ! isset( $usces ) || ! is_object( $usces ) || ! $usces->is_member_logged_in() ) {
			return WCD_Exclusion_Settings::GUEST_RANK;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- HTTPリクエストの入力ではなく、ログイン済み会員の状態としてWelcart自身が設定したセッション値。is_numeric()検証とintキャストのみで十分。
		$status = isset( $_SESSION['usces_member']['status'] ) ? $_SESSION['usces_member']['status'] : null;

		if ( null === $status || ! is_numeric( $status ) ) {
			return WCD_Exclusion_Settings::GUEST_RANK;
		}

		return (int) $status;
	}

	/**
	 * 受注の持ち主のランクを解決する。
	 *
	 * @param int $order_id 受注ID.
	 * @return int|string
	 */
	private static function resolve_rank_from_order( $order_id ) {
		global $usces;

		if ( ! isset( $usces ) || ! is_object( $usces ) ) {
			return WCD_Exclusion_Settings::GUEST_RANK;
		}

		$order = $usces->get_order_data( $order_id );

		if ( ! is_array( $order ) || empty( $order['mem_id'] ) ) {
			return WCD_Exclusion_Settings::GUEST_RANK;
		}

		$member = $usces->get_member_info( (int) $order['mem_id'] );

		if ( ! is_array( $member ) || ! isset( $member['mem_status'] ) || ! is_numeric( $member['mem_status'] ) ) {
			return WCD_Exclusion_Settings::GUEST_RANK;
		}

		return (int) $member['mem_status'];
	}

	/**
	 * カート行を WCD_Exclusion_Calculator が要求する形式（amount / category_ids）に正規化する。
	 *
	 * @param array $cart カート情報. array<int, array{post_id?: int, price?: float, quantity?: float}>.
	 * @return array array<array{amount: float, category_ids: int[]}>。
	 */
	private static function build_rows( array $cart ) {
		$rows = array();

		foreach ( $cart as $item ) {
			if ( ! is_array( $item ) || empty( $item['post_id'] ) ) {
				continue;
			}

			$post_id = (int) $item['post_id'];
			$price   = isset( $item['price'] ) ? (float) $item['price'] : 0.0;
			$qty     = isset( $item['quantity'] ) ? (float) $item['quantity'] : 0.0;
			$terms   = get_the_terms( $post_id, 'category' );

			$rows[] = array(
				'amount'       => $price * $qty,
				'category_ids' => is_array( $terms ) ? wp_list_pluck( $terms, 'term_id' ) : array(),
			);
		}

		return $rows;
	}
}
