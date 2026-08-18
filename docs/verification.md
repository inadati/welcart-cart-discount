# 動作確認の記録

設計書「動作確認の記録」節に記載した7項目の順に、実際に取得したスクリーンショットと
説明を並べる。すべて Docker（WordPress 6.6.2 / PHP 8.2.25 / Welcart 2.12.1.2608181）
上で実機操作して取得したものであり、未実施の項目はない。

検証手順（テスト商品・割引ルールの内容）は `README.md` の
「動作確認環境」節と合わせて参照すること。

- テスト商品A: `検証用商品40000円`（TEST-40000／SKU-001／単価 ¥40,000）
- テスト商品B: `検証用商品3000円`（TEST-3000／SKU-002／単価 ¥3,000、
  しきい値をまたぐ数量調整のために追加登録）
- 割引ルール: 10,000円以上で500円引き／30,000円以上で2,000円引き

---

## 1. 管理画面での複数段ルール設定

`Welcart Shop > 自動割引設定` で「10,000円以上で500円引き」「30,000円以上で
2,000円引き」の2段を入力し、保存した直後の状態。

- 入力直後: [`docs/screenshots/01-settings.png`](screenshots/01-settings.png)
- 保存後（`wcd_saved=1` のクエリで成功通知が表示された状態）:
  [`docs/screenshots/01-settings-saved.png`](screenshots/01-settings-saved.png)

保存内容は DB（`wp_options.wcd_settings`）でも
`a:2:{i:0;a:2:{s:9:"threshold";i:10000;s:6:"amount";i:500;}i:1;a:2:{s:9:"threshold";i:30000;s:6:"amount";i:2000;}}`
として正しくシリアライズされていることを確認済み。

---

## 2. しきい値未満のカート画面（割引なし）

テスト商品B（単価¥3,000）を数量3（合計¥9,000）でカートに入れた状態。
しきい値（¥10,000）未満のため割引行が表示されない。

[`docs/screenshots/02-below-threshold.png`](screenshots/02-below-threshold.png)

---

## 3. しきい値到達後のカート画面（割引行の表示）

同商品の数量を4（合計¥12,000）に更新すると、「自動割引 -¥500」
「割引後合計 ¥11,500」の行がカート表に追加される。

[`docs/screenshots/03-threshold-reached.png`](screenshots/03-threshold-reached.png)

---

## 4. 購入確認画面の割引行と支払総額

数量を10（合計¥30,000）に更新後、購入手続きを進めた確認画面。
「Campaign discount $-2,000.00」「Total Amount $28,000.00」と表示され、
カート画面の割引後合計（後述の項目6と同じ状態）と一致することを確認した。

[`docs/screenshots/05-confirmation.png`](screenshots/05-confirmation.png)

---

## 5. 受注データ一覧・受注詳細の割引額

上記の内容で注文を確定した後の受注一覧・受注詳細画面。

- 受注一覧（Total Amount $28,000.00 と表示）:
  [`docs/screenshots/06-order-list.png`](screenshots/06-order-list.png)
- 受注詳細（Campaign discount -2,000.00、Total Amount 28,000 と表示）:
  [`docs/screenshots/07-order-detail.png`](screenshots/07-order-detail.png)

DB（`wp_usces_order`）でも `order_item_total_price = 30000.00` /
`order_discount = -2000.00` として保存されていることを直接クエリして確認した。
これにより、カート画面・確認画面・受注データの3箇所で割引額（¥2,000）が
完全に一致することを実機で確認できた。

---

## 6. 上位段に到達したときの割引額の切り替わり

カート画面で数量を4（¥12,000、¥500引き）→10（¥30,000）に更新した際、
割引額が ¥500 と ¥2,000 の合算（¥2,500）にならず、**上位段の ¥2,000 のみ**に
切り替わることを確認した。

[`docs/screenshots/04-top-tier-switch.png`](screenshots/04-top-tier-switch.png)

### 補足: 受注編集時の再計算確認（実装計画タスク15ステップ8）

上記で確定した受注（数量10・¥30,000・割引¥2,000）を管理画面の受注編集画面で
数量4（¥12,000）に変更し「Recalculation」ボタンを押した際の再計算結果。

[`docs/screenshots/08-order-recalculation.png`](screenshots/08-order-recalculation.png)

この検証で「Campaign discount」が -¥2,500（¥12,000の正しい段は本来 -¥500）と
なる不具合を発見した。原因は `usces_filter_order_discount_recalculation` の
`$discount` 引数の扱いにあり、`includes/class-wcd-integration.php` を修正して
再検証し、正しく -¥500・合計¥11,500 と表示されること、「Recalculation」を
複数回押しても値が変わらない（べき等）こと、「change decision」で保存後に
DB（`order_item_total_price=12000.00` / `order_discount=-500.00`）へ正しく
反映されることを確認した。発見の経緯と修正内容は `docs/ai-report.md` と
`docs/design-notes.md` に記録している。

---

## 7. `composer lint` と `composer test` の実行結果

上記の不具合修正後、最終状態で再実行した結果。

- lint: [`docs/screenshots/10-composer-lint.txt`](screenshots/10-composer-lint.txt)（違反0件）
- test: [`docs/screenshots/11-composer-test.txt`](screenshots/11-composer-test.txt)（15 tests, 17 assertions, OK）

---

## その他の記録（参考）

- Welcart Shop・本プラグインの有効化直後のプラグイン一覧:
  [`docs/screenshots/00-plugins-active.png`](screenshots/00-plugins-active.png)
- Welcart 初期設定（支払方法「銀行振込」を追加した直後）:
  [`docs/screenshots/00-welcart-settings.png`](screenshots/00-welcart-settings.png)
- テスト商品登録後の商品編集画面（SKU・在庫・配送方法設定済み）:
  [`docs/screenshots/00-item-registered.png`](screenshots/00-item-registered.png)
