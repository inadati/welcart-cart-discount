import { test, expect, Page } from '@playwright/test'
import { ITEMS, CART_URL, emptyCart, addToCart, readCartDiscount, completePurchase } from '../helpers/shop'
import { loginAsAdmin, openOrderEdit, readOrderDiscount, clickRecalculation } from '../helpers/admin'
import { resetToKnownState } from '../helpers/wpcli'

test.describe.configure({ mode: 'serial' })

test.describe('割引の3箇所整合と受注再計算のべき等性', () => {
  // 01-cart-display.spec.ts と同じ理由で単一 page を使い回す。
  // 加えてこの spec では、フロント（ゲスト購入）とバックエンド（管理画面ログイン）の
  // 両方を同一 page で行き来する。購入完了後にカートは空になっており、
  // 管理画面ログインがフロント側のゲスト購入セッションを壊すことはない
  // （実機確認済み）。
  let page: Page
  let orderId = 0
  let cartDiscount = 0

  test.beforeAll(async ({ browser }) => {
    resetToKnownState()
    page = await browser.newPage()
  })

  test.afterAll(async () => {
    await page.close()
  })

  test('カート画面と購入確認画面で割引額が一致し、購入を完了できる', async () => {
    await emptyCart(page)
    await addToCart(page, ITEMS.practicePad.name) // ¥7,500
    await addToCart(page, ITEMS.tunerPedal.name) // 合計 ¥14,300

    await page.goto(CART_URL)
    cartDiscount = (await readCartDiscount(page)) ?? 0
    expect(cartDiscount).toBe(500)

    const result = await completePurchase(page)
    orderId = result.orderId

    // 1箇所目（カート画面）と2箇所目（購入確認画面）の整合。
    expect(result.confirmDiscount).toBe(cartDiscount)
    expect(orderId).toBeGreaterThan(0)
  })

  test('確定後の受注データにも同じ割引額が記録されている', async () => {
    await loginAsAdmin(page)
    await openOrderEdit(page, orderId)

    // 3箇所目（受注データ）の整合。
    expect(await readOrderDiscount(page)).toBe(cartDiscount)
  })

  test('再計算を3回繰り返しても割引額が変動しない（二重計上の非再発）', async () => {
    // 前のテストで既に受注編集画面を開いた状態だが、spec 単独実行時にも
    // 成立するよう改めて開き直す。
    await openOrderEdit(page, orderId)

    const before = await readOrderDiscount(page)
    expect(before).toBe(cartDiscount)

    for (let i = 1; i <= 3; i += 1) {
      await clickRecalculation(page)
      const after = await readOrderDiscount(page)
      // -500 が -1,000 → -1,500 と積み上がる二重計上が起きないこと。
      expect(after, `${i} 回目の再計算後`).toBe(before)
    }
  })
})
