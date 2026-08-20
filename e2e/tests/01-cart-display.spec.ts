import { test, expect, Page } from '@playwright/test'
import { ITEMS, CART_URL, emptyCart, addToCart, readCartDiscount, readCartDiscountedTotal } from '../helpers/shop'
import { resetToKnownState } from '../helpers/wpcli'

test.describe.configure({ mode: 'serial' })

test.describe('カート画面の割引表示', () => {
  // Welcart のカートはセッション（cookie）で保持される。Playwright の `page`
  // フィクスチャはテストごとに新しいブラウザコンテキスト（cookie 未保持）を
  // 生成するため、3件のテストで素朴に `{ page }` を受け取ると2件目以降で
  // カートが空になる（実測して判明）。同一 page を beforeAll で作り、
  // serial 実行の3件で使い回すことでカートの積み増しを成立させる。
  let page: Page

  test.beforeAll(async ({ browser }) => {
    resetToKnownState()
    page = await browser.newPage()
  })

  test.afterAll(async () => {
    await page.close()
  })

  test('しきい値未満では割引行が出ない', async () => {
    await emptyCart(page)
    await addToCart(page, ITEMS.practicePad.name) // ¥7,500

    await page.goto(CART_URL)
    expect(await readCartDiscount(page)).toBeNull()
    expect(await readCartDiscountedTotal(page)).toBeNull()
  })

  test('1段目に到達すると -500 と割引後合計が出る', async () => {
    await addToCart(page, ITEMS.tunerPedal.name) // 合計 ¥14,300

    await page.goto(CART_URL)
    expect(await readCartDiscount(page)).toBe(500)
    expect(await readCartDiscountedTotal(page)).toBe(13800)
  })

  test('2段目に到達すると -2000 に切り替わり、累積しない', async () => {
    await addToCart(page, ITEMS.mapleSnare.name) // 合計 ¥42,300

    await page.goto(CART_URL)
    const discount = await readCartDiscount(page)

    // 最上位1段のみ適用される。500 + 2000 = 2500 にならないことが要点。
    expect(discount).toBe(2000)
    expect(discount).not.toBe(2500)
    expect(await readCartDiscountedTotal(page)).toBe(40300)
  })
})
