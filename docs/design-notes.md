# 設計メモ

設計書（`.nipper/chot/specs/2026-08-18-welcart-cart-discount-design.md`）で選定した
フックの採用理由と、検討したが採用しなかった候補との比較をまとめる。
また、実装直前の再調査（実装計画タスク5）および実機検証（タスク15）で判明した、
設計書作成時点では未確認だった実装細部をここに統合する。

---

## 使用するフック一覧

### Welcart のフック（すべて `usc-e-shop` 2.12.1 の実ソースで存在を確認済み）

| フック | 種別 | 用途 | 出典（実ソース確認済み） |
|---|---|---|---|
| `usces_order_discount` | filter | 割引額の注入（全経路の起点） | `classes/usceshop.class.php:8318`, `classes/tax.class.php:368` |
| `usces_filter_cart_table_footer` | filter | カート画面への割引行挿入 | `templates/cart/cart.php:67` |
| `usces_filter_order_discount_recalculation` | filter | 受注編集時の再計算 | `functions/item_post.php:2805`, `:3023` |

### WordPress のフック

| フック | 種別 | 用途 |
|---|---|---|
| `plugins_loaded` | action | Welcart 有効性の確認とフック登録 |
| `admin_menu` | action | 設定画面のサブメニュー登録 |
| `admin_post_wcd_save_settings` | action | 設定の保存処理 |
| `admin_notices` | action | Welcart 不在時の通知 |

### 本プラグインが公開する独自フック（第二段階の拡張点）

| フック | 第一段階の挙動 | 第二段階での用途 |
|---|---|---|
| `wcd_eligible_subtotal` | カート合計をそのまま返す | 商品カテゴリ除外（対象外商品を差し引く） |
| `wcd_available_rules` | 設定値をそのまま返す | 会員ランク除外（対象外ランクなら空配列を返す） |

---

## 各フックの採用理由と実装上の注意（タスク5・タスク15の調査結果を統合）

### `usces_order_discount`

- **採用理由**: `$usces->get_order_discount()` の末尾で必ず呼ばれ、戻り値が
  `set_cart_fees()` を経由してカート・確認画面・DB の `order_discount` カラムまで
  一本で流れる。3箇所整合の要となるフックであり、これ以外の入口を使うと
  どこかの画面だけ不整合になるリスクがある。
- **$discount の性質**: このフックが呼ばれる時点の `$discount` は、Welcart が
  カート内容から**毎回ゼロから計算し直す**値（キャンペーン割引が無効なら 0）。
  そのため「既存の割引額から自プラグインの割引額を減算（加算）する」実装で、
  他の割引と共存しつつ二重計上を起こさない。この前提は実機検証（タスク15）の
  カート・確認画面・受注データの3箇所で「上位段の切り替え時に前段と合算されない
  こと（¥2,500 ではなく¥2,000）」を確認して裏付けた。

### `usces_filter_cart_table_footer`

- **採用理由**: 標準テンプレートのカート表には割引行が存在しないため、テーマや
  Welcart 本体を改変せずに割引行を追加できる唯一の差し込み点。
- **設計書作成時点との差分（タスク5で判明）**: 設計書は当初 `cart.php:66` 付近と
  記載していたが、実ソース（`templates/cart/cart.php:67`）を確認すると

  ```php
  $html .= apply_filters( 'usces_filter_cart_table_footer', $cart_table_footer );
  ```

  であり、**引数は `$cart_table_footer` の1つのみ**で `$cart` は渡されない。
  設計時点では `usces_order_discount` と同様に `$cart` が引数で渡ってくることを
  想定していたが、これは誤りだった。実装（`WCD_Integration::filter_cart_table_footer()`）
  ではコールバック内部で `global $usces; $usces->cart->get_cart();` により
  カート情報を明示的に取得することで対応した。フックの実在だけでなく
  **引数の実体まで実ソースで確認する**という実装計画タスク5の方針が、
  この差分を実装前に発見する決め手になった。

### `usces_filter_order_discount_recalculation`

