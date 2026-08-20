import { test, expect, Page } from '@playwright/test'
import { loginAsAdmin } from '../helpers/admin'
import { resetToKnownState } from '../helpers/wpcli'

const SETTINGS_URL = '/wp-admin/admin.php?page=wcd_settings'

test.describe.configure({ mode: 'serial' })

test.describe('管理画面からの設定保存', () => {
  // 01-cart-display.spec.ts と同じ理由（`page` フィクスチャは呼び出しごとに
  // 新規コンテキストになりログインセッションを引き継がない）で、
  // この describe 内の1件目は beforeAll で作った単一 page を使い回す。
  let page: Page

  test.beforeAll(async ({ browser }) => {
    page = await browser.newPage()
  })

  test.afterAll(async () => {
    resetToKnownState()
    await page.close()
  })

  test('管理画面で保存したしきい値と割引額が再表示される', async () => {
    await loginAsAdmin(page)
    await page.goto(SETTINGS_URL)

    await page.fill('input[name="wcd_rules[0][threshold]"]', '12000')
    await page.fill('input[name="wcd_rules[0][amount]"]', '700')

    await page.locator('#submit').click()
    await page.waitForLoadState('networkidle')

    // 保存後の再表示で値が保持されている。
    await page.goto(SETTINGS_URL)
    await expect(page.locator('input[name="wcd_rules[0][threshold]"]')).toHaveValue('12000')
    await expect(page.locator('input[name="wcd_rules[0][amount]"]')).toHaveValue('700')
  })

  // 未ログイン状態のアクセス拒否は、1件目のログイン済みセッションと混ざっては
  // 意味がないため、意図的に新規ブラウザコンテキスト（cookie 未保持）を作る。
  test('ログインしていないと設定画面を開けない', async ({ browser }) => {
    const context = await browser.newContext()
    const freshPage = await context.newPage()

    await freshPage.goto(SETTINGS_URL)

    // wp-login.php へリダイレクトされる。
    expect(freshPage.url()).toContain('wp-login.php')
    await context.close()
  })
})
