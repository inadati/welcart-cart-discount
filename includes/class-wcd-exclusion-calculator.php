<?php
/**
 * 除外計算コア。WordPress・Welcart に非依存の純粋な計算ロジック。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 除外カテゴリによる小計減算と、除外ランク判定の純粋計算を担う。
 */
class WCD_Exclusion_Calculator {

	/**
	 * 除外カテゴリに属する行の金額合計を返す。
	 *
	 * @param array $rows                  カート行の配列。array<array{amount: float, category_ids: int[]}>.
	 * @param array $excluded_category_ids 除外カテゴリIDの配列（int）.
	 * @return float 除外分の合計金額。0以上。
	 */
	public static function excluded_amount( array $rows, array $excluded_category_ids ) {
		if ( empty( $excluded_category_ids ) ) {
			return 0.0;
		}

		$total = 0.0;

		foreach ( $rows as $row ) {
			if ( ! isset( $row['amount'], $row['category_ids'] ) || ! is_array( $row['category_ids'] ) ) {
				continue;
			}

			if ( array_intersect( $row['category_ids'], $excluded_category_ids ) ) {
				$total += (float) $row['amount'];
			}
		}

		return $total;
	}

	/**
	 * ランクが除外対象かどうかを判定する。
	 *
	 * ゲストは文字列 'guest'、会員は int の mem_status として渡される。型を跨ぐ
	 * 比較になるため厳密比較で判定する（`0 == 'guest'` が PHP 8 未満で true になる
	 * 罠を避けるため。PHP 8 以降は false だが、composer.json の下限が 7.4 のため
	 * 厳密比較を必須とする）。
	 *
	 * @param int|string $rank           判定対象のランク。ゲストは 'guest'、会員は mem_status（int）.
	 * @param array      $excluded_ranks 除外対象のランクの配列（int|string）.
	 * @return bool
	 */
	public static function is_rank_excluded( $rank, array $excluded_ranks ) {
		return in_array( $rank, $excluded_ranks, true );
	}
}
