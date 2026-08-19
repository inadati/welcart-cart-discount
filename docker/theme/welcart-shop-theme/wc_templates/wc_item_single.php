<?php
/**
 * 商品詳細ページ（Welcart 上書きテンプレート）。
 *
 * usc-e-shop/classes/usceshop.class.php の template_redirect() から
 * 直接 include + exit されるため、get_header()/get_footer() を自分で呼ぶ。
 *
 * 既知の制約: usc-e-shop/templates/single_item.php にある同梱商品
 * （assistance_item）・事業者向け数量割引の表示ブロックは実装していない。
 * また複数SKU（色・サイズ違い等）の選択UIも実装していない。
 * usces_the_item() は $usces->itemskus（複数形）を用意するだけで、
 * usces_the_itemPriceCr() 等が参照する $usces->itemsku（単数形）は
 * usces_have_skus() を呼んでカーソルを進めるまで未設定のまま（null）になる
 * （usc-e-shop/functions/template_func.php の usces_have_skus() で確認済み）。
 * 本テーマは商品1点につきSKU1件の運用を前提とし、最初の1件だけを取得する。
 *
 * @package WelcartShopTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

usces_the_item();
usces_have_skus();
$wcd_item_categories = get_the_category();
?>

<nav class="breadcrumb">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'トップ', 'welcart-shop-theme' ); ?></a>
	/
	<?php if ( $wcd_item_categories ) : ?>
		<a href="<?php echo esc_url( get_category_link( $wcd_item_categories[0] ) ); ?>"><?php echo esc_html( $wcd_item_categories[0]->name ); ?></a> /
	<?php endif; ?>
	<?php echo wp_kses_post( usces_the_itemName( 'return' ) ); ?>
</nav>

<section class="item-detail">
	<div class="item-detail__gallery">
		<div class="item-detail__gallery-main">
			<?php echo wp_kses_post( usces_the_itemImage( 0, 600, 600, $post, 'return' ) ); ?>
		</div>
	</div>
	<div class="item-detail__info">
		<p class="item-detail__category"><?php echo esc_html( $wcd_item_categories ? $wcd_item_categories[0]->name : '' ); ?></p>
		<h1 class="item-detail__name"><?php echo wp_kses_post( usces_the_itemName( 'return' ) ); ?></h1>
		<p class="item-detail__price"><?php echo wp_kses_post( usces_the_itemPriceCr( 'return' ) ); ?></p>
		<p class="item-detail__stock"><?php echo esc_html( usces_get_itemZaiko( 'name' ) ); ?></p>

		<form class="item-detail__form" action="<?php echo esc_url( USCES_CART_URL ); ?>" method="post">
			<div class="item-detail__qty">
				<?php
				/*
				 * usces_the_itemQuant() は <input type="text"> と onKeyDown 属性を含む
				 * Welcart自身が組み立てる信頼済みHTML。wp_kses_post() の投稿本文用
				 * 許可タグには <input> が含まれず出力が消えてしまうため、サニタイズせず
				 * そのまま出力する（実機検証で発覚：数量入力欄・カートボタンが
				 * 消えていた）。
				 *
				 * usces_the_itemQuant() 自体はlabelを生成しない
				 * （usc-e-shop/functions/template_func.php で確認済み）ため、
				 * 入力欄と同じ id="quant[投稿ID][SKUコード]"（usces_the_itemQuant()内の
				 * 組み立てロジックと同一）を for 属性に指定したlabelをテーマ側で補う。
				 */
				global $usces, $post;
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- usces_the_itemQuant() 内のid組み立てがurlencode()を使用しているため、for属性を一致させるには同じ関数を使う必要がある。
				$wcd_qty_input_id = 'quant[' . (int) $post->ID . '][' . urlencode( $usces->itemsku['code'] ) . ']';
				?>
				<label for="<?php echo esc_attr( $wcd_qty_input_id ); ?>"><?php esc_html_e( '数量', 'welcart-shop-theme' ); ?></label>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Welcart本体が組み立てた信頼済みHTML。
				echo usces_the_itemQuant( 'return' );
				?>
			</div>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 同上（カートボタンのhidden input群）。
			echo usces_the_itemSkuButton( __( 'カートに入れる', 'welcart-shop-theme' ), 0, 'return' );
			?>
		</form>
	</div>
</section>

<section class="item-detail__description">
	<?php the_content(); ?>
</section>

<?php get_footer(); ?>
