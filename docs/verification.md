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

## 8. 受注再計算の精緻化（2周目修正）の再検証

evaluator の REJECT を受けた2周目修正（`fix: カート表フッター挿入時に $usces->cart の
null安全性を確保` / `feat: 受注再計算で自プラグイン注入額のみを精緻に差し戻すよう改善`）
について、既存の Docker 環境（コンテナは起動済みだったためそのまま使用）と Playwright で
実機再検証を行った。テスト方法は、上記1〜7で確定した受注（ID 1000）を SQL で意図的に
既知の状態に戻しながら再計算を行うというものである。フルの購入フロー（カート30,000円→
受注確定）から毎回やり直さなかった理由は `docs/ai-report.md`「AIの出力が誤っていた箇所」5
に記録した通り、既存受注を使う方が検証したいシナリオ（本修正より前に作成された受注の
挙動）をより正確に再現できるためである。

### 8-1. メタ未記録の既存受注に対する1回目の再計算（既知の限界の再現）

修正前のコードで作成された受注（ID 1000、`wcd_injected_discount` メタ未記録、
数量4・¥12,000・正しい割引額-¥500）に対し、数量を変更せず「Recalculation」を
押したところ、表示が **-¥1,000** になった（期待値は-¥500のまま変わらないはず）。
`docs/design-notes.md`「受注再計算の既知の限界」に記録した通りの二重計上と同種の
誤りが、修正後のコードでも「メタ未記録」という条件下で再現することを確認した。

[`docs/screenshots/12-recalc-known-limitation-first-time.png`](screenshots/12-recalc-known-limitation-first-time.png)

DB を直接確認し、この時点で `wp_usces_order_meta` に `wcd_injected_discount = 500`
（＝現在のカート状態に対する正しい割引額）が新たに記録されたことも確認した
（`remember_injected_discount()` は常に正しい `$amount` を記録するため、
記録される値自体は正しい。誤っているのは表示された `$discount` の値である）。

### 8-2. メタが正しく記録された状態からの再計算（正常系の確認）

DB（`wp_usces_order.order_discount` / `wp_usces_order_meta.wcd_injected_discount` /
`wp_usces_ordercart.quantity`）を SQL で意図的に「数量4・¥12,000・discount=-500・
メタ=500」という整合の取れた状態に戻し、ブラウザをリロードして再検証した。

- 数量を10（¥30,000）に変更し「Recalculation」を押すと、正しく **-¥2,000** と
  表示された。
- 続けてもう一度「Recalculation」を押しても **-¥2,000** のまま変化しなかった
  （べき等性を確認）。
- 数量を4（¥12,000）に戻して再計算すると、正しく **-¥500** に戻った。

[`docs/screenshots/13-recalc-clean-post-fix-2000.png`](screenshots/13-recalc-clean-post-fix-2000.png)

この結果、メタが正しく追跡されている限り、精緻化後のロジックは意図通りに動作する
ことを実機で確認した。8-1 で確認した限界は、あくまで「メタが一度も記録されていない
受注」に固有のものである。

### 8-3. カート画面（フロント）のリグレッション確認（null 安全性修正）

`filter_cart_table_footer()` に追加された `$usces->cart` の null ガードが通常の
カート表示を壊していないことを確認するため、テスト商品（TEST-3000）を数量4で
カートに追加し、カート画面を表示した。「自動割引 -¥500」の行が正しく表示され、
リグレッションがないことを確認した。

[`docs/screenshots/14-cart-regression-after-null-safety-fix.png`](screenshots/14-cart-regression-after-null-safety-fix.png)

なお、`$usces->cart` が実際に null になる状況（このガードが本来守ろうとしている
経路）そのものは、通常のブラウザ操作では再現できなかった。これはコードレビューで
対処した防御的分岐であり、この経路自体を実機で発火させることはできていない
（`docs/ai-report.md`「うまくいかなかったこと」に同旨を記録）。

### 8-4. `composer lint` / `composer test` の再実行

ローカル環境に PHP が見つからなかったため、Docker コンテナ内（PHP 8.2.25、
`docker exec docker-wordpress-1 ...`）で bind mount 済みの `vendor/bin/phpunit` /
`vendor/bin/phpcs` を直接実行し、2周目修正後のコードで次を確認した。

- `vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/unit`: 15 tests,
  17 assertions, OK
- `vendor/bin/phpcs --standard=phpcs.xml.dist`: 7 / 7 完了、違反0件

## その他の記録（参考）

- Welcart Shop・本プラグインの有効化直後のプラグイン一覧:
  [`docs/screenshots/00-plugins-active.png`](screenshots/00-plugins-active.png)
- Welcart 初期設定（支払方法「銀行振込」を追加した直後）:
  [`docs/screenshots/00-welcart-settings.png`](screenshots/00-welcart-settings.png)
- テスト商品登録後の商品編集画面（SKU・在庫・配送方法設定済み）:
  [`docs/screenshots/00-item-registered.png`](screenshots/00-item-registered.png)
