# AI活用レポート

## 使用ツールと進め方

- **Claude Code**（本レポート作成時点のモデル: Claude Sonnet 5）を、設計から実装・検証・
  ドキュメント作成まで一貫して使用した。
- 開発フローは chot-harness（設計→計画→並列生成→評価のループを回す社内ハーネス）を用い、
  以下の段階を踏んだ。
  1. **brainstorming**: 課題文の要件・評価基準を踏まえた対話を通じて設計方針を固め、
     `.nipper/chot/specs/2026-08-18-welcart-cart-discount-design.md` を作成。
  2. **writing-plans**: 設計書を基に、TDD前提のステップバイステップな実装計画
     `.nipper/chot/plans/2026-08-18-welcart-cart-discount.md`（タスク1〜16）を作成。
  3. **generator（並列生成）**: 実装計画のタスク1〜14を、複数の generator サブエージェントが
     `git log --oneline` の履歴が示す通り、雛形→値オブジェクト→計算コア→設定→
     フック接続→管理画面→lint→i18n→Docker→CI の順にTDDで積み上げた。
  4. **本タスク（15・16）**: 実機検証（Docker + Playwright によるブラウザ操作）と
     提出物ドキュメントの作成。実装エージェントとは別のセッションとして、
     すでに実装済みのコードを外部から検証する立場で作業した。

## Welcart 実ソースでの事前検証（なぜ行ったか）

設計書・実装計画のいずれにも「フックの実在は必ず Welcart のソースを読んで確認し、
推測でフック名を使わない」という制約を明記していた。これは、AIエージェントが
存在しないフック名やシグネチャを尤もらしく生成しがち、という一般的な弱点への
対策として意図的に組み込んだプロセスである。実装計画のタスク5を「調査タスク・
コード変更なし」として独立させ、`usces_order_discount` / `usces_filter_cart_table_footer` /
`usces_filter_order_discount_recalculation` の呼び出し箇所・引数の実体を
`grep` と実ソース読解で確認してから Integration 層の実装に着手する順序にした。

## AIの出力が誤っていた箇所とその発見・修正方法

### 1. `usces_filter_cart_table_footer` の引数仕様（設計時点の誤り・実装前に訂正）

設計書は当初、このフックも `usces_order_discount` と同様に `$cart` が引数で
渡ってくる想定で書かれていたが、これは実ソースを読む前の推測だった。
実装計画タスク5で `templates/cart/cart.php` を実際に読んだところ、

```php
$html .= apply_filters( 'usces_filter_cart_table_footer', $cart_table_footer );
```

（`templates/cart/cart.php:67`）であり、引数は `$cart_table_footer` の1つのみで
`$cart` は渡されないことが判明した。この事実は `includes/class-wcd-integration.php`
のコミット（`feat: WCD_Integration に割引額注入と受注再計算を追加` および
`feat: カート画面への割引行表示を追加`）に残るコード内コメントで確認できる。
実装では `global $usces; $usces->cart->get_cart();` によりカート情報を
明示的に取得する形に修正して実装された。**コードを書く前に実ソースで検証する
プロセスが機能し、誤りが実装に混入する前に訂正できた事例。**

### 2. `WCD_Settings::normalize()` の absint() 適用順序（コード内コメントから確認できる設計判断）

`git show 673e1f5`（`feat: WCD_Settings::normalize() を追加`）のコードには、
以下のコメントが残っている。

```php
// absint() は abs( intval() ) であり負値をそのまま正値へ反転させるため、
// 0以下（負値含む）の判定は absint() する前の生値に対して行う。
if ( (float) $row['threshold'] <= 0 || (float) $row['amount'] <= 0 ) {
    continue;
}

$threshold = absint( $row['threshold'] );
```

`absint()` は絶対値を取ってから整数化するため、`absint( -500 )` は `500` になり、
本来は不正として破棄すべき負のしきい値が正のルールとして通ってしまう。
この関数は「不正入力の除去（負値・空文字・非数値）」がテスト観点として
設計書に明記されていた項目であり、`tests/unit/WCD_SettingsTest.php` の
`test_discards_non_positive_rows()` によって
このコメントが示す順序（符号判定 → `absint()`）が正しく実装されていることを
TDDで担保している。**absint() を先に適用すると符号情報が失われる**という
落とし穴は、WordPress関数の意味論を正確に理解していないと踏みやすい誤りであり、
コメントとして明示的に残すことで再発を防いでいる。

### 3. 受注編集の再計算フィルタでの割引二重計上（タスク15の実機検証で発見・修正）

これは実装フェーズではなく、**本タスク（タスク15の実機検証）で新たに発見した
不具合**である。

