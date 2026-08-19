<?php
/**
 * 購入確認画面（Welcart 上書きテンプレート）。
 *
 * @package WelcartShopTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$html = '';
require WP_PLUGIN_DIR . '/usc-e-shop/templates/cart/confirm.php';
?>

<main class="wcd-shop-page wcd-shop-page--confirm">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Welcart本体が組み立てた信頼済みHTML。
	echo $html;
	?>
</main>

<?php get_footer(); ?>
