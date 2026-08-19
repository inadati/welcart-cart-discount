<?php
/**
 * テーマのセットアップとヘルパー関数。
 *
 * @package WelcartShopTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * after_setup_theme アクション。
 *
 * @return void
 */
function wcd_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' )
	);
	register_nav_menus(
		array(
			'primary' => __( 'メインナビゲーション', 'welcart-shop-theme' ),
		)
	);
}
add_action( 'after_setup_theme', 'wcd_theme_setup' );

/**
 * wp_enqueue_scripts アクション。
 *
 * @return void
 */
function wcd_theme_enqueue_assets() {
	wp_enqueue_style( 'wcd-theme-style', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );

	/*
	 * Welcart 本体のカート用CSS（usces_cart.css）は読み込まない。
	 *
	 * usces_cart.css は `#cart th { background-color: #999999 }` のように
	 * IDセレクタで色・固定列幅・余白を直接指定するルールを78個持つ。
	 * 本テーマの装飾はクラスセレクタ（詳細度 0,2,x）で書いているため
	 * これらに構造的に勝てず、当初は見た目の崩れに気づいた箇所だけを
	 * !important や #cart_table を足して個別に打ち消していた。
	 * 結果として「気づいていない箇所は本体の指定が残ったまま」という
	 * 状態が広範囲に生じ、ステップナビの文字切れ（display:flex が
	 * div.usccart_navi ol.ucart の display:block に負けていた）、
	 * ボタン上部の黄色い罫線（#inside-cart .send の border-top）など、
	 * 実測で29件の指定が負けていた。
	 *
	 * 打ち消しを積み増すのではなく本体CSSの読み込み自体を止め、
	 * カート導線6画面の装飾を shop-pages.css で完全に自前で持つ方針に変更した。
	 * これにより詳細度の競合が原理的に発生しなくなる。
	 * 代わりに列幅・フォーム・ボタン等をすべて明示する責任を負う。
	 *
	 * usces_default_css（カート専用ではない共通CSS）は
	 * 会員ページ等でも使われるため読み込みを維持し、依存に指定して
	 * 本テーマのCSSが必ず後段に出力されるようにする。
	 */
	wp_dequeue_style( 'usces_cart_css' );
	wp_deregister_style( 'usces_cart_css' );

	wp_enqueue_style(
		'wcd-theme-shop-pages',
		get_stylesheet_directory_uri() . '/assets/css/shop-pages.css',
		array( 'wcd-theme-style', 'usces_default_css' ),
		wp_get_theme()->get( 'Version' )
	);

	/*
	 * カート画面の数量欄に − / + ボタンを付け、変更時に自動更新する
	 * （assets/js/cart-quantity-stepper.js 参照）。
	 * 更新は Welcart 自身の更新ボタンを click() して行うため、
	 * Welcart のインラインJS（uscesCart）が先に定義されている必要がある。
	 * これは本体がフッターで出力するため、こちらもフッター読み込みにする。
	 * カート画面以外では #cart_table が無く何もしないので、常時読み込んでよい。
	 */
	wp_enqueue_script(
		'wcd-theme-cart-quantity-stepper',
		get_stylesheet_directory_uri() . '/assets/js/cart-quantity-stepper.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'wcd_theme_enqueue_assets', 20 );

/**
 * Welcart のボタンラベルに含まれる装飾目的の空白を取り除く gettext フィルタ。
 *
 * Welcart の「次へ」ボタンの原文は ' Next '（前後に半角空白）で、日本語訳は
 * '　　次　へ　　'（全角空白で前後と文字間を埋めたもの）になっている。
 * これは本体既定のボタン装飾に合わせて訳文側で幅を稼ぐ古い書き方であり、
 * 本テーマのようにボタンへ padding を与えている場合は不要な余白として
 * 「次　へ」のように不自然な間隔で表示される（実機で value を確認して判明）。
 *
 * 原文が ' Next ' の場合に限定して、訳文から空白（全角 U+3000 を含む）を
 * すべて取り除き「次へ」にする。前後だけでなく文字間にも全角空白が
 * 入っているため、前後の trim だけでは「次　へ」と間延びしたまま残る。
 * 「次へ」に語中の空白が入る余地はないので、この文字列に限り全除去でよい。
 * 他の文字列には影響させない。
 *
 * @param string $translation 訳文.
 * @param string $text        原文.
 * @param string $domain      テキストドメイン.
 * @return string
 */
function wcd_theme_trim_usces_button_label( $translation, $text, $domain ) {
	if ( 'usces' !== $domain || ' Next ' !== $text ) {
		return $translation;
	}

	return (string) preg_replace( '/[\s\x{3000}]+/u', '', $translation );
}
add_filter( 'gettext', 'wcd_theme_trim_usces_button_label', 10, 3 );

/**
 * Welcart の商品（post_mime_type = 'item'）だけを取得するクエリを作る。
 *
 * WP_Query の 'post_mime_type' 引数はスラッシュを含まない値を渡すと
 * wp_post_mime_type_where()（wp-includes/post.php）が 'item/%' という
 * LIKE パターンに変換してしまい、実際の投稿（post_mime_type = 'item'）に
 * ヒットしない。そのため posts_where フィルタで直接一致条件を追加する。
 *
 * @param array $args WP_Query に渡す追加引数。
 * @return WP_Query
 */
function wcd_theme_item_query( $args = array() ) {
	$defaults = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 12,
	);
	$args     = wp_parse_args( $args, $defaults );

	add_filter( 'posts_where', 'wcd_theme_filter_item_mime_type' );
	$query = new WP_Query( $args );
	remove_filter( 'posts_where', 'wcd_theme_filter_item_mime_type' );

	return $query;
}

/**
 * posts_where フィルタ本体。
 *
 * @param string $where 既存の WHERE 句。
 * @return string
 */
function wcd_theme_filter_item_mime_type( $where ) {
	global $wpdb;
	return $where . " AND {$wpdb->posts}.post_mime_type = 'item'";
}

/**
 * テーマが扱う商品カテゴリ名（tools/seed-items.php の投入対象と同一）。
 *
 * @return string[]
 */
function wcd_theme_product_category_names() {
	return array( 'ギター', 'ベース', 'アンプ', 'エフェクター', 'ドラム' );
}

/**
 * 上記カテゴリ名に対応する WP_Term のみを、指定順で返す。
 *
 * category タクソノミーには、Welcart本体が自動生成する既定カテゴリ
 * （Items / Item genre / Items recommended / New items）や WordPress標準の
 * 「Uncategorized」も混在しているため、get_categories() で全件取得して
 * 絞り込むのではなく、既知のカテゴリ名で個別に取得する。
 *
 * @return WP_Term[]
 */
function wcd_theme_product_categories() {
	$terms = array();
	foreach ( wcd_theme_product_category_names() as $name ) {
		$term = get_term_by( 'name', $name, 'category' );
		if ( $term ) {
			$terms[] = $term;
		}
	}
	return $terms;
}
