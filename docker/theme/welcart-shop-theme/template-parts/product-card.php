<?php
/**
 * 商品カードの共通パーツ。
 *
 * @package WelcartShopTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

usces_the_item();
usces_have_skus();
$wcd_item_categories = get_the_category();
?>
<article class="product-card">
	<a class="product-card__image" href="<?php the_permalink(); ?>">
		<?php echo wp_kses_post( usces_the_itemImage( 0, 400, 400, $post, 'return' ) ); ?>
	</a>
	<div class="product-card__body">
		<p class="product-card__category"><?php echo esc_html( $wcd_item_categories ? $wcd_item_categories[0]->name : '' ); ?></p>
		<h2 class="product-card__name"><a href="<?php the_permalink(); ?>"><?php echo wp_kses_post( usces_the_itemName( 'return' ) ); ?></a></h2>
		<p class="product-card__price"><?php echo wp_kses_post( usces_the_itemPriceCr( 'return' ) ); ?></p>
		<a class="product-card__cta" href="<?php the_permalink(); ?>"><?php esc_html_e( '商品を見る', 'welcart-shop-theme' ); ?></a>
	</div>
</article>
