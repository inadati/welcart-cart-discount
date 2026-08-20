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
	 * Welcart の受注メタ（wp_usces_order_meta）に、本プラグインが直近に注入した
	 * 割引額（正の値）を記録するためのメタキー。
	 *
	 * Welcart の受注は独自テーブル wp_usces_order に保存され、投稿タイプではない
	 * （wp_posts には存在しない）ため、update_post_meta() / get_post_meta() は
	 * 使用できない。Welcart 自身が用意する $usces->set_order_meta_value() /
	 * $usces->get_order_meta_value()（実体は wp_usces_order_meta テーブルへの
	 * 読み書き、classes/usceshop.class.php:9334, :9342）を使う。
	 *
	 * @var string
	 */
	const INJECTED_DISCOUNT_META_KEY = 'wcd_injected_discount';

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
	 * $condition は Welcart 側のフィルタシグネチャに合わせるためだけに受け取っており、
	 * 本コールバックの計算では使用しない（Welcart の「ショップ条件」であり、
	 * 下記の $change_taxrate とは別物）。
	 *
	 * 【$discount の意味が usces_order_discount とは異なる点、かつ呼び出し経路で
	 * 意味そのものが変わる点に注意（functions/item_post.php を実ソースで確認済み）】
	 *
	 * `usces_order_recalculation()` / `usces_order_recalculation_reduced()`
	 * （呼び出し元 item_post.php:2805, :3023）はいずれも、管理画面の再計算フォームが
	 * 送信する `$_POST['change_taxrate']` の値で $discount の由来が変わる。
	 *
	 * - `change_taxrate !== 'change'`（通常の再計算。数量変更など）:
	 *   $discount は `$_POST['discount']`（または `discount_standard` +
	 *   `discount_reduced`）そのもの、つまり受注編集フォームに現在表示されている
	 *   割引欄の値であり、前回保存時に本プラグインが書き込んだ割引額を含む
	 *   （item_post.php:1139, :1146）。ここに対して単純加算すると、実機検証で
	 *   確認した「1回目の再計算後に -2,000 → -2,500 と二重計上される」不具合が
	 *   再発する。
	 * - `change_taxrate === 'change'`（軽減税率の切替）:
	 *   Welcart は `$discount = 0` としてから Promotionsale キャンペーン割引のみを
	 *   ゼロから再計算する（item_post.php:2779-2795, :2984-3020）。この経路の
	 *   $discount は $_POST の値を一切引き継がず、本プラグインの前回寄与を
	 *   含まない、Welcart 自身のキャンペーン割引のみである。
	 *
	 * そのため、単純な「丸ごと置き換え」（二重計上は防げるが change_taxrate=change
	 * 時に Welcart 自身のキャンペーン割引を消してしまう）でも、単純な「常に加算」
	 * （二重計上が再発する）でもなく、$_POST['change_taxrate'] で経路を判定した上で
	 * 以下のように扱う。
	 *
	 * - change_taxrate === 'change' のとき: $discount は本プラグインの寄与を
	 *   含まないため、そのまま加算する（$discount - $amount）。
	 * - それ以外のとき: 前回本プラグインが注入した割引額（wp_usces_order_meta に
	 *   {@see self::INJECTED_DISCOUNT_META_KEY} で記録済み）だけを $discount から
	 *   差し戻し、Welcart 自身のキャンペーン割引など他の割引成分を復元してから、
	 *   新しい割引額を適用する。
	 *
	 * いずれの経路でも、次回のためにその時点の割引額を meta に書き直す。
	 *
	 * 【$_POST['change_taxrate'] を参照することについて】
	 * Welcart のフィルタ引数（$discount, $cart, $condition, $order_id）だけでは
	 * どちらの経路かを判別できない（$condition は変化しない）。$change_taxrate は
	 * item_post.php:1136, :1143 で `$_POST['change_taxrate']` から読み取られる
	 * ローカル変数であり、フィルタには渡されない。このフィルタは常に同一リクエスト
	 * 内（Welcart 自身の管理画面ハンドラの実行中）でのみ呼ばれるため、同じ
	 * $_POST を読み取ることで経路を判別する。この値は表示分岐にのみ使い、
	 * 保存処理の実行可否そのものは判断していないため、nonce 検証は不要
	 * （Welcart 側のリクエスト処理で完結している）。将来 Welcart がこの
	 * フィールド名を変更した場合は空文字列にフォールバックし、常に
	 * meta による差し戻し（より安全な既定動作）を行う。
	 *
	 * @param float  $discount  Welcart 側で算出された割引額（経路により意味が異なる。上記参照）.
	 * @param array  $cart      受注編集フォームから組み立てられたカート相当の配列.
	 * @param string $condition 再計算の条件（Welcart のショップ条件。本コールバックでは未使用）.
	 * @param int    $order_id  受注ID.
	 * @return float
	 */
	public static function filter_order_recalculation( $discount, $cart, $condition, $order_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Welcart 側のフィルタシグネチャ（4引数）に合わせるため受け取る。

		/*
		 * この経路のセッション会員は操作している管理者であり、受注の持ち主ではない
		 * （クラス冒頭のコメント参照）。除外条件（会員ランク除外）の判定を受注の
		 * 持ち主から解決させるため、calculate_amount() の実行中だけ対象受注IDを
		 * WCD_Exclusion に通知する。wcd_available_rules フィルタの引数は
		 * ($rules, $cart) の2つのみで $order_id を含まないため、フィルタ契約を
		 * 変えずに文脈を受け渡す手段として静的プロパティを用いる。
		 */
		WCD_Exclusion::begin_order_recalculation( $order_id );
		$amount = self::calculate_amount( $cart );
		WCD_Exclusion::end_order_recalculation();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- 保存可否の判断には使わず、$discount の由来（通常再計算か軽減税率変更か）を判別する読み取り専用の分岐にのみ使用する。nonce検証・権限チェックはWelcart自身の管理画面ハンドラ（item_post.php）側で完結している。
		$change_taxrate = isset( $_POST['change_taxrate'] ) ? sanitize_text_field( wp_unslash( $_POST['change_taxrate'] ) ) : '';

		if ( 'change' === $change_taxrate ) {
			// この経路の $discount は本プラグインの寄与を含まない
			// （Welcart 自身のキャンペーン割引のみ、ゼロから再計算されたもの）ため,
			// そのまま加算する.
			$new_discount = $discount - $amount;
		} else {
			// この経路の $discount には前回本プラグインが注入した割引額が
			// 含まれている. その既知の値だけを差し戻し、他の割引成分を復元する.
			$previous_injected = self::get_injected_discount( $order_id );
			$other_components  = $discount + $previous_injected;
			$new_discount      = $other_components - $amount;
		}

		self::remember_injected_discount( $order_id, $amount );

		return $new_discount;
	}

	/**
	 * 受注登録アクション usces_action_reg_orderdata 用コールバック。
	 *
	 * 受注が新規登録された直後（functions/function.php:266, :417 の
	 * `do_action( 'usces_action_reg_orderdata', $args )`）に発火する。この時点で
	 * $args['order_id'] は確定済みで、$args['cart'] は登録された受注のカート内容
	 * （array<int, array{post_id:int, price:float, quantity:float, ...}>）である。
	 * usces_reg_orderdata() / usces_new_orderdata()（settlement_func.php:794,
	 * usceshop.class.php:966, :7678）は全ての決済方法が共通して通る受注登録経路
	 * であるため、ここで本プラグインが実際に注入した割引額（正の値）を記録して
	 * おき、受注編集時の再計算（filter_order_recalculation()）で差し戻しに使う。
	 *
	 * @param array $args 'cart', 'entry', 'order_id' 等を含む連想配列.
	 * @return void
	 */
	public static function record_injected_discount_on_order_registration( $args ) {
		if ( ! is_array( $args ) || empty( $args['order_id'] ) || empty( $args['cart'] ) ) {
			return;
		}

		self::remember_injected_discount( (int) $args['order_id'], self::calculate_amount( $args['cart'] ) );
	}

	/**
	 * 本プラグインが直近に注入した割引額を Welcart の受注メタから取得する。
	 *
	 * @param int $order_id 受注ID.
	 * @return float 正の値。記録がなければ 0.0。
	 */
	private static function get_injected_discount( $order_id ) {
		global $usces;

		if ( empty( $order_id ) || ! isset( $usces ) || ! is_object( $usces ) ) {
			return 0.0;
		}

		return (float) $usces->get_order_meta_value( self::INJECTED_DISCOUNT_META_KEY, (int) $order_id );
	}

	/**
	 * 本プラグインが注入した割引額を Welcart の受注メタに記録する。
	 *
	 * Welcart の受注は独自テーブル（wp_usces_order）に保存され投稿タイプではない
	 * ため、post meta ではなく $usces->set_order_meta_value() を使う
	 * （classes/usceshop.class.php:9342）。
	 *
	 * @param int   $order_id 受注ID.
	 * @param float $amount   正の割引額.
	 * @return void
	 */
	private static function remember_injected_discount( $order_id, $amount ) {
		global $usces;

		if ( empty( $order_id ) || ! isset( $usces ) || ! is_object( $usces ) ) {
			return;
		}

		$usces->set_order_meta_value( self::INJECTED_DISCOUNT_META_KEY, $amount, (int) $order_id );
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

		if ( ! isset( $usces->cart ) || ! is_object( $usces->cart ) ) {
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

		/*
		 * 行の HTML は WCD_Cart_Row_Builder に組み立てさせる。
		 * 挿入先フッターの列構成（colspan を含む）に合わせた行を生成するため、
		 * $footer をそのまま渡す。詳細は class-wcd-cart-row-builder.php の説明を参照。
		 */
		$row = WCD_Cart_Row_Builder::build(
			$footer,
			array(
				array(
					'class'  => 'wcd-discount-row',
					'label'  => esc_html__( '自動割引', 'welcart-cart-discount' ),
					'amount' => '-' . self::format_price( $amount ),
				),
				array(
					'class'  => 'wcd-discounted-total-row',
					'label'  => esc_html__( '割引後合計', 'welcart-cart-discount' ),
					'amount' => self::format_price( max( 0, $subtotal - $amount ) ),
				),
			)
		);

		return str_replace( $needle, $row . $needle, $footer );
	}

	/**
	 * 金額を Welcart の通貨設定に従って整形する。
	 *
	 * 当初は `'-&yen;' . number_format( $amount )` のように円記号を直書きしていたため、
	 * Welcart 側の通貨設定（管理画面 > Welcart Shop > 基本設定の「通貨」。
	 * 既定値は USD）が何であっても円表示になり、カート表の他の金額と記号が
	 * 食い違っていた（既定設定の検証環境で他の金額が `$` 表示になり発覚）。
	 * Welcart 本体が金額表示に使う usces_crform()（functions/template_func.php:3736）
	 * に委譲することで、通貨記号・桁区切りが本体の他の金額と常に一致する。
	 *
	 * 第2・第3引数は本体のカート表フッター（templates/cart/cart.php:61）と
	 * 同じく「記号を前置し、後置しない」を指定する。
	 * 戻り値は usces_crform() 内で esc_html() 済みである。
	 *
	 * @param float $amount 金額.
	 * @return string 整形済みの金額文字列（エスケープ済み）。
	 */
	private static function format_price( $amount ) {
		if ( function_exists( 'usces_crform' ) ) {
			return (string) usces_crform( (float) $amount, true, false, 'return' );
		}

		return esc_html( number_format( (float) $amount ) );
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
