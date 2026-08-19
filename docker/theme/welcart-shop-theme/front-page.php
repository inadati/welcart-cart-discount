<?php
/**
 * トップページ。
 *
 * @package WelcartShopTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$wcd_query = wcd_theme_item_query( array( 'posts_per_page' => 6 ) );
?>

<section class="hero">
	<img class="hero__bg" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/hero.jpg' ); ?>" alt="">
	<div class="hero__inner">
		<h1 class="hero__title">SOUND FORGE</h1>
		<p class="hero__tagline"><?php esc_html_e( 'プロが選ぶ、次に鳴らす一本。', 'welcart-shop-theme' ); ?></p>
		<a class="hero__cta" href="#products"><?php esc_html_e( '商品を見る', 'welcart-shop-theme' ); ?></a>
	</div>
</section>

<main id="products" class="product-grid">
	<?php
	if ( $wcd_query->have_posts() ) :
		while ( $wcd_query->have_posts() ) :
			$wcd_query->the_post();
			get_template_part( 'template-parts/product-card' );
		endwhile;
		wp_reset_postdata();
	else :
		?>
		<p><?php esc_html_e( '商品がまだ登録されていません。', 'welcart-shop-theme' ); ?></p>
		<?php
	endif;
	?>
</main>

<?php get_footer(); ?>
