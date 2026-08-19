<?php
/**
 * 商品25点（5カテゴリ×5点）を投入する WP-CLI スクリプト。
 *
 * 実行: wp eval-file /scripts/seed-items.php --path=/var/www/html
 * 冪等: itemCode が既存の場合は投稿・SKU・画像を上書き更新し、複製しない。
 *
 * @package WelcartShopTheme
 */

if ( ! defined( 'WP_CLI' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WP_Filesystemはまだ読み込まれていない起動直後のCLIスクリプトのため、標準のfwrite()でSTDERRに直接出力する。
	fwrite( STDERR, "This script must be run via WP-CLI (wp eval-file).\n" );
	exit( 1 );
}

$wcd_products = array(
	array(
		'category' => 'ギター',
		'name'     => 'Vintage Sunburst STD',
		'code'     => 'SF-GT-001',
		'price'    => 98000,
		'image'    => 'guitar-001.jpg',
		'desc'     => '60年代のサンバーストフィニッシュを再現したクラシックストラト系モデル。アルダーボディにメイプルネックを組み合わせ、抜けの良いブライトなトーンが特徴。',
	),
	array(
		'category' => 'ギター',
		'name'     => 'Custom HB Black',
		'code'     => 'SF-GT-002',
		'price'    => 118000,
		'image'    => 'guitar-002.jpg',
		'desc'     => 'ハムバッカー2基を搭載したブラックフィニッシュのカスタムモデル。太く歪みやすいトーンでロック系のプレイに向く。',
	),
	array(
		'category' => 'ギター',
		'name'     => 'Compact Travel Guitar',
		'code'     => 'SF-GT-003',
		'price'    => 42000,
		'image'    => 'guitar-003.jpg',
		'desc'     => 'ヘッドレス構造で持ち運びやすいコンパクトなトラベルギター。',
	),
	array(
		'category' => 'ギター',
		'name'     => 'Semi-Hollow Cherry',
		'code'     => 'SF-GT-004',
		'price'    => 156000,
		'image'    => 'guitar-004.jpg',
		'desc'     => 'チェリーレッドのセミホロウボディ。温かみのある鳴りが特徴。',
	),
	array(
		'category' => 'ギター',
		'name'     => 'Nylon Classic Natural',
		'code'     => 'SF-GT-005',
		'price'    => 68000,
		'image'    => 'guitar-005.jpg',
		'desc'     => 'ナチュラルフィニッシュのクラシックナイロン弦ギター。',
	),
	array(
		'category' => 'ベース',
		'name'     => 'Deep Black PB Bass',
		'code'     => 'SF-BS-001',
		'price'    => 68000,
		'image'    => 'bass-001.jpg',
		'desc'     => 'プレシジョンベース系のブラックフィニッシュ4弦ベース。',
	),
	array(
		'category' => 'ベース',
		'name'     => '5-String Active Bass',
		'code'     => 'SF-BS-002',
		'price'    => 135000,
		'image'    => 'bass-002.jpg',
		'desc'     => 'アクティブ回路搭載の5弦ベース。低域まで安定した出力。',
	),
	array(
		'category' => 'ベース',
		'name'     => 'Short Scale Bass Blue',
		'code'     => 'SF-BS-003',
		'price'    => 58000,
		'image'    => 'bass-003.jpg',
		'desc'     => 'ショートスケールで弾きやすいブルーメタリックのベース。',
	),
	array(
		'category' => 'ベース',
		'name'     => 'Fretless Bass Natural',
		'code'     => 'SF-BS-004',
		'price'    => 92000,
		'image'    => 'bass-004.jpg',
		'desc'     => 'ナチュラルフィニッシュのフレットレスベース。',
	),
	array(
		'category' => 'ベース',
		'name'     => 'Vintage JB Bass Sunburst',
		'code'     => 'SF-BS-005',
		'price'    => 88000,
		'image'    => 'bass-005.jpg',
		'desc'     => 'ジャズベース系のサンバーストフィニッシュ4弦ベース。',
	),
	array(
		'category' => 'アンプ',
		'name'     => 'Tube Combo 30W',
		'code'     => 'SF-AM-001',
		'price'    => 45000,
		'image'    => 'amp-001.jpg',
		'desc'     => '真空管を使用した30Wのコンボアンプ。',
	),
	array(
		'category' => 'アンプ',
		'name'     => 'Solid State Practice 10W',
		'code'     => 'SF-AM-002',
		'price'    => 9800,
		'image'    => 'amp-002.jpg',
		'desc'     => '自宅練習向けの小型10Wソリッドステートアンプ。',
	),
	array(
		'category' => 'アンプ',
		'name'     => 'Bass Combo 100W',
		'code'     => 'SF-AM-003',
		'price'    => 62000,
		'image'    => 'amp-003.jpg',
		'desc'     => '15インチスピーカー搭載の100Wベースコンボアンプ。',
	),
	array(
		'category' => 'アンプ',
		'name'     => 'Full Stack Head 100W',
		'code'     => 'SF-AM-004',
		'price'    => 198000,
		'image'    => 'amp-004.jpg',
		'desc'     => 'ヘッド+キャビネットのフルスタック構成、100W。',
	),
	array(
		'category' => 'アンプ',
		'name'     => 'Modeling Combo 40W',
		'code'     => 'SF-AM-005',
		'price'    => 38000,
		'image'    => 'amp-005.jpg',
		'desc'     => 'デジタルモデリング搭載の40Wコンボアンプ。',
	),
	array(
		'category' => 'エフェクター',
		'name'     => 'OD-1 Overdrive',
		'code'     => 'SF-EF-001',
		'price'    => 12800,
		'image'    => 'effector-001.jpg',
		'desc'     => '定番のオーバードライブペダル。',
	),
	array(
		'category' => 'エフェクター',
		'name'     => 'Digital Delay DL-2',
		'code'     => 'SF-EF-002',
		'price'    => 15200,
		'image'    => 'effector-002.jpg',
		'desc'     => '多機能なデジタルディレイペダル。',
	),
	array(
		'category' => 'エフェクター',
		'name'     => 'Compact Tuner Pedal',
		'code'     => 'SF-EF-003',
		'price'    => 6800,
		'image'    => 'effector-003.jpg',
		'desc'     => '視認性の高い液晶を備えたコンパクトチューナー。',
	),
	array(
		'category' => 'エフェクター',
		'name'     => 'Multi Effects Processor',
		'code'     => 'SF-EF-004',
		'price'    => 32000,
		'image'    => 'effector-004.jpg',
		'desc'     => '複数エフェクトを内蔵したマルチエフェクター。',
	),
	array(
		'category' => 'エフェクター',
		'name'     => 'Analog Chorus CH-1',
		'code'     => 'SF-EF-005',
		'price'    => 11000,
		'image'    => 'effector-005.jpg',
		'desc'     => 'アナログ回路のコーラスペダル。',
	),
	array(
		'category' => 'ドラム',
		'name'     => 'Maple Snare 14inch',
		'code'     => 'SF-DR-001',
		'price'    => 28000,
		'image'    => 'drum-001.jpg',
		'desc'     => '14インチのメイプルシェルスネアドラム。',
	),
	array(
		'category' => 'ドラム',
		'name'     => '5-Piece Shell Pack',
		'code'     => 'SF-DR-002',
		'price'    => 88000,
		'image'    => 'drum-002.jpg',
		'desc'     => '5点セットのアコースティックドラムシェルパック。',
	),
	array(
		'category' => 'ドラム',
		'name'     => 'Practice Pad Set',
		'code'     => 'SF-DR-003',
		'price'    => 7500,
		'image'    => 'drum-003.jpg',
		'desc'     => '静音練習用のプラクティスパッドセット。',
	),
	array(
		'category' => 'ドラム',
		'name'     => 'Electronic Drum Kit',
		'code'     => 'SF-DR-004',
		'price'    => 62000,
		'image'    => 'drum-004.jpg',
		'desc'     => 'メッシュヘッド採用のコンパクト電子ドラムキット。',
	),
	array(
		'category' => 'ドラム',
		'name'     => 'Bronze Cymbal Set',
		'code'     => 'SF-DR-005',
		'price'    => 34000,
		'image'    => 'drum-005.jpg',
		'desc'     => 'ブロンズ製シンバルのセット。',
	),
);

$wcd_category_ids = array();
foreach ( array( 'ギター', 'ベース', 'アンプ', 'エフェクター', 'ドラム' ) as $wcd_category_name ) {
	$wcd_term = get_term_by( 'name', $wcd_category_name, 'category' );
	if ( ! $wcd_term ) {
		$wcd_result  = wp_insert_term( $wcd_category_name, 'category' );
		$wcd_term_id = is_wp_error( $wcd_result ) ? 0 : $wcd_result['term_id'];
	} else {
		$wcd_term_id = $wcd_term->term_id;
	}
	$wcd_category_ids[ $wcd_category_name ] = $wcd_term_id;
}

$wcd_image_dir = get_stylesheet_directory() . '/assets/img/products/';

/**
 * ローカル画像ファイルをメディアライブラリに登録する。
 * post_title を itemCode と一致させる（Welcartの画像解決ロジックの
 * 旧経路が、itemCode と一致するアタッチメントの post_title を検索するため）。
 *
 * @param int    $post_id 商品の投稿ID。
 * @param string $file_path 画像ファイルの絶対パス。
 * @param string $item_code 品番。
 * @return int|false アタッチメントID。失敗時 false。
 */
function wcd_seed_attach_image( $post_id, $file_path, $item_code ) {
	if ( ! file_exists( $file_path ) ) {
		WP_CLI::warning( "画像が見つかりません: {$file_path}" );
		return false;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$wcd_existing_query = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'title'          => $item_code,
			'posts_per_page' => 1,
		)
	);
	if ( $wcd_existing_query->have_posts() ) {
		return $wcd_existing_query->posts[0]->ID;
	}

	$wcd_filetype   = wp_check_filetype( basename( $file_path ), null );
	$wcd_upload_dir = wp_upload_dir();
	wp_mkdir_p( $wcd_upload_dir['path'] );
	$wcd_destination = trailingslashit( $wcd_upload_dir['path'] ) . basename( $file_path );
	copy( $file_path, $wcd_destination );

	$wcd_attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $wcd_filetype['type'],
			'post_title'     => $item_code,
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$wcd_destination,
		$post_id
	);

	$wcd_attach_data = wp_generate_attachment_metadata( $wcd_attachment_id, $wcd_destination );
	wp_update_attachment_metadata( $wcd_attachment_id, $wcd_attach_data );

	return $wcd_attachment_id;
}

