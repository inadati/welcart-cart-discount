<?php
/**
 * フォールバックテンプレート。
 *
 * WordPress のクラシックテーマは index.php を必須とする（欠けていると
 * "theme_no_index" エラーでテーマとして認識されない）。front-page.php /
 * archive.php / wc_templates 以外の表示（通常投稿・検索結果・404等）で使われる。
 *
 * @package WelcartShopTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main class="wcd-shop-page">
	<?php
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class(); ?>>
				<h1><?php the_title(); ?></h1>
				<?php the_content(); ?>
			</article>
			<?php
		endwhile;
	else :
		?>
		<p><?php esc_html_e( 'コンテンツが見つかりませんでした。', 'welcart-shop-theme' ); ?></p>
		<?php
	endif;
	?>
</main>

<?php get_footer(); ?>