- **採用理由**: 管理画面で受注内容を編集し再計算した際に、割引だけが消える
  不整合を防ぐために必須。存在しなければ「受注確定後に数量変更すると割引が
  消える」という、3箇所整合の趣旨を裏切る不具合になる。
- **$cart の性質（タスク5で判明）**: この `$cart` は `functions/item_post.php` の
  `usces_order_recalculation()` 内で POST された `post_id` / `price` / `quantity`
  から組み立てられた**受注編集フォーム由来の配列**であり、フロントの
  セッションカート（`$usces->cart->get_cart()`）ではない。編集対象の受注と
  無関係な「管理者自身が別タブで操作中のセッションカート」を参照してしまう
  誤りを避けるため、実装ではフィルタ引数として受け取った `$cart` をそのまま使う。
- **$discount の性質（タスク15の実機検証で判明・第1回修正）**: `usces_order_discount`
  とは異なり、この `$discount` は Welcart が毎回ゼロから計算し直す値では**ない**。
  `usces_order_recalculation()` の呼び出し元は管理画面の JS から送信される
  `$_POST['discount']` であり、その実体は受注編集画面の「Campaign discount」欄に
  **現在表示されている値**（＝前回保存時に本プラグインが書き込んだ割引額を含む）
  である。当初の実装は `usces_order_discount` と同じ「既存の割引額から減算」を
  流用していたため、受注編集画面で数量を変更して「再計算」ボタンを押すたびに
  割引が二重・三重に計上される不具合があった（実機で ¥30,000/¥2,000引き の受注を
  数量変更して再計算すると ¥12,000 に対し ¥2,500引き になることを確認。
  正しくは ¥500引き）。この不具合はコミット
  `fix: 受注編集の再計算で割引が二重計上されるバグを修正` で修正した。
  修正後は `$discount` を無視し、本プラグインの計算結果でそのまま**置き換える**。
  トレードオフとして、軽減税率変更（`change_taxrate=change`）時に Welcart 自身の
  キャンペーン割引が `$discount` に入っているケースではその値を上書きしてしまう
  ことをコミット当時から認識していたが、この時点では「より精緻な代替案の検討」を
  ドキュメントに記録しておらず、evaluator の REJECT（`recalculation-fix-soundness` 軸）
  で指摘を受けた。以下は、その指摘を受けて行った第2回修正の記録である。

