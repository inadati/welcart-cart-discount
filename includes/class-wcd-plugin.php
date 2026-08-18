<?php
/**
 * フック登録の一元管理。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * プラグイン全体のフック登録を一元管理する。
 */
class WCD_Plugin {

	/**
	 * 初期化処理。plugins_loaded アクションから呼ばれる。
	 *
	 * Welcart が有効でない場合はフックを登録せず、管理画面に通知のみ出す。
	 *
	 * @return void
	 */
	public static function init() {
		load_plugin_textdomain(
			'welcart-cart-discount',
			false,
			dirname( plugin_basename( WCD_PLUGIN_FILE ) ) . '/languages'
		);

		if ( ! class_exists( 'usc_e_shop' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_missing_welcart_notice' ) );
			return;
		}

		add_filter( 'usces_order_discount', array( 'WCD_Integration', 'filter_order_discount' ), 10, 2 );
		add_filter( 'usces_filter_cart_table_footer', array( 'WCD_Integration', 'filter_cart_table_footer' ) );
		add_filter( 'usces_filter_order_discount_recalculation', array( 'WCD_Integration', 'filter_order_recalculation' ), 10, 4 );
		add_action( 'usces_action_reg_orderdata', array( 'WCD_Integration', 'record_injected_discount_on_order_registration' ) );

		add_action( 'admin_menu', array( 'WCD_Admin', 'register_menu' ) );
		add_action( 'admin_post_wcd_save_settings', array( 'WCD_Admin', 'handle_save' ) );
	}

	/**
	 * Welcart 未有効時の管理画面通知を描画する。
	 *
	 * @return void
	 */
	public static function render_missing_welcart_notice() {
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Welcart Cart Discount には Welcart Shop プラグインの有効化が必要です。', 'welcart-cart-discount' )
		);
	}
}
