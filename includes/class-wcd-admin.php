<?php
/**
 * 設定画面の描画と保存処理。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 割引ルール設定画面。
 */
class WCD_Admin {

	/**
	 * 保存処理・メニュー登録で共通して使う capability を返す。
	 *
	 * Welcart 独自の capability（wel_manage_setting）が administrator ロールに
	 * 存在しない環境では manage_options にフォールバックする。
	 *
	 * @return string
	 */
	public static function get_capability() {
		$administrator = get_role( 'administrator' );

		if ( $administrator && $administrator->has_cap( 'wel_manage_setting' ) ) {
			return 'wel_manage_setting';
		}

		return 'manage_options';
	}

	/**
	 * Welcart Shop メニュー配下にサブメニューを追加する admin_menu アクション用コールバック。
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			USCES_PLUGIN_BASENAME,
			__( '自動割引設定', 'welcart-cart-discount' ),
			__( '自動割引設定', 'welcart-cart-discount' ),
			self::get_capability(),
			'wcd_settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * 設定画面を描画する。
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::get_capability() ) ) {
			wp_die( esc_html__( 'この画面にアクセスする権限がありません。', 'welcart-cart-discount' ) );
		}

		$rules = get_option( WCD_Settings::OPTION_KEY, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '自動割引設定', 'welcart-cart-discount' ); ?></h1>
			<p><?php esc_html_e( 'カート合計金額のしきい値と割引額を複数段設定できます。到達した最上位の1段のみが適用されます。', 'welcart-cart-discount' ); ?></p>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 表示切り替えのみに使用し、値の出力・処理は行わないため検証不要。 ?>
			<?php if ( isset( $_GET['wcd_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '設定を保存しました。', 'welcart-cart-discount' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wcd_save_settings" />
				<?php wp_nonce_field( 'wcd_save_settings', 'wcd_nonce' ); ?>
				<table class="widefat" id="wcd-rules-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'しきい値金額（円）', 'welcart-cart-discount' ); ?></th>
							<th><?php esc_html_e( '割引額（円）', 'welcart-cart-discount' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rules as $index => $rule ) : ?>
							<tr>
								<td><input type="number" min="1" step="1" name="wcd_rules[<?php echo (int) $index; ?>][threshold]" value="<?php echo esc_attr( $rule['threshold'] ); ?>" /></td>
								<td><input type="number" min="1" step="1" name="wcd_rules[<?php echo (int) $index; ?>][amount]" value="<?php echo esc_attr( $rule['amount'] ); ?>" /></td>
								<td><button type="button" class="button wcd-remove-row"><?php esc_html_e( '削除', 'welcart-cart-discount' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="wcd-add-row"><?php esc_html_e( '行を追加', 'welcart-cart-discount' ); ?></button></p>
				<?php submit_button( __( '設定を保存', 'welcart-cart-discount' ) ); ?>
			</form>
		</div>
		<script>
		( function() {
			var table = document.getElementById( 'wcd-rules-table' ).getElementsByTagName( 'tbody' )[0];

			function rowTemplate( index ) {
				var tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td><input type="number" min="1" step="1" name="wcd_rules[' + index + '][threshold]" /></td>' +
					'<td><input type="number" min="1" step="1" name="wcd_rules[' + index + '][amount]" /></td>' +
					'<td><button type="button" class="button wcd-remove-row"><?php echo esc_js( __( '削除', 'welcart-cart-discount' ) ); ?></button></td>';
				return tr;
			}

			// tbody の子要素数（table.children.length）を新規行のインデックスに使うと、
			// 行を削除して残存行のインデックスに欠番ができた際、追加した行と既存行の
			// インデックスが衝突し name 属性が重複する。フォーム送信時、同一キーの
			// 重複パラメータは PHP のパース規則により後勝ちとなるため、既存行の入力値が
			// 新規行の値で silently に上書きされてしまう。
			// これを避けるため、既存の input の name 属性から使用済みインデックスの
			// 最大値を都度走査し、その +1 を新規行のインデックスとして採番する。
			// WCD_Settings::normalize() はキーの連番性に依存せず値のみを見て正規化する
			// ため、削除によってインデックスに欠番ができても問題は生じない。
			function nextIndex() {
				var inputs = table.querySelectorAll( 'input[name^="wcd_rules["]' );
				var max = -1;

				for ( var i = 0; i < inputs.length; i++ ) {
					var match = inputs[ i ].name.match( /^wcd_rules\[(\d+)\]/ );

					if ( match && parseInt( match[1], 10 ) > max ) {
						max = parseInt( match[1], 10 );
					}
				}

				return max + 1;
			}

			document.getElementById( 'wcd-add-row' ).addEventListener( 'click', function() {
				table.appendChild( rowTemplate( nextIndex() ) );
			} );

			table.addEventListener( 'click', function( event ) {
				if ( event.target.classList.contains( 'wcd-remove-row' ) ) {
					event.target.closest( 'tr' ).remove();
				}
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * 設定を保存する admin_post_wcd_save_settings アクション用コールバック。
	 *
	 * @return void
	 */
	public static function handle_save() {
		check_admin_referer( 'wcd_save_settings', 'wcd_nonce' );

		if ( ! current_user_can( self::get_capability() ) ) {
			wp_die( esc_html__( 'この操作を行う権限がありません。', 'welcart-cart-discount' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce は上の check_admin_referer() で検証済み。値のサニタイズは WCD_Settings::normalize() 内の absint() で行う。
		$raw = isset( $_POST['wcd_rules'] ) && is_array( $_POST['wcd_rules'] ) ? wp_unslash( $_POST['wcd_rules'] ) : array();

		WCD_Settings::save_rules( $raw );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'wcd_settings',
					'wcd_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