- **第2回修正（自プラグイン注入額のみを差し戻す精緻化、コミット
  `feat: 受注再計算で自プラグイン注入額のみを精緻に差し戻すよう改善`）**:

  「丸ごと置き換え」（二重計上は防げるが `change_taxrate=change` 時に Welcart 自身の
  キャンペーン割引を消す）のトレードオフを解消するため、Welcart 実ソース
  （`functions/item_post.php`）を再調査し、以下を確認した。

  - `usces_order_recalculation()` / `usces_order_recalculation_reduced()` は
    `$_POST['change_taxrate']` の値により `$discount` の意味そのものが変わる。
    `'change'` 以外（通常の数量変更など）では `$discount` は受注編集フォームの
    現在値（本プラグインの前回出力を含む）。`'change'`（軽減税率切替）では
    Welcart が `$discount = 0` としてから Promotionsale キャンペーン割引のみを
    ゼロから再計算するため（`item_post.php:2779-2795`）、この経路の `$discount` は
    本プラグインの寄与を含まない。
  - フィルタの引数（`$discount`, `$cart`, `$condition`, `$order_id`）だけではこの
    2経路を判別できない（`$condition` は変化しない）。`$change_taxrate` は
    `item_post.php:1136` で `$_POST['change_taxrate']` から読み取られるローカル変数で
    あり、フィルタには渡されない。このフィルタは常に同一リクエスト内（Welcart
    自身の管理画面ハンドラの実行中）でのみ呼ばれるため、同じ `$_POST` を読むことで
    経路を判別する（読み取り専用の表示分岐にのみ使うため nonce 検証は不要。
    保存処理の可否は Welcart 側のハンドラで完結している）。
  - 受注登録直後に発火する `usces_action_reg_orderdata` アクション
    （`functions/function.php:266`, `:417`。`usces_reg_orderdata()` /
    `usces_new_orderdata()` という全決済方法共通の受注登録経路から発火）の実在を
    確認した。

  **【誤った前提とその訂正】** 当初、前回本プラグインが注入した割引額を記録する先として
  「Welcart の受注は WordPress の投稿だろう」という推測のもと `update_post_meta()` /
  `get_post_meta()` を使う設計を検討した。これは Welcart の他の主要エンティティ
  （商品は `usces_item` 投稿タイプ）からの類推であり、実ソースを確認する前の誤りだった。
  実際には **Welcart の受注は独自テーブル `wp_usces_order` に保存され、投稿タイプでは
  ない**（`wp_posts` には存在しない）ため post meta API は使えないことが
  `classes/usceshop.class.php` の確認で判明した。Welcart 自身が受注メタ用に用意する
  `$usces->set_order_meta_value()` / `$usces->get_order_meta_value()`（実体は
  `wp_usces_order_meta` テーブルへの読み書き、`classes/usceshop.class.php:9334`,
  `:9342`）を使うよう設計を訂正した。「フックの実在だけでなく引数・データ構造の実体も
  実ソースで確認する」という方針（タスク5の方針）を、フックだけでなくデータ永続化層にも
  適用すべきだったという教訓であり、`docs/ai-report.md`「AIの出力が誤っていた箇所」4にも
  同じ事実を記録する。

  上記を踏まえ、`WCD_Integration::record_injected_discount_on_order_registration()` を
  `usces_action_reg_orderdata` に登録し、受注登録時に本プラグインが実際に注入した割引額を
  `wp_usces_order_meta` へ記録する。再計算時は `$_POST['change_taxrate']` に応じて
  次のように扱う。

  - `change_taxrate === 'change'` のとき: `$discount` は本プラグインの寄与を含まないため、
    そのまま加算する（`$discount - $amount`）。
  - それ以外のとき: 前回本プラグインが注入した割引額（メタに記録済み）だけを
    `$discount` から差し戻し（`$discount + $previous_injected`）、Welcart 自身の
    キャンペーン割引など他の割引成分を復元してから、新しい割引額を適用する
    （`$other_components - $amount`）。

  いずれの経路でも、次回のために新しい割引額（`$amount`、正の値）をメタに書き直す。

  実機検証（Docker + Playwright、詳細は `docs/verification.md` 「8. 受注再計算の
  精緻化（2周目修正）の再検証」）で、メタが正しく記録されている状態からの再計算が
  期待通り（¥12,000→¥30,000 の変更で -¥2,000 に正しく切り替わり、複数回クリックしても
  値が変わらない）ことを確認した。一方、**メタが一度も記録されていない受注**（本修正
  より前に作成された受注）に対する挙動には既知の限界が残ることも実機で確認した。
  詳細は次の「受注再計算の既知の限界（2周目修正で判明）」を参照。

  なお、この「8」節の再検証は既存受注（ID 1000）を SQL で意図的に既知の状態に戻して
  行ったものであり、`usces_action_reg_orderdata` フック自体が実際のフロント購入フローを
  通じて発火することまでは検証していなかった。3周目修正で、フロントからカートに商品を
  追加して購入手続きを最後まで進め、新しい受注（ID 1001）を確定させたところ、
  `record_injected_discount_on_order_registration()` が実際に発火し、
  `wp_usces_order_meta.wcd_injected_discount` に正しい割引額（¥2,000）が自動的に
  記録されることを実機で確認した。さらにこの新規受注を管理画面で数量変更・
  「Recalculation」した際も、正しい段（-¥500）に切り替わり、複数回クリックしても
  値が変わらないことを確認した。詳細は `docs/verification.md`
  「9. 新規受注（フロント購入フロー）での受注メタ自動記録の実機確認」を参照。

---

## 受注再計算の既知の限界（2周目修正で判明・未解決）

第2回修正（前回注入額のみを差し戻す方式）には、**本修正より前に作成された受注**
（`usces_action_reg_orderdata` が一度も発火していない、つまり `wcd_injected_discount`
メタが存在しない受注）に対して既知の限界が残る。実機（Docker 上の実際の受注データ、
ID 1000）で以下を確認した。

