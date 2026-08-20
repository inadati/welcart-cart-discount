import { defineConfig } from '@playwright/test'

const WP_PORT = process.env.WP_PORT ?? '8080'

export default defineConfig({
  testDir: './tests',
  // 全 spec が単一の WordPress と共有 option を操作するため直列実行する。
  workers: 1,
  fullyParallel: false,
  // 環境起因の失敗を握りつぶさないためリトライしない。
  retries: 0,
  reporter: [['list']],
  timeout: 60_000,
  use: {
    baseURL: `http://localhost:${WP_PORT}`,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    locale: 'ja-JP',
  },
})
