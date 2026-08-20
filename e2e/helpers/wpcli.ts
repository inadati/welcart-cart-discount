import { execFileSync } from 'node:child_process'
import * as path from 'node:path'

const REPO_ROOT = path.resolve(__dirname, '..', '..')
const COMPOSE_FILE = path.join(REPO_ROOT, 'docker', 'docker-compose.yml')

/** WP-CLI を docker compose 経由で実行し、標準出力を返す。 */
export function wp(...args: string[]): string {
  return execFileSync(
    'docker',
    ['compose', '-f', COMPOSE_FILE, 'run', '--rm', '-T', 'wpcli', 'wp', '--path=/var/www/html', ...args],
    { cwd: REPO_ROOT, encoding: 'utf8' },
  ).trim()
}

/**
 * 割引ルールを option に直接書き込む。
 *
 * キーは threshold / amount。`discount` ではない
 * （includes/class-wcd-settings.php:56-59 の normalize() が返す形式）。
 */
export function setDiscountRules(rules: Array<{ threshold: number; amount: number }>): void {
  wp('option', 'update', 'wcd_settings', JSON.stringify(rules), '--format=json')
}

/** 除外設定を option に直接書き込む。 */
export function setExclusions(exclusions: { ranks: Array<number | string>; categories: number[] }): void {
  wp('option', 'update', 'wcd_exclusions', JSON.stringify(exclusions), '--format=json')
}

/** カテゴリ名から term_id を引く。 */
export function getCategoryId(name: string): number {
  const id = wp('term', 'list', 'category', `--name=${name}`, '--field=term_id')
  if (!id) {
    throw new Error(`カテゴリが見つかりません: ${name}`)
  }
  return Number(id)
}

/** 設計書が定める既知の状態（2段ルール・除外なし）へ戻す。 */
export function resetToKnownState(): void {
  setDiscountRules([
    { threshold: 10000, amount: 500 },
    { threshold: 30000, amount: 2000 },
  ])
  setExclusions({ ranks: [], categories: [] })
}
