<?php
/**
 * サイト共通ヘッダー。
 *
 * @package WelcartShopTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="site-header__inner">
		<div class="site-header__logo"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">SOUND FORGE</a></div>
		<nav class="site-header__nav">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'トップ', 'welcart-shop-theme' ); ?></a>
			<?php foreach ( wcd_theme_product_categories() as $wcd_category ) : ?>
				<a href="<?php echo esc_url( get_category_link( $wcd_category ) ); ?>"><?php echo esc_html( $wcd_category->name ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="site-header__icons">
			<?php if ( function_exists( 'usces_url' ) ) : ?>
				<a class="site-header__cart" href="<?php echo esc_url( usces_url( 'cart' ) ); ?>">
					<svg class="site-header__cart-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
						<circle cx="9" cy="21" r="1.4" fill="currentColor" stroke="none" />
						<circle cx="18" cy="21" r="1.4" fill="currentColor" stroke="none" />
						<path d="M1.5 2.5h2.6l2.2 12.6a2 2 0 0 0 2 1.65h9.4a2 2 0 0 0 1.97-1.66l1.43-8.34H5.4" />
					</svg>
					<span class="wcd-visually-hidden"><?php esc_html_e( 'カート', 'welcart-shop-theme' ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</div>
</header>
