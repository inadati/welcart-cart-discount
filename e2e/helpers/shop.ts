import { Page } from '@playwright/test'

/**
 * 検証環境の商品25点のうち、E2E が使う4点。
 *
 * 価格・カテゴリは `docker/seed-items.php` の実データ。`url` は投稿 ID から
 * 実測して確定した値（下記「実測メモ」参照）。
 */
export const ITEMS = {
  practicePad: { name: 'Practice Pad Set', price: 7500, category: 'ドラム', url: '/?p=181' },
  tunerPedal: { name: 'Compact Tuner Pedal', price: 6800, category: 'エフェクター', url: '/?p=171' },
  overdrive: { name: 'OD-1 Overdrive', price: 12800, category: 'エフェクター', url: '/?p=167' },
  mapleSnare: { name: 'Maple Snare 14inch', price: 28000, category: 'ドラム', url: '/?p=177' },
} as const

const ITEM_LIST = Object.values(ITEMS)

/**
 * カート画面の URL。
 *
 * 実測メモ: 設計書・実装計画は `/usces-cart/` を想定していたが、この検証環境の
 * パーマリンク設定は「基本」（プレーン）であり `/usces-cart/` は 404 になる
 * （`curl -o /dev/null -w '%{http_code}' http://localhost:8080/usces-cart/` → 404）。
 * カートページの実体は固定ページで、そのページ ID は option `usces_cart_number`
 * が保持している。この検証環境では 5（`/?page_id=5` → 200 を確認済み）。
 */
export const CART_URL = '/?page_id=5'

/** カートを空にする。各 spec の開始時に呼び、前の spec の中身を持ち越さない。 */
export async function emptyCart(page: Page): Promise<void> {
  await page.goto(CART_URL)
  const deleteButtons = page.locator('input[name^="delButton"]')
  while ((await deleteButtons.count()) > 0) {
    await deleteButtons.first().click()
    await page.waitForLoadState('networkidle')
  }
}

/**
 * 商品名からカートに投入する。
 *
 * 実測メモ: 実装計画はトップページから商品名リンクを探してクリックする方式を
 * 想定していたが、実測するとトップページ（フロントページ）には25商品中6点しか
 * 一覧表示されず、Practice Pad Set 等はそこに現れない
 * （`curl http://localhost:8080/` の `product-card__name` を数えると6件のみ）。
 * そのためリンク探索はやめ、ITEMS に持たせた商品ページ URL（`?p=<投稿ID>`）へ
 * 直接遷移する方式に変更した。
 *
 * 「カートに入れる」ボタンの name 属性 `inCart[...]` は実ページで確認済み
 * （`usces_the_itemSkuButton()` が生成する）。
 */
export async function addToCart(page: Page, itemName: string): Promise<void> {
  const item = ITEM_LIST.find((candidate) => candidate.name === itemName)
  if (!item) {
    throw new Error(`ITEMS に無い商品名です: ${itemName}`)
  }
  await page.goto(item.url)
  await page.locator('input[name^="inCart"]').click()
  await page.waitForLoadState('networkidle')
}

/**
 * カート画面の割引行から金額セルを読む。
 *
 * 実測メモ: 割引行は `<tr class="wcd-discount-row"><td>&nbsp;</td><td>&nbsp;</td>
 * <td colspan="3">自動割引</td><td class="aright subtotal">-¥500</td>
 * <td>&nbsp;</td><td>&nbsp;</td></tr>` という構造で、金額セルは6つある `<td>` の
 * うち4番目（`nth(1)` ではない）。列数の変化に左右されないよう、
 * 金額セルが一意に持つ `td.subtotal` クラスで特定する。
 */
async function readAmountRow(page: Page, rowSelector: string): Promise<number | null> {
  const row = page.locator(rowSelector)
  if ((await row.count()) === 0) {
    return null
  }
  return parseAmount(await row.locator('td.subtotal').innerText())
}

/** カート画面の割引額を数値で返す。割引行が無ければ null。 */
export async function readCartDiscount(page: Page): Promise<number | null> {
  return readAmountRow(page, 'tr.wcd-discount-row')
}

/** カート画面の割引後合計を数値で返す。 */
export async function readCartDiscountedTotal(page: Page): Promise<number | null> {
  return readAmountRow(page, 'tr.wcd-discounted-total-row')
}

/** 「-¥2,000」「¥13,800」等の表示文字列から絶対値の数値を取り出す。 */
export function parseAmount(text: string): number {
  const digits = text.replace(/[^0-9]/g, '')
  if (digits === '') {
    throw new Error(`金額を読み取れません: ${text}`)
  }
  return Number(digits)
}
