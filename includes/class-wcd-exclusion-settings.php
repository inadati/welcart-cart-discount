<?php
/**
 * 除外設定（会員ランク・商品カテゴリ）の読み書きと正規化。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 除外設定（option `wcd_exclusions`）の読み書きと正規化を担う。
 */
class WCD_Exclusion_Settings {

	/**
	 * オプションキー。
	 *
	 * @var string
	 */
	const OPTION_KEY = 'wcd_exclusions';

	/**
	 * 非会員（ゲスト）を表す擬似ランクのキー。
	 *
	 * @var string
	 */
	const GUEST_RANK = 'guest';

	/**
	 * POST 等から渡された生の配列を正規化する。
	 *
	 * - ranks: `guest` は文字列のまま保持する。それ以外は absint() し、
	 *   `$known_ranks` に存在しないキーは破棄する
	 * - categories: absint() し、0以下と重複を破棄する
	 * - 両方とも重複排除し array_values() で連番化する
	 *
	 * @param array $raw         生の入力。array{ranks?: mixed, categories?: mixed}.
	 * @param array $known_ranks 有効なランクキーの配列（int）。option `usces_customer_status` のキー一覧を渡す.
	 * @return array 正規化済みの配列。array{ranks: array<int|string>, categories: array<int>}。
	 */
	public static function normalize( array $raw, array $known_ranks ) {
		$ranks = array();

		if ( isset( $raw['ranks'] ) && is_array( $raw['ranks'] ) ) {
			foreach ( $raw['ranks'] as $rank ) {
				if ( self::GUEST_RANK === $rank ) {
					$ranks[ self::GUEST_RANK ] = self::GUEST_RANK;
					continue;
				}

				if ( ! is_numeric( $rank ) ) {
					continue;
				}

				$rank_id = absint( $rank );

				if ( ! in_array( $rank_id, $known_ranks, true ) ) {
					continue;
				}

				$ranks[ $rank_id ] = $rank_id;
			}
		}

		$categories = array();

		if ( isset( $raw['categories'] ) && is_array( $raw['categories'] ) ) {
			foreach ( $raw['categories'] as $category ) {
				// absint() は絶対値を取るため、0以下の判定は absint() を適用する前に行う
				// （先に absint() すると負数が正数に反転し、0以下の除外条件を通過できなくなるため）.
				if ( ! is_numeric( $category ) || $category <= 0 ) {
					continue;
				}

				$category_id = absint( $category );

				$categories[ $category_id ] = $category_id;
			}
		}

		return array(
			'ranks'      => array_values( $ranks ),
			'categories' => array_values( $categories ),
		);
	}

	/**
	 * 保存済みの除外設定を返す。
	 *
	 * @return array array{ranks: array<int|string>, categories: array<int>}。
	 */
	public static function get() {
		$saved = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array(
			'ranks'      => isset( $saved['ranks'] ) && is_array( $saved['ranks'] ) ? $saved['ranks'] : array(),
			'categories' => isset( $saved['categories'] ) && is_array( $saved['categories'] ) ? $saved['categories'] : array(),
		);
	}

	/**
	 * 除外設定を保存する。
	 *
	 * @param array $raw 生の入力.
	 * @return void
	 */
	public static function save( array $raw ) {
		update_option( self::OPTION_KEY, self::normalize( $raw, array_keys( self::get_known_ranks() ) ) );
	}

	/**
	 * 会員ランクの選択肢を、擬似ランク「未ログイン（非会員）」を加えて返す。
	 *
	 * @return array<int|string, string> ランクキー（int または 'guest'） => ラベル.
	 */
	public static function get_rank_choices() {
		$choices = array(
			self::GUEST_RANK => __( '未ログイン（非会員）', 'welcart-cart-discount' ),
		);

		foreach ( self::get_known_ranks() as $rank_id => $label ) {
			$choices[ $rank_id ] = $label;
		}

		return $choices;
	}

	/**
	 * Welcart の会員ランク定義（option `usces_customer_status`）を返す。
	 *
	 * @return array<int, string> ランクID => ラベル.
	 */
	private static function get_known_ranks() {
		$statuses = get_option( 'usces_customer_status', array() );

		return is_array( $statuses ) ? $statuses : array();
	}
}
