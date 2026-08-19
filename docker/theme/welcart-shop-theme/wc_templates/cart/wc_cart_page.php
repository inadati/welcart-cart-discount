<?php
/**
 * カート画面（Welcart 上書きテンプレート）。
 *
 * usc-e-shop/templates/cart/cart.php は $html 変数を組み立てるだけで
 * 自身は出力を行わないため、include して $html を作らせてから
 * テーマの header/footer で包んで出力する。$html は Welcart 自身が
 * 組み立てる信頼済みHTML（数量更新用の onclick 属性を含む）のため、
 * サニタイズせずそのまま出力する。
 *
 * @package WelcartShopTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$html = '';
require WP_PLUGIN_DIR . '/usc-e-shop/templates/cart/cart.php';
?>

<main class="wcd-shop-page wcd-shop-page--cart">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Welcart本体が組み立てた信頼済みHTML。
	echo $html;
	?>
</main>

<?php get_footer(); ?>
