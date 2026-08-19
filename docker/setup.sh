#!/usr/bin/env bash
#
# 検証環境のワンショット初期化スクリプト。
#
# docker compose up の後にこれを1回実行すると、WordPress のインストールから
# Welcart・本プラグインの有効化、日本向け設定までを済ませた状態になる。
# 何度実行しても同じ結果になる（冪等）。
#
# 使い方:
#   docker compose -f docker/docker-compose.yml up -d --build
#   ./docker/setup.sh
#
# ポートを変えている場合は WP_PORT を合わせる:
#   WP_PORT=8090 ./docker/setup.sh

set -euo pipefail

cd "$(dirname "$0")/.."

WP_PORT="${WP_PORT:-8080}"
SITE_URL="http://localhost:${WP_PORT}"
SITE_TITLE="${SITE_TITLE:-Welcart Cart Discount 検証環境}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"

COMPOSE=(docker compose -f docker/docker-compose.yml)

wp() {
	"${COMPOSE[@]}" run --rm -T wpcli wp --path=/var/www/html "$@"
}

echo "==> WordPress の起動を待機中..."
for _ in $(seq 1 60); do
	if wp core is-installed >/dev/null 2>&1 || wp core version >/dev/null 2>&1; then
		break
	fi
	sleep 2
done

if wp core is-installed >/dev/null 2>&1; then
	echo "==> WordPress は既にインストール済み"
else
	echo "==> WordPress をインストール"
	wp core install \
		--url="${SITE_URL}" \
		--title="${SITE_TITLE}" \
		--admin_user="${ADMIN_USER}" \
		--admin_password="${ADMIN_PASS}" \
		--admin_email="${ADMIN_EMAIL}" \
		--skip-email
fi

echo "==> 管理画面の言語を日本語にする"
wp language core install ja --activate >/dev/null 2>&1 || true

echo "==> Welcart（usc-e-shop）を有効化"
wp plugin activate usc-e-shop

echo "==> welcart-cart-discount を有効化"
wp plugin activate welcart-cart-discount

# Welcart の既定値は米国向け（英語・USD・US住所形式・国際便）のため、
# 日本向けに寄せておく。いずれも Welcart 本体の設定項目であり、
# 本プラグインの動作条件ではない（通貨記号や言語が変わるだけ）。
# ここを設定しないと金額が $ 表示になり、README のスクリーンショットと食い違う。
echo "==> Welcart を日本向け設定にする"
wp eval '
$opt = get_option( "usces", array() );
$opt["system"]["currency"]      = "JP";
$opt["system"]["front_lang"]    = "ja";
$opt["system"]["addressform"]   = "JP";
$opt["system"]["target_market"] = array( "JP" );
if ( isset( $opt["delivery_method"] ) && is_array( $opt["delivery_method"] ) ) {
	foreach ( $opt["delivery_method"] as $i => $m ) {
		$opt["delivery_method"][ $i ]["intl"] = "0";
	}
}
update_option( "usces", $opt );
'

echo
echo "完了しました。"
echo "  サイト        : ${SITE_URL}"
echo "  管理画面      : ${SITE_URL}/wp-admin/"
echo "  ユーザー/パス : ${ADMIN_USER} / ${ADMIN_PASS}"
echo "  割引設定      : ${SITE_URL}/wp-admin/admin.php?page=wcd_settings"
echo
echo "商品が未登録の場合は、管理画面の Welcart Shop から商品を追加してください。"
