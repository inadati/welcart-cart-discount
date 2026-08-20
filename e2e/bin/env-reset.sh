#!/usr/bin/env bash
#
# E2E の前提となる既知の状態へ戻す。
#
# 戻すのは wcd_settings / wcd_exclusions の2つの option のみ。
# テストが作成した受注は削除しない（docs/verification.md に記録済みの
# 既存受注を巻き込む事故を避けるため）。

set -euo pipefail

cd "$(dirname "$0")/../.."

COMPOSE=(docker compose -f docker/docker-compose.yml)

wp() {
	"${COMPOSE[@]}" run --rm -T wpcli wp --path=/var/www/html "$@"
}

echo "==> 割引ルールを既知の状態にする（10,000円以上-500円 / 30,000円以上-2,000円）"
wp option update wcd_settings \
	'[{"threshold":10000,"amount":500},{"threshold":30000,"amount":2000}]' \
	--format=json

echo "==> 除外設定を空にする"
wp option update wcd_exclusions '{"ranks":[],"categories":[]}' --format=json

echo "==> リセット完了"
