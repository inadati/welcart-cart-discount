#!/usr/bin/env bash
#
# 検証環境を停止し、ボリュームごと破棄する。
# 次回の env-up.sh はまっさらな状態から作り直す。

set -euo pipefail

cd "$(dirname "$0")/../.."

docker compose -f docker/docker-compose.yml down -v

echo "==> 検証環境を破棄しました"