**現象**: メタ未記録の受注に対して初めて「Recalculation」を押すと、
`get_injected_discount()` がメタなしを `0.0` として返すため、`$discount`
（受注編集フォームの現在値。前回本プラグインが注入した割引額を実際には含んでいる）を
そのまま「他の割引成分」として扱ってしまい、二重計上と同種の誤りが発生する。
具体的には、数量4（¥12,000、正しくは-¥500）の受注で数量を変更せずに
「Recalculation」を押しただけで、表示が -¥500 から **-¥1,000** に変化した
（`docs/screenshots/12-recalc-known-limitation-first-time.png`）。

**この誤りは1回限りで収まらない**: 上記の1回目の再計算の直後、
`remember_injected_discount()` によりメタは正しい値（¥500、その時点の正しい割引額）に
更新される。しかし2回目以降の再計算では、telescoping で式を展開すると

```
discount_n = -(amount_before_fix) - amount_n
```

（`amount_before_fix` は修正前の最後の正しい割引額、`amount_n` は現在のカート状態での
正しい割引額）となり、**修正前の割引額がオフセットとして永続的に上乗せされ続ける**
と推定された。2周目修正の時点ではこの続き（-¥1,000 の状態から連続して数量を
変更して再計算する具体的な操作）までは実機検証しておらず、-¥2,500 という数値は
上記の数式から導いた机上の推定値だった（`docs/verification.md` 8-1節は数量を変えずに
「Recalculation」を押して -¥1,000 になることを確認した時点で止まっており、8-2節は
そこから連続してではなく、SQLで改めてクリーンな状態に戻してから数量10への変更を
検証していた）。

3周目修正で、この未検証だった続きの操作（-¥1,000 の状態から**SQLでリセットせず
そのまま**数量を10・¥30,000に変更して「Recalculation」を押す）を実機で行い、
表示が実際に **-¥2,500** になることを確認した（推定値と一致。
`docs/screenshots/22-order1000-recalc2-minus2500-confirmed.png`）。
これにより、数式によるtelescoping説明（`amount_before_fix = 500` が毎回加算され
続けるため `-500 - 2000 = -2500`）が実機の挙動と正確に一致することが確認できた。
詳細は `docs/verification.md`「10. 受注再計算の既知の限界の追加検証」を参照。

**回復方法**: この状態を解消するには、管理者が「Campaign discount」欄の値を手動で
正しい額に書き換えてから「change decision」（保存）を行う必要がある。保存処理自体は
`usces_filter_order_discount_recalculation` フィルタを経由しないため、これによって
「discount フィールドの値」と「メタに記録された注入額」が矛盾のない状態に戻り、
以降の再計算は正しく動作する。2周目修正の時点ではこの回避策を SQL による DB 直接
書き換えでしか検証しておらず、「管理画面の UI で Campaign discount 欄を手動編集して
保存する」という手順そのものが実機で機能することは未検証だった。3周目修正で、
上記の -¥2,500 という誤った表示状態から実際に管理画面の Campaign discount 欄へ
`-2000` を直接入力し、「change decision」で保存する操作を行い、保存後に DB
（`wp_usces_order.order_discount = -2000.00`）へ正しく反映されること、その後
数量10のまま「Recalculation」を押しても -¥2,000 のまま安定する（健全な状態に
復帰する）ことを実機で確認した
（`docs/screenshots/23-order1000-manual-ui-fix-before-save.png`,
`docs/screenshots/24-order1000-post-ui-fix-healthy-recalc.png`）。

**未解決である理由**: Welcart のフィルタ引数だけでは「メタが存在しない」ことが
「このプラグインで一度も割引を注入したことがない」ことを意味するのか、
「旧バージョン（メタ記録の仕組みがない時点）で注入したことはあるが記録されていない
だけ」なのかを区別する手段がない。後者を安全側に倒す（メタなし＝注入額0と仮定する）
と今回確認した限界が残る。プラグインのバージョンアップ直後に全受注へ一括で
メタを再構築するマイグレーション処理を別途用意すれば解消できるが、第一段階の
実装スコープを超えるため、今回は実装せず、この限界を正直に記録するに留める
（`docs/ai-report.md`「うまくいかなかったこと・時間を要したこと」にも同じ内容を記録）。