foreach ( $wcd_products as $wcd_product ) {
	$wcd_existing_post_id = wel_get_id_by_item_code( $wcd_product['code'], false );

	if ( $wcd_existing_post_id ) {
		$wcd_post_id = $wcd_existing_post_id;
		wp_update_post(
			array(
				'ID'           => $wcd_post_id,
				'post_title'   => $wcd_product['name'],
				'post_content' => $wcd_product['desc'],
			)
		);
	} else {
		$wcd_post_id = wp_insert_post(
			array(
				'post_type'      => 'post',
				'post_mime_type' => 'item',
				'post_status'    => 'publish',
				'post_title'     => $wcd_product['name'],
				'post_content'   => $wcd_product['desc'],
			)
		);
	}

	if ( is_wp_error( $wcd_post_id ) || ! $wcd_post_id ) {
		WP_CLI::warning( "投入失敗: {$wcd_product['code']}" );
		continue;
	}

	wp_set_object_terms( $wcd_post_id, array( $wcd_category_ids[ $wcd_product['category'] ] ), 'category' );

	$wcd_attachment_id = wcd_seed_attach_image( $wcd_post_id, $wcd_image_dir . $wcd_product['image'], $wcd_product['code'] );

	wel_update_item_data(
		array(
			'itemCode'            => $wcd_product['code'],
			'itemName'            => $wcd_product['name'],
			'itemShipping'        => 0,
			'itemOrderAcceptable' => 0,
			'item_division'       => 'shipped',
			// 配送方法（Welcart管理画面 > 配送設定 の既定値、id=0「通常配送」）を
			// 明示しないと usc_e_shop::getItemDeliveryMethod() が空配列を返し、
			// get_available_delivery_method() がこの商品をカートから除外してしまう
			// （配送・支払い方法選択画面で配送方法の選択肢が空になり先に進めなくなる）。
			// 実機検証（タスク20）で発見。
			'itemDeliveryMethod'  => array( 0 ),
			'itemPicts'           => $wcd_attachment_id ? array( $wcd_attachment_id ) : array(),
		),
		$wcd_post_id
	);

	$wcd_sku_data = array(
		'code'     => $wcd_product['code'],
		'name'     => $wcd_product['name'],
		'price'    => $wcd_product['price'],
		'stock'    => 0,
		'stocknum' => 10,
		'unit'     => 1,
		'pict_id'  => $wcd_attachment_id ? $wcd_attachment_id : 0,
		'sort'     => 0,
	);

	$wcd_existing_skus = wel_get_skus( $wcd_post_id, 'code', false );

	if ( $wcd_existing_skus && isset( $wcd_existing_skus[ $wcd_product['code'] ] ) ) {
		$wcd_sku_data['meta_id'] = $wcd_existing_skus[ $wcd_product['code'] ]['meta_id'];
		wel_update_sku_data_by_id( $wcd_sku_data['meta_id'], $wcd_post_id, $wcd_sku_data );
	} else {
		wel_add_sku_data( $wcd_post_id, $wcd_sku_data );
	}

	WP_CLI::success( "投入完了: {$wcd_product['code']} {$wcd_product['name']}" );
}
