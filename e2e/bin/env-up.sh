#!/usr/bin/env bash
#
# 検証環境を「テストが実行できる状態」まで持っていく。冪等。
# 既に整っている場合は何もせず即座に戻る。

set -euo pipefail

cd "$(dirname "$0")/../.."

WP_PORT="${WP_PORT:-8080}"
COMPOSE=(docker compose -f docker/docker-compose.yml)

wp() {
	"${COMPOSE[@]}" run --rm -T wpcli wp --path=/var/www/html "$@"
}

if ! "${COMPOSE[@]}" ps --status running --services 2>/dev/null | grep -qx wordpress; then
	echo "==> コンテナを起動"
	"${COMPOSE[@]}" up -d --build
fi

if ! wp core is-installed >/dev/null 2>&1; then
	echo "==> WordPress が未インストール。docker/setup.sh を実行"
	WP_PORT="${WP_PORT}" ./docker/setup.sh
	exit 0
fi

# テーマディレクトリの中身が空になる状態異常（2026-08-19 に発生）を検出する。
# 自動復旧すると原因が隠れるため、対処方法を示して停止する。
if ! wp theme is-installed welcart-shop-theme >/dev/null 2>&1; then
	echo "検証環境の状態が異常です: テーマ welcart-shop-theme が見つかりません。" >&2
	echo "docker/README の復旧手順、または ./e2e/bin/env-down.sh で作り直してください。" >&2
	exit 1
fi

# Welcart の商品は独立した投稿タイプ（usces_item 等）ではなく、
# post_type=post に post_mime_type=item を付与した投稿として保存される
# （docker/seed-items.php:302-311）。また `wp post list --post_mime_type=item` は
# フィルタとして機能しない（Welcart 側の pre_get_posts フックが介在するとみられ、
# 実機検証で確認済み）。そのため $wpdb 経由の直接カウントで数える。
ITEM_COUNT="$(wp eval 'global $wpdb; echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=\"post\" AND post_mime_type=\"item\" AND post_status=\"publish\"" );')"
if [ "${ITEM_COUNT}" -lt 25 ]; then
	echo "==> 商品が ${ITEM_COUNT} 点しかない。docker/setup.sh を実行して投入"
	WP_PORT="${WP_PORT}" ./docker/setup.sh
	ITEM_COUNT="$(wp eval 'global $wpdb; echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=\"post\" AND post_mime_type=\"item\" AND post_status=\"publish\"" );')"
fi

echo "==> 検証環境は準備済み（商品 ${ITEM_COUNT} 点）"