---

## 確定した仕様判断

| 論点 | 決定 | 根拠 |
|---|---|---|
| 複数段の適用方式 | 到達した最上位の1段のみ適用 | 課題文の例示（35,000円→2,000円引き）と整合。店舗が最終割引額を把握しやすい。累積方式は設定ミスを誘発する |
| しきい値の判定基準額 | 商品合計 `$usces->get_total_price( $cart )` | Welcart 標準のキャンペーン割引が同じ値を基準にしている（`usceshop.class.php:8294`）。送料はカート画面時点で未確定のため、含めると3箇所整合が破綻する |
| しきい値の境界 | 「以上」（`>=`） | 課題文が「10,000円以上」と明記。実装とテストの両方で固定する |
| 割引額の型 | 整数（円） | 日本円運用を前提とする。多通貨の小数対応は行わず README に明記 |
| 既存割引との関係（`usces_order_discount`） | 上書きせず加算 | 上書きすると Welcart 標準キャンペーン割引や他プラグインの割引が消える。$discount が毎回ゼロから再計算される文脈でのみ安全に成立する（上記参照） |
| 既存割引との関係（`usces_filter_order_discount_recalculation`） | `$_POST['change_taxrate']` で経路分岐し、通常時は前回注入額（`wp_usces_order_meta` に記録）のみを差し戻してから適用、軽減税率変更時は加算 | 当初は「加算ではなく丸ごと置き換え」（タスク15で二重計上バグを実証したため）だったが、evaluator の指摘（`recalculation-fix-soundness` 軸）を受け、軽減税率変更時に Welcart 自身のキャンペーン割引を上書きしてしまうトレードオフをより精緻に解消した（2周目修正）。メタ未記録の既存受注に対する既知の限界が残る（上記「受注再計算の既知の限界」参照） |
| 設定画面の位置 | Welcart Shop メニュー配下 | 店舗運営者の導線に一致。権限も Welcart 体系に乗る |
| 設定保存の実装 | Settings API を使わず自前処理 | 動的増減する行と Settings API のフィールド登録モデルが噛み合わない。また必須要件5の明示的実装を示すため |

---

## 検討したが採用しなかった候補

### 複数段の累積適用（不採用）

「10,000円以上で500円引き」と「30,000円以上で2,000円引き」を両方満たす場合に
合計2,500円引きとする案も検討した。課題文の例示（35,000円の例で2,000円引きと
明記されている）と矛盾するため不採用。累積方式は設定した段数が増えるほど
店舗側が最終割引額を暗算しづらくなり、設定ミスによる過大な割引損失のリスクも
高まる。

### Settings API 経由の保存（不採用）

WordPress 標準の Settings API（`register_setting()` / `add_settings_field()`）を
使う案も検討した。Settings API はフィールドを事前登録するモデルであり、
「行の追加・削除」で動的に増減する割引ルールの配列とは相性が悪く、
`sanitize_callback` 内で配列全体の正規化（重複排除・ソート）を行うのも
不自然になる。また、必須要件5（nonce検証・権限チェック・サニタイズ）を
`WCD_Admin::handle_save()` に明示的に実装することで、採用ツールに頼らず
セキュリティ処理を自前で示す狙いもあり、自前の `admin_post_*` 実装を採用した。

### `usces_filter_cart_table_footer` での `$cart` 引数受け取り（不採用・設計誤りとして訂正）

設計段階では `usces_order_discount` と同様に `$cart` がフィルタ引数として
渡ってくる前提でコールバックの型を検討していた。タスク5の実ソース調査で
このフックが1引数（`$cart_table_footer` のみ）であることが判明したため、
`global $usces; $usces->cart->get_cart();` によるカート取得に設計を訂正した。
「検討したが採用しなかった候補」というより「設計の誤りをコードより先に
実ソースで検出できた事例」であり、AI活用レポート（`docs/ai-report.md`）にも
同じ事実を記録する。

