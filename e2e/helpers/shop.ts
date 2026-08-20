import { Page, expect } from '@playwright/test'
import { getLatestOrderId } from './wpcli'

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

/**
 * 購入フローで入力する検証用の顧客情報。
 *
 * 実測メモ: お客様情報フォームは「姓名」の2分割に加え、住所が
 * `pref`（都道府県セレクト）／`address1`（市区郡町村）／`address2`（番地）の
 * 3分割になっており、計画書が想定していた単一の `address1` では埋まらない
 * （`address2` は必須項目「＊番地」）。メールアドレスも確認用の
 * `mailaddress2` が別途必須。
 */
export const TEST_CUSTOMER = {
  name1: 'テスト',
  name2: '太郎',
  zipcode: '1000001',
  pref: '東京都',
  address1: '千代田区千代田',
  address2: '1-1',
  tel: '0312345678',
  email: 'e2e-buyer@example.com',
} as const

/**
 * カート画面から購入を完了し、受注 ID を返す。
 *
 * カート → お客様情報 → 配送・支払方法 → 確認 → 完了 の5画面を通す。
 *
 * 実測メモ（計画書のコード例からの主な変更点）:
 * - 各画面の「次へ」ボタンは `nextpage` という共通 name ではなく、
 *   画面ごとに異なる name を持つ（カート→お客様情報: `customerinfo`、
 *   お客様情報→配送: `deliveryinfo`、配送→確認: `confirm`、
 *   確認→完了: `purchase`）。実際にカートから購入完了まで進めて確認した。
 * - お客様情報画面には「会員ログイン」フォームと「ゲスト購入」フォームの
 *   2つが同居する。`customer[...]` という name はゲスト側にしか存在しないため
 *   セレクタの一意性は問題ない（実ページで確認済み）。
 * - 配送・支払方法は初期選択（配送先=お客様情報と同じ／支払方法=銀行振込）が
 *   既に要件を満たすため、値の変更は行わない。
 */
export async function completePurchase(page: Page): Promise<{ orderId: number; confirmDiscount: number }> {
  await page.goto(CART_URL)
  await page.locator('input[name="customerinfo"]').click()
  await page.waitForLoadState('networkidle')

  // お客様情報（ゲスト購入フォーム）。
  await page.fill('input[name="customer[mailaddress1]"]', TEST_CUSTOMER.email)
  await page.fill('input[name="customer[mailaddress2]"]', TEST_CUSTOMER.email)
  await page.fill('input[name="customer[name1]"]', TEST_CUSTOMER.name1)
  await page.fill('input[name="customer[name2]"]', TEST_CUSTOMER.name2)
  await page.fill('input[name="customer[zipcode]"]', TEST_CUSTOMER.zipcode)
  await page.selectOption('select[name="customer[pref]"]', TEST_CUSTOMER.pref)
  await page.fill('input[name="customer[address1]"]', TEST_CUSTOMER.address1)
  await page.fill('input[name="customer[address2]"]', TEST_CUSTOMER.address2)
  await page.fill('input[name="customer[tel]"]', TEST_CUSTOMER.tel)
  await page.locator('input[name="deliveryinfo"]').click()
  await page.waitForLoadState('networkidle')

  // 配送・支払方法（既定の選択のまま進む）。
  await page.locator('input[name="confirm"]').click()
  await page.waitForLoadState('networkidle')

  // 確認画面: 割引額を読む。
  const confirmDiscount = await readConfirmDiscount(page)

  // 確定。
  await page.locator('input[name="purchase"]').click()
  await page.waitForLoadState('networkidle')

  const orderId = await readCompletionOrderId(page)
  return { orderId, confirmDiscount }
}

/**
 * 購入確認画面の割引額を返す。Welcart 本体が `tr.discount` として描画する
 * （`usc-e-shop/templates/cart/confirm.php`）。金額セルは `td.totalend` で
 * 一意に取れることを実ページで確認済み（ラベル側セルは `td.totallabel`）。
 */
export async function readConfirmDiscount(page: Page): Promise<number> {
  const row = page.locator('tr.discount')
  await expect(row).toHaveCount(1)
  return parseAmount(await row.locator('td.totalend').innerText())
}

/**
 * 完了画面から受注 ID を取り出す。
 *
 * 実測メモ: 計画書は完了画面の本文テキストを正規表現で読み取る方式を
 * 想定していたが、実際の完了画面（検証用テーマ
 * `wc_templates/cart/wc_completion_page.php`）は Welcart 本体の汎用完了
 * テンプレートをそのまま使っており、「送信が完了しました。お買い上げ
 * ありがとうございました。」という定型文以外に受注番号を一切含まない
 * （他の数値と誤認する以前に、そもそも数値が存在しない）。
 * そのため完了画面に到達したことだけを見出しで確認し、受注 ID は
 * WP-CLI 経由で受注テーブルから直接取得する（`getLatestOrderId()`）。
 */
export async function readCompletionOrderId(page: Page): Promise<number> {
  await expect(page.locator('main.wcd-shop-page--completion')).toBeVisible()
  return getLatestOrderId()
}