**発見の経緯**: Docker + Playwright で「カート30,000円→受注確定（割引¥2,000）→
管理画面で受注の数量を変更して¥12,000相当にし、『再計算』ボタンを押す」という
実装計画タスク15ステップ8の手順を実行したところ、期待値は¥500引き（12,000円は
10,000円以上30,000円未満の段）だったが、実際の表示は**¥2,500引き**だった。

**原因調査**: `usces_filter_order_discount_recalculation` フィルタの `$discount`
引数を、実装は `usces_order_discount` と同じ「既存の割引額から減算」ロジック
（`return $discount - self::calculate_amount( $cart );`）で扱っていた。
しかし Welcart 側のソース（`functions/item_post.php` の `usces_order_recalculation()`）
を読むと、この `$discount` は管理画面から送信される `$_POST['discount']`、
つまり受注編集画面の「Campaign discount」欄に**現在表示されている値**
（＝前回保存時に本プラグイン自身が書き込んだ割引額）がそのまま渡ってくる。
`usces_order_discount` の `$discount` が呼び出しのたびにゼロから再計算される
のとは前提が異なり、同じロジックを流用すると
「前回の割引額 -2,000 に対して、新しい割引額 -500 をさらに減算」してしまい、
`-2,000 - 500 = -2,500` という二重計上が発生していた。

**修正**: `includes/class-wcd-integration.php` の `filter_order_recalculation()` を、
`$discount` に対する加減算をやめ、本プラグインの計算結果でそのまま置き換える形に
修正した（コミット: `fix: 受注編集の再計算で割引が二重計上されるバグを修正`）。
修正後、同じ手順を再実行し、¥500引き・合計¥11,500 と正しく表示されること、
「再計算」ボタンを連打しても値が変わらない（べき等である）こと、
「変更を保存する」で DB（`wp_usces_order.order_discount`）に `-500.00` として
正しく保存されることを確認した。`composer lint` / `composer test`
（15 tests, 17 assertions）も修正後に再実行し、いずれも通ることを確認した。

このトレードオフとして、軽減税率変更時に Welcart 自身のキャンペーン割引が
`$discount` に入っているケースではその値を上書きしてしまう可能性が残る。
これは「常に再現する二重計上バグ」と「稀にしか組み合わないケースでの
上書き」を比較した結果のトレードオフであり、詳細は `docs/design-notes.md` に
記録した。

## うまくいかなかったこと・時間を要したこと

### Welcart 管理画面の「新規商品登録」で Playwright の合成クリックが機能しなかった

テスト商品を登録する際、Welcart の「Add New Item」画面で `#publish` ボタンを
Playwright の `browser_click`（内部的には `element.click()`）で押しても、
`post.php` へのネットワークリクエストが一切発生せず、商品が `auto-draft` の
ままになる事象が発生した。WordPress 標準の投稿編集画面が持つ
「auto-draft から publish への遷移時に `history.replaceState()` で URL を
書き換える」という JS（`wp-admin/js/post.js`）が関与しており、合成クリックでは
実際のフォーム送信まで届かないケースがあることが分かった。
最終的に `document.getElementById('post').submit()` を直接呼び出す形で回避したが、
その際は `#publish` ボタンの `name=value`（`publish=Publish`）が送信データに
含まれないため、Welcart 側の「どのボタンが押されたか」を見て分岐する保存処理
（商品メタデータの保存など）が動かず、一度目は商品コード・商品名しか
保存されないという二段階の試行錯誤が必要だった。最終的に隠しinput要素で
`publish` フィールドを補って解決した。この一連の切り分けに検証時間の
かなりの部分を要した。**プラグイン自体の不具合ではなく、Welcart管理画面の
複雑なJS（WordPress標準の投稿編集フローに独自のSKU登録処理を重ねている）と
自動化ツールの相性の問題である。**

### 国際配送バリデーションで「Delivery method is incorrect」エラー

購入確認画面で「Next」を押すと `Delivery method is incorrect. Specify the
international flights.` というエラーで先に進めない事象が発生した。
配送方法設定で「Possible Delivery Area」を Domestic Shipment に設定していたが、
テスト用の顧客住所を米国の州（カリフォルニア州）で入力していたため、
Welcart 側がこれを Domestic ではなく International 扱いと判定していたことが
原因と推測される。配送方法の設定を International Shipment に切り替えることで
回避した。根本原因（Welcart の国内/海外判定ロジックの詳細）までは
時間の制約上、深く追跡していない。

### 未完了だった項目

実装計画タスク15のステップ3〜8はすべて実施できた。第二段階（会員ランク・
商品カテゴリ除外の実装）は本設計書・実装計画の対象外であり、着手していない
（当初から意図的にスコープ外としている）。
