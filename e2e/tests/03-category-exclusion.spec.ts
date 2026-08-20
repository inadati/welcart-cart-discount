import { test, expect, Page } from '@playwright/test'
import { ITEMS, CART_URL, emptyCart, addToCart, readCartDiscount } from '../helpers/shop'
import { resetToKnownState, setExclusions, getCategoryId } from '../helpers/wpcli'

test.describe.configure({ mode: 'serial' })

test.describe('除外カテゴリによる部分除外', () => {
  // Welcart のカートはセッション（cookie）で保持される。01-cart-display.spec.ts と
  // 同じ理由で、同一 page を beforeAll で作り serial 実行の3件で使い回す。
  let page: Page
  let effectorCategoryId = 0

  test.beforeAll(async ({ browser }) => {
    resetToKnownState()
    effectorCategoryId = getCategoryId('エフェクター')
    page = await browser.newPage()
  })

  // 他の spec へ状態を漏らさない。
  test.afterAll(async () => {
    resetToKnownState()
    await page.close()
  })

  test('除外なしなら除外対象カテゴリの商品も合算されて割引される', async () => {
    setExclusions({ ranks: [], categories: [] })

    await emptyCart(page)
    await addToCart(page, ITEMS.overdrive.name) // エフェクター ¥12,800
    await addToCart(page, ITEMS.practicePad.name) // ドラム ¥7,500 / 合計 ¥20,300

    await page.goto(CART_URL)
    expect(await readCartDiscount(page)).toBe(500)
  })

  test('エフェクターを除外すると対象小計が下がり割引が消える', async () => {
    setExclusions({ ranks: [], categories: [effectorCategoryId] })

    // 合計は ¥20,300 のままだが、対象小計は ¥7,500 となりしきい値未満。
    await page.goto(CART_URL)
    expect(await readCartDiscount(page)).toBeNull()
  })

  test('除外により適用される段が下がる', async () => {
    setExclusions({ ranks: [], categories: [] })

    await emptyCart(page)
    await addToCart(page, ITEMS.mapleSnare.name) // ドラム ¥28,000
    await addToCart(page, ITEMS.overdrive.name) // エフェクター ¥12,800 / 合計 ¥40,800

    await page.goto(CART_URL)
    expect(await readCartDiscount(page)).toBe(2000)

    setExclusions({ ranks: [], categories: [effectorCategoryId] })

    // 対象小計 ¥28,000 は2段目に届かず1段目に落ちる。全面停止ではない。
    await page.goto(CART_URL)
    expect(await readCartDiscount(page)).toBe(500)
  })
})
