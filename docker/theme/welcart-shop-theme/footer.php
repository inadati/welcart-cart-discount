<?php
/**
 * サイト共通フッター。
 *
 * @package WelcartShopTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<footer class="site-footer">
	<div class="site-footer__inner">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> SOUND FORGE</p>
		<div class="site-footer__links">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '会社概要', 'welcart-shop-theme' ); ?></a>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
