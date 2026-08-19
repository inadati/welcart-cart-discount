<?php
/**
 * カテゴリ別商品一覧。
 *
 * @package WelcartShopTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$wcd_query = wcd_theme_item_query(
	array(
		'posts_per_page' => 12,
		'paged'          => max( 1, (int) get_query_var( 'paged' ) ),
		'cat'            => get_queried_object_id(),
	)
);
?>

<nav class="breadcrumb">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'トップ', 'welcart-shop-theme' ); ?></a> / <?php single_cat_title(); ?>
</nav>

<h1 class="archive-title"><?php single_cat_title(); ?></h1>

<main class="product-grid">
	<?php
	if ( $wcd_query->have_posts() ) :
		while ( $wcd_query->have_posts() ) :
			$wcd_query->the_post();
			get_template_part( 'template-parts/product-card' );
		endwhile;
		?>
		<div class="pagination" style="grid-column: 1 / -1;">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'total'   => $wcd_query->max_num_pages,
						'current' => max( 1, (int) get_query_var( 'paged' ) ),
					)
				)
			);
			?>
		</div>
		<?php
		wp_reset_postdata();
	else :
		?>
		<p><?php esc_html_e( 'このカテゴリーには商品がありません。', 'welcart-shop-theme' ); ?></p>
		<?php
	endif;
	?>
</main>

<?php get_footer(); ?>