---

## 第二段階: 除外条件設定（会員ランク・商品カテゴリ）

設計書 `.nipper/chot/specs/2026-08-20-welcart-cart-discount-exclusions-design.md` に基づく
加点要件の実装。第一段階で設置済みの独自フィルタ2本（`wcd_eligible_subtotal` /
`wcd_available_rules`）に接続する形で実装し、Welcart 側に新規フックは要求していない
（使用するフックは第一段階の4本のまま増えていない）。

### 実装計画作成時に発見した設計書内の記述矛盾

設計書の「既存ファイルへの変更」表は `includes/class-wcd-integration.php` を
「変更しない（拡張点は設置済み）」としていたが、同じ設計書の「データフロー／経路2」節は
「`WCD_Integration::filter_order_recalculation()` の実行中だけ `WCD_Exclusion` に
対象受注IDを通知する静的プロパティ」という実装方針を明記していた。`wcd_available_rules`
フィルタの引数は `($rules, $cart)` の2つのみで `$order_id` を含まないため、この通知を
実現するには `filter_order_recalculation()` の本体（`calculate_amount()` を呼ぶ箇所）に
2行を追加する以外の手段がなく、「変更しない」は誤りだった。実装計画作成（writing-plans
フェーズ）の時点でこの矛盾に気づき、「フィルタのシグネチャ・契約は変えないが、本体には
最小限の2行を追加する」方針に修正して実装した（`includes/class-wcd-integration.php` の
`filter_order_recalculation()` 冒頭、コミット `feat: 受注再計算時に対象受注IDを
WCD_Exclusion へ通知する`）。詳細は `docs/ai-report.md`「AIの出力が誤っていた箇所」を
参照。

### 受注再計算の例外安全性（2周目のevaluator REJECTで発見・try/finallyで対応）

上記の `begin_order_recalculation() / end_order_recalculation()` の対称呼び出しは、
実装当初は `WCD_Integration::filter_order_recalculation()` 内で
`begin...(); $amount = self::calculate_amount( $cart ); end...();` という素朴な直列呼び出しに
なっていた。

evaluator の REJECT（2周目、`rank-resolution-context-correctness` 軸）は、
`calculate_amount()` の内部で呼び出される `wcd_eligible_subtotal` / `wcd_available_rules`
という、他プラグインも購読しうる公開フィルタのコールバックが例外を送出した場合、
`end_order_recalculation()` が実行されないまま関数を抜けてしまい、静的プロパティ
`WCD_Exclusion::$recalculating_order_id` が残留する、という設計上の見落としを指摘した。
残留すると、以降にカート画面・購入確認画面で発生するランク解決（本来はセッション中の
ログイン会員を見るべき）が誤って「受注再計算中」の分岐に迂回し、無関係な購入者の
ランクを受注の持ち主のランクと誤認するおそれがあった。

指摘は技術的に正しく、`calculate_amount( $cart )` の呼び出しを try/finally で囲み、
例外発生時にも `end_order_recalculation()` が確実に呼ばれるよう修正した
（`includes/class-wcd-integration.php`、コミット `d66e4e9 fix: 受注再計算の例外発生時にも
受注ID通知を確実に解除する`）。同時に、`WCD_Exclusion_SettingsTest.php` の ranks 系
テストが会員ランク `0` の境界値（`categories` の「0以下は無条件に破棄する」とは非対称な、
「`known_ranks` に含まれていれば保持し、含まれていなければ破棄する」という仕様）を
検証していなかった指摘（`test-coverage-quality` 軸）も受け、回帰テスト2件を追加した
（コミット `38a9ae6`）。詳細は `docs/ai-report.md`「AIの出力が誤っていた箇所」11を参照。

### 会員ランク解決の経路依存（第一段階からの拡張点）

会員ランクの解決元は、カート・確認画面（セッション中のログイン会員）と受注編集の再計算
（受注の持ち主）とで異なる。この非対称性は第一段階の `usces_filter_order_discount_recalculation`
に関する調査（「$discount の性質」節）と同種の構造であり、除外条件の実装でも同じ注意点
（フィルタの呼び出し文脈によって参照すべきデータソースが変わる）が再度現れた。

