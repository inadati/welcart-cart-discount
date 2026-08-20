#!/usr/bin/env bash
#
# E2E の統合入口。
# 環境の状態を検査して不足分を用意し、既知の状態へ戻してからテストを実行する。

set -euo pipefail

cd "$(dirname "$0")/../.."

./e2e/bin/env-up.sh
./e2e/bin/env-reset.sh

echo "==> Playwright を実行"
cd e2e

if [ ! -d node_modules ]; then
	echo "==> 依存が未インストール。npm install を実行"
	npm install
	npx playwright install chromium
fi

npx playwright test "$@"
