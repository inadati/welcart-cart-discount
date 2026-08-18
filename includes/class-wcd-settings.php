<?php
/**
 * 設定の読み書き・正規化。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * プラグイン設定（割引ルール）の読み書きと正規化を担う。
 */
class WCD_Settings {

	/**
	 * オプションキー。
	 *
	 * @var string
	 */
	const OPTION_KEY = 'wcd_settings';

	/**
	 * POST 等から渡された生の配列を正規化する。
	 *
	 * - 各値を absint() で整数化する
	 * - しきい値または割引額が0以下の行を破棄する
	 * - しきい値が重複する行は後勝ちで排除する
	 * - しきい値の昇順にソートする
	 *
	 * @param array $raw 生の入力。 array<array{threshold: mixed, amount: mixed}>。
	 * @return array 正規化済みの配列。 array<array{threshold: int, amount: int}>。
	 */
	public static function normalize( array $raw ) {
		$by_threshold = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['threshold'], $row['amount'] ) ) {
				continue;
			}

			if ( ! is_numeric( $row['threshold'] ) || ! is_numeric( $row['amount'] ) ) {
				continue;
			}

			// absint() は abs( intval() ) であり負値をそのまま正値へ反転させるため、
			// 0以下（負値含む）の判定は absint() する前の生値に対して行う。
			if ( (float) $row['threshold'] <= 0 || (float) $row['amount'] <= 0 ) {
				continue;
			}

			$threshold = absint( $row['threshold'] );
			$amount    = absint( $row['amount'] );

			$by_threshold[ $threshold ] = array(
				'threshold' => $threshold,
				'amount'    => $amount,
			);
		}

		ksort( $by_threshold, SORT_NUMERIC );

		return array_values( $by_threshold );
	}

	/**
	 * 保存済みの割引ルールを WCD_Rule の配列として返す。
	 *
	 * @return WCD_Rule[]
	 */
	public static function get_rules() {
		$rows = get_option( self::OPTION_KEY, array() );

		$rules = array();
		foreach ( self::normalize( is_array( $rows ) ? $rows : array() ) as $row ) {
			$rules[] = new WCD_Rule( $row['threshold'], $row['amount'] );
		}

		return $rules;
	}

	/**
	 * 割引ルールを保存する。
	 *
	 * @param array $raw 生の入力。
	 * @return void
	 */
	public static function save_rules( array $raw ) {
		update_option( self::OPTION_KEY, self::normalize( $raw ) );
	}
}