### `WCD_Exclusion_Settings::normalize()` の categories 正規化バグ（実装時に発見・修正）

`normalize()` のテスト（`tests/unit/WCD_Exclusion_SettingsTest.php`）を書く段階で、
`categories` に負値（`-1`）を渡すと破棄されずに `1` として残ってしまう不具合を実装中に
発見した。原因は、`absint()` を先に適用してから `$category_id <= 0` で0以下判定を
行っていたため、`absint( -1 )` が `1`（絶対値）に変換され、非正数判定をすり抜けていた
ことにある。第一段階の `WCD_Settings::normalize()`（`docs/ai-report.md`「AIの出力が
誤っていた箇所」2）で踏んだのと同種の落とし穴が、今回は設計書の疑似コードの記述段階で
再発した。修正はチェック順序を入れ替え、`is_numeric( $category ) && $category <= 0` の
判定を **`absint()` 適用前の生値**に対して行う形にした（`includes/class-wcd-exclusion-settings.php`）。
第一段階のコメント規約を踏襲し、同じ理由をコード内コメントとして残している。

### 単体テスト件数: 実装計画の想定（32件）と実際（44件→2周目修正で46件）の乖離

実装計画は「既存15件 + 新規17件 = 32件」を想定していたが、実際には既存テストは
27件（第一段階の完了後に `WCD_Cart_Row_BuilderTest`（11件）が追加されており、
計画作成時点で参照した「15件」という数字はその追加前の値だった）あり、新規17件
（`WCD_Exclusion_CalculatorTest` 10件 + `WCD_Exclusion_SettingsTest` 7件）と合わせて
実装当初の合計は **44件**であった。計画書の想定件数はチェックリストの目安として
書かれたものであり、実装・レビューの判断には実測件数（`composer test` の出力）を
優先した。

その後、2周目修正（`test-coverage-quality` 軸のevaluator REJECTを受けた対応、
コミット `38a9ae6`）で、会員ランク `0` の境界値を検証する回帰テスト2件
（`test_rank_zero_is_preserved_when_in_known_ranks` /
`test_rank_zero_is_discarded_when_not_in_known_ranks`）を追加した。これにより
**現在の実際の合計は46件**である（`vendor/bin/phpunit` 実測: 46 tests, 53
assertions）。この44件から46件への増加の経緯は `docs/ai-report.md`「AIの出力が
誤っていた箇所」11、`docs/verification.md` 18節でも同じ数値で追跡・記録している。

### 並行 generator 間のコミット競合とその復旧

タスク7（`WCD_Integration::filter_order_recalculation()` への文脈受け渡し追加）担当と
タスク8・9（`WCD_Admin` の除外設定UI追加・保存処理接続）担当は、chot-harness の
generator フェーズで同一バッチ内の別サブエージェントとして並行実行された。作業対象
ファイルが分かれていたにもかかわらず、タスク7担当が `git add` 後に `git commit` した
際、同時にステージされていたタスク8・9担当の `includes/class-wcd-admin.php` の変更を
巻き込んでコミットしてしまう競合が一時的に発生した。双方とも、他方が既に作業ツリーに
残していた変更履歴を失わせないよう `git rebase` や `git commit --amend` は使わず、
`git reset --soft` で直前のコミットを取り消してから改めて対象ファイルを個別に
`git add` し直し、意図通りの分離コミット（`0801e20 feat: 受注再計算時に対象受注IDを
WCD_Exclusion へ通知する` / `b7bc047 feat: 設定画面に除外条件（会員ランク・商品カテゴリ）
のUIを追加` / `63a232e feat: 除外設定の保存を既存のnonce・権限チェックに相乗りさせる`）
に直した。この復旧の過程は `git reflog` にも残っている（`53713ee` への `reset: moving
to HEAD~1` のエントリ）。並列 generator 運用では、同一リポジトリ上でサブエージェントが
`git add -A` 等の広い範囲のステージングを行うと他エージェントの作業中の変更を意図せず
巻き込みうる、という実例として記録する。
