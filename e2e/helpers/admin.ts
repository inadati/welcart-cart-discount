import { Page } from '@playwright/test'
import { parseAmount } from './shop'

export const ADMIN_USER = process.env.ADMIN_USER ?? 'admin'
export const ADMIN_PASS = process.env.ADMIN_PASS ?? 'admin'

/** 管理画面にログインする。 */
export async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/wp-login.php')
  await page.fill('#user_login', ADMIN_USER)
  await page.fill('#user_pass', ADMIN_PASS)
  await page.click('#wp-submit')
  await page.waitForURL('**/wp-admin/**')
}

/**
 * 受注編集画面を開く。
 *
 * 実測メモ: 実装計画は `?page=usces_orderlist&order_action=edit&order_id=<ID>` へ
 * 直接 goto する方式を想定していたが、実測すると nonce（`wc_nonce`）が無いと
 * Welcart 側のディスパッチャがリクエストを受け付けず、受注リスト画面が
 * 表示されるだけで編集画面には遷移しない
 * （`title` が「受注リスト」のままで `#order_discount` が存在しないことを確認済み）。
 *
 * `wp_create_nonce()` はログインセッションのセッショントークンを鍵の一部に含むため、
 * WP-CLI 側で生成した nonce をブラウザのログインセッションに対して使うことはできない。
 * そのため、受注リスト画面を開いて実際に描画されたリンク（本物の nonce 付き）を
 * href から取得し、それへ遷移する方式にした。
 *
 * href の部分一致（`*=`）で `order_id` を絞り込むと、例えば `order_id=101` が
 * `order_id=1014` にも一致してしまう（CSS 属性セレクタは部分文字列一致のため）。
 * それを避けるため、`URL#searchParams` で `order_id` を厳密に比較する。
 */
export async function openOrderEdit(page: Page, orderId: number): Promise<void> {
  await page.goto('/wp-admin/admin.php?page=usces_orderlist')

  const links = page.locator('a[href*="order_action=edit"]')
  const count = await links.count()
  let target: string | null = null
  for (let i = 0; i < count; i += 1) {
    const href = await links.nth(i).getAttribute('href')
    if (!href) {
      continue
    }
    const url = new URL(href, page.url())
    if (url.searchParams.get('order_id') === String(orderId)) {
      target = href
      break
    }
  }

  if (!target) {
    throw new Error(`受注リストに受注 ID ${orderId} への編集リンクが見つかりません`)
  }

  await page.goto(target)
  await page.waitForLoadState('networkidle')
}

/**
 * 受注編集画面の割引欄（`#order_discount`、`name="offer[discount]"`）の値を
 * 数値で返す（絶対値）。
 *
 * 実測メモ: 値は `-500` のように負値で表示される
 * （`usc-e-shop/includes/order_edit_form.php:2069, 2078`）。`parseAmount()` は
 * 数字以外を除去するため、符号の有無にかかわらず絶対値が取れる。
 */
export async function readOrderDiscount(page: Page): Promise<number> {
  return parseAmount(await page.locator('#order_discount').inputValue())
}

/**
 * 受注編集画面の「再計算」ボタン（`input#recalc`）を押し、再計算の完了を待つ。
 *
 * 実測メモ: 再計算は `orderfunc.recalculation()`
 * （`usc-e-shop/includes/order_edit_form.php:840-993`）が発火する ajax
 * （`action=order_item_ajax` を `wp-admin/admin-ajax.php` へ POST）であり、
 * その `.done()` コールバックが `#order_discount` の値を書き換える。
 * 固定の待ち時間ではなく、`admin-ajax.php` へのレスポンスを実際に待つ方式にした
 * （実機確認: レスポンス受信時点で `#order_discount` は既に更新済みだったが、
 * 念のため描画の猶予を待つ）。
 */
export async function clickRecalculation(page: Page): Promise<void> {
  const [response] = await Promise.all([
    page.waitForResponse((res) => res.url().includes('admin-ajax.php')),
    page.locator('input#recalc').click(),
  ])

  if (!response.ok()) {
    throw new Error(`再計算の ajax リクエストが失敗しました: status=${response.status()}`)
  }

  await page.waitForTimeout(300)
}
