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

/** PHP のシングルクォート文字列リテラルとして安全な形にエスケープする。 */
function escapePhpSingleQuoted(value: string): string {
  return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")
}

/**
 * 商品名から投稿 ID を取得する。
 *
 * 実測して判明した事実: Welcart の商品は独立した投稿タイプ（`usces_item` 等）
 * ではなく、`post_type=post` に `post_mime_type=item` を付与した投稿として
 * 保存される（`docker/seed-items.php`、`e2e/bin/env-up.sh` のコメント参照）。
 * `wp post list --post_mime_type=item --title=...` は Welcart 側の
 * pre_get_posts フックが介在するとみられ、フィルタとして機能しないことを
 * 実機で確認済み（`--title` 指定でも 0 件になる）。そのため `getCategoryId()`
 * のような `wp term list` 相当の単純な CLI 検索は使えず、`$wpdb` を直接叩く
 * `wp eval` 経由で取得する。
 *
 * 直値の投稿 ID をハードコードしていた旧実装は、まっさらな環境（採番が
 * 異なる）で商品ページに遷移できず spec 全体が失敗する欠陥があった。
 * 商品名から都度解決することで、投稿 ID の採番に依存しなくなる。
 */
export function getItemPostId(name: string): number {
  const escapedName = escapePhpSingleQuoted(name)
  const output = wp(
    'eval',
    'global $wpdb; echo (int) $wpdb->get_var( $wpdb->prepare(' +
      ' "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_mime_type=%s AND post_status=%s AND post_title=%s",' +
      ` 'post', 'item', 'publish', '${escapedName}' ) );`,
  )
  const id = Number(output)
  if (!Number.isInteger(id) || id <= 0) {
    throw new Error(`商品が見つかりません: ${name}`)
  }
  return id
}

/**
 * 商品名から商品ページの URL（`?p=<投稿ID>` 形式）を返す。
 *
 * この検証環境のパーマリンク設定は「基本」（プレーン）であるため
 * `/?p=<ID>` で商品ページへ直接遷移できる（`e2e/helpers/shop.ts` の
 * `CART_URL` の実測メモと同じ前提）。
 */
export function getItemUrl(name: string): string {
  return `/?p=${getItemPostId(name)}`
}

/**
 * 直近に作成された受注 ID を取得する。
 *
 * 実測して判明した事実: 検証用テーマの購入完了画面
 * （`docker/theme/welcart-shop-theme/wc_templates/cart/wc_completion_page.php`）は
 * Welcart 本体の汎用完了テンプレート（`usc-e-shop/templates/cart/completion.php`）を
 * そのまま `require` しているだけで、受注番号を一切表示しない。
 * そのため完了画面の文字列から受注 ID を読み取ることはできず、
 * 受注テーブル（`wp_usces_order`）へ直接問い合わせる。
 *
 * `wp db query` は本検証環境では `TLS/SSL error` で失敗するため、
 * WordPress の DB 抽象化層を使う `wp eval` 経由で取得する。
 */
export function getLatestOrderId(): number {
  const output = wp(
    'eval',
    'global $wpdb; echo (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->prefix}usces_order");',
  )
  const id = Number(output)
  if (!Number.isInteger(id) || id <= 0) {
    throw new Error(`受注IDを取得できません: ${output}`)
  }
  return id
}
