# AI活用レポート

## 使用ツールと進め方

- **Claude Code**（本レポート作成時点のモデル: Claude Sonnet 5）を、設計から実装・検証・
  ドキュメント作成まで一貫して使用した。
- 開発フローは chot-harness（設計→計画→並列生成→評価のループを回す自作の並列開発ハーネス）を用い、
  以下の段階を踏んだ。
  1. **brainstorming**: 課題文の要件・評価基準を踏まえた対話を通じて設計方針を固め、
     `.nipper/chot/specs/2026-08-18-welcart-cart-discount-design.md` を作成。
  2. **writing-plans**: 設計書を基に、TDD前提のステップバイステップな実装計画
     `.nipper/chot/plans/2026-08-18-welcart-cart-discount.md`（タスク1〜16）を作成。
  3. **generator（並列生成・バッチ方式）**: 実装計画のタスク1〜14を、chot-harness の
     generator フェーズが複数の generator サブエージェントにバッチ単位で並列に割り当てて
     実装した。実際のコミット順序（後述）は計画のタスク番号の順序とは一致しない箇所があるが、
     これは意図的な並列化の結果であり見落としではない。詳細は次節「コミット順序と実装計画の
     タスク番号の関係」に記録する。
  4. **本タスク（15・16、および本レポート作成後の2周目・3周目修正）**: 検証
     （Docker + Playwright によるブラウザ操作）と提出物ドキュメントの作成。実装エージェント
     とは別のセッションとして、すでに実装済みのコードを外部から検証する立場で作業した。
     2周目では、chot-harness の evaluator が REJECT と判定した指摘
     （`.nipper/chot/feedback.md`）に基づき、コード側は別の generator サブエージェントが
     2件修正し（カート表フッターの null 安全性確保、受注再計算の精緻化）、本レポートを
     含むドキュメント側は本タスクが担当し整合させた。3周目では、evaluator が
     `order-meta-hook-coverage` 軸・`known-limitation-precision` 軸で REJECT と判定した
     指摘（新規受注フローでの受注メタ自動記録がDocker環境で未確認であったこと、-¥2,500という
     数値とUI経由の回避策がDocker環境で未確認のまま「確認した」と記載されていたこと）を受け、
     本タスクがDocker環境で実際に操作して追加検証を行い、ドキュメントの記述を実際に確認できた内容に
     訂正した（詳細は「AIの出力が誤っていた箇所」6・7を参照）。
  5. **第二段階（除外条件設定）**: 加点要件の実装として、同じ
     brainstorming → writing-plans → generator（並列生成） の流れを別セッションで
     再度回した。設計書は
     `.nipper/chot/specs/2026-08-20-welcart-cart-discount-exclusions-design.md`、
     実装計画は `.nipper/chot/plans/2026-08-20-welcart-cart-discount-exclusions.md`
     （タスク1〜13）。タスク1〜11を generator が並列実装し、本タスク（タスク12・
     提出物ドキュメント更新）は実装エージェントとは別のセッションとして、既に実装
     済みの除外条件のコードとコミット履歴を外部から確認し整合させる形で担当した。
     詳細は「第二段階: 除外条件設定」節を参照。

## コミット順序と実装計画のタスク番号の関係

実際のコミット順序を `git log --pretty=format:"%h %ad %s" --date=iso --reverse` で確認すると、
タスク1〜14の範囲は次の通りである（コマンドの出力そのもの）。

```
a804d6b 2026-08-18 14:34:57 +0900 chore: プロジェクト雛形とツールチェーンを追加
210c995 2026-08-18 14:39:22 +0900 chore: Docker検証環境を追加
055d5d7 2026-08-18 14:39:30 +0900 chore: GitLab CI で lint と test を実行するよう設定
b0257ec 2026-08-18 14:40:44 +0900 feat: WCD_Rule 値オブジェクトを追加
3db3112 2026-08-18 14:41:05 +0900 feat: WCD_Calculator 割引計算コアを追加
673e1f5 2026-08-18 14:42:10 +0900 feat: WCD_Settings::normalize() を追加
3e54811 2026-08-18 14:43:10 +0900 feat: WCD_Plugin によるフック登録の一元管理を追加
3e7bcc8 2026-08-18 14:43:37 +0900 feat: WCD_Admin に設定画面の描画を追加
e6ceb79 2026-08-18 14:43:50 +0900 feat: 設定保存処理に nonce検証・権限チェック・サニタイズを実装
79a6199 2026-08-18 14:43:54 +0900 feat: WCD_Integration に割引額注入と受注再計算を追加
d0d5329 2026-08-18 14:44:06 +0900 feat: カート画面への割引行表示を追加
344b685 2026-08-18 14:48:58 +0900 style: WPCS違反を解消
833713e 2026-08-18 14:49:44 +0900 chore: 翻訳用 .pot を生成
```

すなわち、実際の順序は「雛形→**Docker→GitLab CI**→値オブジェクト→計算コア→設定→
WCD_Plugin→**WCD_Admin描画→設定保存**→WCD_Integration→カート表示→lint→i18n」であり、
計画のタスク番号順（1〜14の昇順）とは以下の2点で異なる。

1. タスク13（Docker検証環境）・タスク14（GitLab CI設定）が、タスク1（雛形）の直後、
   タスク2〜11（値オブジェクト・計算コア・設定・フック接続・管理画面という TDD によるコア
   実装群）より前にコミットされている。
2. タスク6〜10の内部順序が、計画（6:WCD_Plugin→7:Integration割引注入→8:カート表示→
   9:Admin描画→10:Admin保存）とは異なり、実際は 6(WCD_Plugin)→9(Admin描画)→
   10(Admin保存)→7(Integration)→8(カート表示) の順になっている。

**この乖離が生じた理由**: chot-harness の generator フェーズは、実装計画のタスク間の依存関係を
踏まえた上で、依存関係のない独立タスクを1つのバッチとしてまとめ、複数の generator
サブエージェントに並列で割り当てるバッチ方式で実行される。実装計画のタスク番号は
「論理的な依存順序」（何が何に依存して書けるか）を表現するものであり、「コミットの
タイムスタンプ順」を保証するものではない。

- タスク13（Docker検証環境）とタスク14（GitLab CI設定）は、コア実装タスク2〜11
  （値オブジェクト・計算コア・設定・フック接続・管理画面）のいずれにも依存しない独立タスク
  である（Docker はテスト対象コードを bind mount するだけ、GitLab CI は `composer test` /
  `composer lint` を呼び出すだけで、両者ともコード側の実装内容を先に読む必要がない）。
  そのため、タスク1（雛形）が完了した直後の同一バッチで、コア実装群と並列実行の対象となった。
  Docker・CI の設定ファイルはコア実装（TDD でテストを書きながら実装するため相対的に時間を
  要する）より記述量・検証時間が少なく、担当した generator サブエージェントの完了が早かった
  ため、結果としてコミットが先になった。
- タスク6〜10も同様に、タスク6（WCD_Plugin）完了後は、タスク9（Admin描画）・
  タスク10（Admin保存）・タスク7（Integration の割引額注入・受注再計算）・
  タスク8（カート表示）の相互に依存しない4タスクが同一バッチで並列実行され、
  各サブエージェントの実際の作業完了タイミングによって計画の番号順とは異なる順に
  コミットされた。

一方、タスク間の**依存関係そのもの**は損なわれていない。実際のログでもタスク2
（b0257ec、WCD_Rule）〜タスク4（673e1f5、WCD_Settings）というコア計算層が、これらに
依存するタスク6〜10（フック接続・管理画面）より前に完了していることが確認できる。
機能的な正しさは各バッチの完了時点で `composer test` / `composer lint` を実行して都度
確認しており（各タスクのコミットメッセージ内の「構文チェックを行う」ステップに対応）、
依存順序の遵守という設計上の制約は守られたまま、コミットの見た目の時系列だけが
バッチ内の並列実行によって前後している。これは意図的な並列化の結果であり、
単なる作業順序の乱れや見落としではない。

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

### 3. 受注編集の再計算フィルタでの割引二重計上（タスク15のDocker環境での検証で発見・修正）

これは実装フェーズではなく、**本タスク（タスク15のDocker環境での検証）で新たに発見した
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

### 4. 「Welcart の受注は投稿タイプで post meta が使える」という誤った前提（2周目修正で訂正）

evaluator の REJECT（`.nipper/chot/feedback.md` の `recalculation-fix-soundness` 軸）を
受け、上記3の「軽減税率変更時に上書きしてしまう」トレードオフをより精緻に解消する
（本プラグインが前回注入した割引額だけを記録・差し戻す）実装に着手した際、
最初に検討した実装方針は「受注を WordPress の投稿として扱い、`update_post_meta()` /
`get_post_meta()` で前回注入額を記録する」というものだった。これは Welcart の他の
多くの機能（商品は `usces_item` 投稿タイプ）から類推した、実ソースを確認する前の
推測だった。

Welcart 実ソース（`classes/usceshop.class.php`）を確認したところ、**受注は独自テーブル
`wp_usces_order` に保存され、`wp_posts` には存在しない**ことが判明した。したがって
post meta API はそもそも使用できない（`update_post_meta( $order_id, ... )` を呼んでも
`$order_id` に対応する投稿が存在しないため、意図しない挙動になるか何も保存されない）。
Welcart 自身が受注メタ用に `$usces->set_order_meta_value()` / `$usces->get_order_meta_value()`
（実体は `wp_usces_order_meta` テーブルへの読み書き、`classes/usceshop.class.php:9334`,
`:9342`）を用意しており、実装ではこちらを使うよう訂正した
（コミット: `feat: 受注再計算で自プラグイン注入額のみを精緻に差し戻すよう改善`）。

**教訓**: 同一プラグイン内でも、エンティティによってデータの保存形式（投稿タイプ /
独自テーブル）が異なりうる。「他の機能が投稿タイプだから、このエンティティも
投稿タイプだろう」という類推は実ソース確認なしには成立しない。この訂正は
コードを書く前に発覚したため実装への混入はなかったが、`filter_cart_table_footer`
の引数仕様の誤り（上記1）と同種の「実ソース確認プロセスが機能した事例」として
記録する。

### 5. 受注再計算のコード修正後、検証環境（Docker + Playwright）で再検証した結果

上記4の修正（`fix: カート表フッター挿入時に $usces->cart のnull安全性を確保` /
`feat: 受注再計算で自プラグイン注入額のみを精緻に差し戻すよう改善`）はコードを
書いた別の generator サブエージェントによるものであり、本タスク（ドキュメント担当）
がDocker環境で実際に操作して以下を再検証した。

- 既存の Docker 環境（`docker compose -f docker/docker-compose.yml up -d`、既に起動済み）
  を用い、Playwright で管理画面にログインし直した（セッション切れのため
  `wp_set_password()` でパスワードを再設定した）。
- `vendor/bin/phpunit` / `vendor/bin/phpcs` をコンテナ内で直接実行し、修正後のコードで
  `composer test`（15 tests, 17 assertions, OK）・`composer lint`（違反0件）を再確認した
  （ローカル環境に PHP が入っていなかったため、`docker exec docker-wordpress-1 ...` の形で
  実行した。既存の `vendor/` は bind mount 済みのため再インストール不要だった）。
- 以前の検証（項目6補足）で作成済みの受注（ID 1000、修正前のコードで作成されたため
  `wcd_injected_discount` メタが未記録）を使い、受注編集画面での「Recalculation」を
  実際にクリックして再検証した。結果、`docs/design-notes.md`「受注再計算の既知の限界
  （2周目修正で判明）」に記録した通り、**メタ未記録の既存受注に対する1回目の再計算は
  実際に二重計上した値（-1000）を返すこと**、**メタが正しく記録された状態からの
  再計算は正しい値（-2000）を返し、複数回クリックしても値が変わらないこと**を
  Docker環境で確認した。カート画面（フロント）についても、商品をカートに追加して
  「自動割引 -¥500」の行が正しく表示されることを確認し、null 安全性ガードの追加が
  通常のカート表示を壊していないことを確認した（`$usces->cart` が実際に null になる
  状況そのものは、通常のブラウザ操作では再現できなかった。これはコードレビューで
  対処した防御的分岐であり、この経路自体をDocker環境上の実際の操作で発火させることはできていない）。
- スクリーンショットは `docs/screenshots/12-recalc-known-limitation-first-time.png`
  （メタ未記録の受注での1回目の再計算、-1000 という誤った表示）、
  `docs/screenshots/13-recalc-clean-post-fix-2000.png`
  （メタが正しく記録された状態からの再計算、-2000 という正しい表示。2回連続で
  クリックしても値が変わらないことも確認）、
  `docs/screenshots/14-cart-regression-after-null-safety-fix.png`
  （null安全性修正後のカート画面。通常表示にリグレッションがないことの確認）
  として保存した。詳細な手順・DB上の状態遷移は `docs/verification.md` の
  「8. 受注再計算の精緻化（2周目修正）の再検証」に記録した。

なお、本再検証（2周目修正時点）は新規に「カート30,000円→受注確定」というフロントの
購入フローを最初からやり直すのではなく、既存の受注（ID 1000）を SQL で意図的に
既知の状態（メタ未記録／メタ記録済みの双方）に戻しながら行った。理由は、上記
「うまくいかなかったこと」に記録した通り、Welcart 管理画面の商品登録・購入確認フローが
Playwright の合成操作と相性が悪く、フルフローを毎回再現するコストが高いためである。また、
既存受注を使う方が「本修正より前に作成された受注」という、まさに検証したい既知の
限界のシナリオを正確に再現でき、検証の目的に対してより直接的だった。

**この判断はしかし、evaluator の REJECT（`order-meta-hook-coverage` 軸）で指摘された
通り、「`usces_action_reg_orderdata` フック自体がフロント購入フローを通じて実際に
発火することを確認していない」という別の穴を生んでいた**。テスト商品（TEST-40000・
TEST-3000）はすでに登録済みだったため、3周目修正では新規商品登録は不要であり、
SKUを再利用してフロントの購入フローを最後まで実行することが実際にはそれほど
コストが高くないことが分かった。3周目修正でのフルフローをDocker環境で確認した結果は
「AIの出力が誤っていた箇所」6を参照。

### 6. `usces_action_reg_orderdata` フックの新規受注フローでの発火が未確認だったこと（3周目修正で解消）

evaluator の REJECT（`order-meta-hook-coverage` 軸）は、2周目修正で追加した
`WCD_Integration::record_injected_discount_on_order_registration()`
（`usces_action_reg_orderdata` に登録）について、フロントの購入フローを通じて
実際に発火し、正しい値を `wp_usces_order_meta` へ記録することを確認したDocker環境での検証記録が
存在しない、と指摘した。指摘は正確だった。2周目の再検証（上記5）は既存受注に対する
SQL 状態操作のみで、フックのコールバック自体が呼ばれたことは確認していなかった。

3周目修正で、既存のテスト商品（TEST-40000・TEST-3000）を使いフロント購入フローを
最後まで実行し、新しい受注（ID 1001）を確定させた。DB を直接クエリしたところ
`wp_usces_order_meta`（order_id=1001）に `wcd_injected_discount = 2000` が
自動的に記録されており、`record_injected_discount_on_order_registration()` が
実際の受注登録経路で正しく発火・記録することを確認できた。詳細は
`docs/verification.md`「9. 新規受注（フロント購入フロー）での受注メタ自動記録の
Docker環境での確認」を参照。

### 7. -¥2,500 という数値と UI 経由の回避策がDocker環境で未確認のまま記載されていたこと（3周目修正で訂正）

evaluator の REJECT（`known-limitation-precision` 軸）は、`docs/design-notes.md`
「受注再計算の既知の限界」節が、-¥1,000 の状態から数量10へ変更して再計算すると
-¥2,500 になる、という記述を「Docker環境で確認した」としていたが、実際には
`docs/verification.md` 8-1 節は数量を変えずに「Recalculation」を押して -¥1,000 に
なったことのみを確認しており、8-2 節はそこから連続してではなく SQL で改めて
クリーンな状態に戻してから数量10への変更を検証していたため、-¥1,000 の状態から
連続して数量10に変更した場合の -¥2,500 という数値はDocker環境で確認されていない
数式上の推定値だった、と指摘した。また、回避策として記載していた「Campaign discount
欄を UI で手動編集して保存する」手順も、実際には SQL による DB 直接書き換えでしか
検証していなかった。いずれも指摘は正確だった。

3周目修正で、指摘された未検証の操作を実際に行った。既存受注（ID 1000）を
SQL で「メタ未記録・数量4・discount=-500」の状態に戻し、まず数量を変えずに
「Recalculation」を押して -¥1,000 になることを再現した。**その状態のまま
SQL でリセットせず**続けて数量を10（¥30,000）に変更し、もう一度
「Recalculation」を押したところ、表示は実際に **-¥2,500** となり、
`docs/design-notes.md` の数式（telescoping 展開）による推定値と正確に一致した。
続けて、この誤った状態から実際に管理画面の Campaign discount 欄（UI）へ `-2000`
を直接入力し「change decision」で保存する操作を行い、保存後に DB
（`order_discount = -2000.00`）へ正しく反映されること、以降の「Recalculation」が
-¥2,000 のまま安定することを確認した。数式による事前の推定は結果的に正しかったが、
「推定をDocker環境での確認と偽って書かない」というドキュメントの正確性の観点では2周目時点の
記述に誤りがあった。詳細は `docs/verification.md`「10. 受注再計算の既知の限界の
追加検証」を参照。

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

### 受注再計算の精緻化後も残る既知の限界（未解決・正直に記録する）

上記「AIの出力が誤っていた箇所」4・5に記録した通り、受注再計算の精緻化
（`wcd_injected_discount` メタによる差し戻し）は、**本修正より前に作成された受注**
（メタが一度も記録されていない受注）に対しては、修正後最初の1回の再計算に限り
二重計上と同種の誤った値を返してしまう限界が残っている。2周目時点のDocker環境での検証では、
数量を変えずに「Recalculation」を押して -¥1,000 になることまでは確認していたが、
**それ以降の再計算・数量変更でも、最初の誤りで生じた一定のずれ（本検証では¥500）を
含んだ値を返し続ける**という点（数式による説明は `docs/design-notes.md`
「受注再計算の既知の限界」節に記録）は、実際には数式からの推定でありDocker環境で確認して
いなかった。この点は evaluator の REJECT（`known-limitation-precision` 軸）で
指摘を受け、3周目修正で「AIの出力が誤っていた箇所」7に記録した通りDocker環境で確認し、
-¥1,000 の状態から連続して数量10へ変更すると実際に -¥2,500 になる（推定と一致）
ことを確認した。この状態から正しい値に戻すには、管理者が「Campaign discount」欄の
値を手動で正しい額に修正して保存する（再計算フィルタを経由しない）必要があり、
この回避策も3周目修正で SQL ではなく実際の管理画面 UI 操作としてDocker環境で確認した。

この限界は、本修正のスコープでは解消しきれなかった。理由は、Welcart のフィルタ
引数だけでは「そもそも過去にこのプラグインが割引を注入したことがあるかどうか」を
判別する手段がなく（メタが存在しない＝未注入なのか、単に旧バージョンのプラグインで
注入したが記録の仕組みがなかっただけなのかを区別できない）、後者を安全側に倒すと
（＝メタなしを「注入額0」と仮定する）今回確認した限界が残り、逆に前者を安全側に
倒す判断基準もない、というトレードオフのためである。実運用では「本プラグインの
バージョンアップ直後に全受注へ一括でメタを再構築するマイグレーション処理」を
別途用意すれば解消できるが、本課題のスコープ（第一段階の実装）を超えるため
今回は実装していない。

### 国際配送バリデーションで「Delivery method is incorrect」エラー

購入確認画面で「Next」を押すと `Delivery method is incorrect. Specify the
international flights.` というエラーで先に進めない事象が発生した。
配送方法設定で「Possible Delivery Area」を Domestic Shipment に設定していたが、
テスト用の顧客住所を米国の州（カリフォルニア州）で入力していたため、
Welcart 側がこれを Domestic ではなく International 扱いと判定していたことが
原因と推測される。配送方法の設定を International Shipment に切り替えることで
回避した。根本原因（Welcart の国内/海外判定ロジックの詳細）までは
時間の制約上、深く追跡していない。

**3周目修正での追記**: 3周目修正のフロント購入フローのDocker環境での確認（`docs/verification.md`
「9」節）では、同じくカリフォルニア州の顧客住所で「通常配送」（Domestic 側の設定の
まま）を選択したにもかかわらず、このエラーは再現しなかった。原因の切り分けは
行っていないが、以前の検証時に配送設定を International Shipment に切り替えた変更が
そのまま残っていた可能性、またはセッション・カート内容（今回はセッションに残っていた
既存カート内容と新規追加商品が混在していた）の違いが影響した可能性がある。
いずれにせよ、これはプラグイン自体の割引ロジックとは無関係な Welcart 配送設定の
挙動であり、本課題のスコープ外として深追いはしていない。

### 未完了だった項目（第一段階時点）

実装計画タスク15のステップ3〜8はすべて実施できた。3周目修正では、evaluator の
指摘に基づき、新規受注フローでのフック発火確認（`docs/verification.md` 9節）と
既知の限界の追加検証（同10節）を追加でDocker環境で確認した。第二段階（会員ランク・
商品カテゴリ除外の実装）は本設計書・実装計画の対象外であり、この時点では
着手していなかった（当初から意図的にスコープ外としていた）。以下「第二段階:
除外条件設定」に、実際に実装した経緯を記録する。

---

## 第二段階: 除外条件設定（会員ランク・商品カテゴリ）

加点要件「特定の会員ランクや商品カテゴリを除外する条件設定」の実装。設計は
`.nipper/chot/specs/2026-08-20-welcart-cart-discount-exclusions-design.md`、
実装計画は `.nipper/chot/plans/2026-08-20-welcart-cart-discount-exclusions.md`。

brainstorming → writing-plans → generator（並列生成、タスク1〜11をバッチで
割り当て）→ docs（タスク12・提出物ドキュメント更新）という基本の流れ自体は
第一段階と同じ chot-harness の構成を踏襲したが、実際にはそこで終わらなかった。
docs 完了後、evaluator が2周目の評価で REJECT と判定し（`rank-resolution-context-correctness`
軸・`test-coverage-quality` 軸）、コード側の該当箇所を修正した（受注再計算部分の
try/finally による静的プロパティ保護、および会員ランク0の境界値の回帰テスト追加。
コミット `d66e4e9`・`38a9ae6`）。しかしこの2周目修正はコードのみに留まり、
本レポート・`docs/design-notes.md`・`docs/verification.md` のいずれにも反映されて
いなかったため、evaluator は3周目の評価で再び REJECT と判定した（`ai-report-accuracy`
軸・`eval-feedback-loop-documented`軸・`documentation-cross-consistency`軸ほか）。
本節（本タスク）はその3周目の指摘を受けたドキュメント側の修正である。
第一段階（本レポート冒頭「使用ツールと進め方」4参照）でも同種の evaluator
REJECT→修正サイクルが発生しており、第二段階でも同じ構造の指摘が再現したことになる。
詳細は後述の項11に記録する。

### 8. writing-plans フェーズで発見した設計書内の記述矛盾

除外条件設計書の「既存ファイルへの変更」表は `includes/class-wcd-integration.php` を
「変更しない」と記載していたが、同じ設計書内の「データフロー／経路2」節は、受注編集の
再計算時に対象受注IDを `WCD_Exclusion` へ通知する静的プロパティのセット／解除を
`filter_order_recalculation()` の実行中に行うと明記しており、両者は両立しない
（`$order_id` を保持しているのは `filter_order_recalculation()` の引数のみであり、
そこから2行追加する以外に受け渡す手段がない）。

**発見方法**: 設計書の2つの節を実装計画作成のために突き合わせた際、「ファイル変更なし」
という記述と「そのファイルのメソッド本体を編集する」という記述が矛盾していることに
気づいた。コードを書く前、計画段階での突き合わせで発見できたため、実装のやり直しは
発生していない。

**修正方法**: 「フィルタのシグネチャ・フィルタ契約（引数の数や意味）は変えないが、
メソッド本体には最小限の2行（`WCD_Exclusion::begin_order_recalculation( $order_id )` /
`end_order_recalculation()`）を追加する」という、両節の意図（安全に実装したいという
制約と、正しくランクを解決したいという要件）を両立させる方針に修正した
（タスク7、コミット `feat: 受注再計算時に対象受注IDを WCD_Exclusion へ通知する`）。

### 9. `WCD_Exclusion_Settings::normalize()` の categories 正規化バグ（タスク1担当が実装中に発見）

タスク1（`WCD_Exclusion_Calculator`）の担当ではなく、実際にはタスク2
（`WCD_Exclusion_Settings::normalize()`）の実装中に、TDD のテスト
（`test_casts_category_to_absint_and_discards_non_positive`、`array( '5', 0, -1 )` を
渡すと `array( 5 )` のみが残るべき、という期待値）を書いた段階で、計画書のサンプル
コードそのものに論理バグがあることに気づいた。計画書のコードは `absint()` を先に
適用してから `$category_id <= 0` で0以下判定を行っていたため、`absint( -1 )` が
絶対値の `1` になり、非正数判定をすり抜けて `1` として残ってしまう
（本来は破棄すべき負値のカテゴリIDが有効なカテゴリIDとして保存される不具合）。

これは第一段階の `WCD_Settings::normalize()` で踏んだのと同種の落とし穴
（本レポート「AIの出力が誤っていた箇所」2、`absint()` を先に適用すると符号情報が
失われる）が、今回は設計書・実装計画のサンプルコード自体の記述段階で再発したもの。
第一段階では実装前のコードレビューで防げていたが、今回は計画書の疑似コードに
紛れ込んでいたため、実装担当の generator が TDD のステップ2（失敗するテストの実行）
で実際に検出する形になった。

**修正**: チェック順序を入れ替え、`is_numeric( $category ) && $category <= 0` の判定を
`absint()` 適用前の生値に対して行うようにした（`includes/class-wcd-exclusion-settings.php`）。
第一段階のコメント規約を踏襲し、同じ理由をコード内コメントとして残している。

### 10. WPCS違反の修正と、参照した先例コミットのハッシュ表記の誤り

タスク10（WPCS準拠確認）で `composer lint` を実行したところ、3件の違反
（DocComment短文の書き出し規則、ブロックコメント前の空行不足、インラインコメント
末尾の全角句点）が検出された。第一段階の先例（実装計画・本レポートともに
`344b685 style: WPCS違反を解消` という表記で参照していた）と同じ方針（`phpcbf` の
一括自動修正は使わず、各違反の意味を確認しながら手動で直す。全角句点をASCIIピリオド
に統一する等）で手動修正した（コミット `0e062b6 style: 除外条件実装のWPCS違反を
解消`）。

**表記の誤りの発見**: 修正時に先例コミットを `git log` で確認したところ、実際の
ハッシュは `2042131`（`git log --pretty=format:"%h %ad %s" --date=iso --reverse` の
出力）であり、実装計画・本レポート冒頭「コミット順序と実装計画のタスク番号の関係」に
記載していた `344b685` はこのリポジトリの現在の履歴には存在しないハッシュだった
（`git cat-file -t 344b685` は `fatal: Not a valid object name` を返す）。コミット
メッセージ・日時（`2026-08-18 14:48:58 +0900`）は一致するため同一コミットを指しているが、
ハッシュ値そのものが本レポート初版の記録時点から変化している。原因は本レポート作成後の
リポジトリ側の履歴操作（例: 大容量ファイルの除去や初期コミットの手直しに伴う後続コミットの
連鎖的なハッシュ変化）と推測されるが、本タスクの範囲では特定していない。本節では
実際に確認できた正しいハッシュ `2042131` を記録するに留め、冒頭の「コミット順序」節の
埋め込みログ全体（第一段階分）を遡って書き換えることはしていない（当時実際に出力された
コマンド結果の記録として残す方が、後から辻褄を合わせるより誠実だと判断した）。読者が
先例コミットを実際に参照する場合は、コミットメッセージ・日時、または現在の
`git log --oneline` の該当行（`2042131 style: WPCS違反を解消`）を使うこと。

### 11. 受注再計算の例外安全性とランク0境界値のテスト漏れ（2周目のevaluator REJECTで指摘・修正、3周目でドキュメント化）

タスク12（本節冒頭のドキュメント更新）を終えた時点のコードに対し、evaluator が
2周目の評価で2件の指摘を行った（`rank-resolution-context-correctness` 軸・
`test-coverage-quality` 軸。この時点の指摘は現在の `.nipper/chot/feedback.md` には
残っていない（その後の3周目評価の内容で上書きされている）が、指摘内容と対応する
コミットは以下の通り実在する）。

**指摘1（`rank-resolution-context-correctness` 軸）**: 上記8で追加した
`WCD_Exclusion::begin_order_recalculation() / end_order_recalculation()` の対称呼び出しは、
実装当初 `WCD_Integration::filter_order_recalculation()` 内で
`begin...(); $amount = self::calculate_amount( $cart ); end...();` という素朴な直列呼び出しに
なっていた。`calculate_amount()` の内部では `wcd_eligible_subtotal` / `wcd_available_rules`
という、他プラグインも購読しうる公開フィルタを呼び出しており、これらのコールバックが
例外を送出すると `end_order_recalculation()` が実行されないまま関数を抜け、静的プロパティ
`WCD_Exclusion::$recalculating_order_id` が残留してしまう。残留すると、以降にカート画面・
購入確認画面で発生するランク解決（本来はセッション中のログイン会員を見るべき）が誤って
「受注再計算中」の分岐に迂回し、無関係な購入者のランクを受注の持ち主のランクと誤認する
おそれがあった。指摘は技術的に正しかった。

**指摘2（`test-coverage-quality` 軸）**: `WCD_Exclusion_SettingsTest.php` の ranks 系テストが、
会員ランク `0` の境界値を検証していなかった。`WCD_Exclusion_Settings::normalize()` の
`ranks` は「`known_ranks` に含まれていれば保持し、含まれていなければ破棄する」という
仕様であり、これは同じ `normalize()` 内の `categories`（「0以下は無条件に破棄する」）とは
非対称な境界挙動である。この非対称性を固定する回帰テストが欠けていた、という指摘も
正確だった。

**修正方法**:
- 指摘1に対し、`calculate_amount( $cart )` の呼び出しを try/finally で囲み、例外発生時にも
  `end_order_recalculation()` が確実に呼ばれるよう修正した
  （`includes/class-wcd-integration.php`、コミット `d66e4e9 fix: 受注再計算の例外発生時にも
  受注ID通知を確実に解除する`）。
- 指摘2に対し、`test_rank_zero_is_preserved_when_in_known_ranks()` /
  `test_rank_zero_is_discarded_when_not_in_known_ranks()` の2件を
  `WCD_Exclusion_SettingsTest.php` に追加した（コミット `38a9ae6 test: 会員ランク0の境界値
  （categoriesとの非対称性）を回帰テストに追加する`）。実装コード
  （`includes/class-wcd-exclusion-settings.php`）自体は変更していない。既存実装のまま
  2件ともPASSすることを確認済み。

この2件のテスト追加により、単体テストの総数は次項「単体テスト件数」に記録した
**44件から46件**へ増えた。**しかし `docs/verification.md`「最終確認」節（`composer test` /
`composer lint` の最終確認）は、この2周目修正より前の44件のまま更新されておらず、
実際のコードベースと食い違っている**（`docs/verification.md` の当該記録を追加した
コミット 1258924 は 2026-08-20 10:37 時点、d66e4e9・38a9ae6 は同日 10:49 時点であり、
後者の方が新しい）。この訂正は `docs/verification.md`「18. composer test /
composer lint の最終確認」節で、本タスクと並行するもう一方の generator により
コミット `4028058`（本コミット `01f7ca8` より後）で既に反映済みであり、両ドキュメント
とも現在は「46 tests, 53 assertions」で一致している。なぜ44件と46件の2つの数字が
一時的に併存していたのかという経緯を、ドキュメント側からも本項で追えるようにしておく。

**教訓**: 2周目修正でコード（try/finally・回帰テスト）は正しく直したにもかかわらず、
その修正をドキュメント（本レポート・`docs/design-notes.md`・`docs/verification.md`）に
反映する作業が漏れていた。第一段階では evaluator REJECT サイクルのたびに
「AIの出力が誤っていた箇所」の項目として記録していた（上記4・6・7）が、第二段階では
この記録作業自体が抜け落ち、3周目の evaluator の再REJECTで指摘されて初めて気づいた。
コード修正とドキュメント更新を同じレビューサイクルで扱わないと、両者が容易に
乖離するという実例として記録する。

### 単体テスト件数: 実装計画の想定（32件）と実際（44件→2周目修正で46件）の乖離

タスク1の実装中、実装計画の「タスク3」節が「第一段階の既存15件 + タスク1の10件 +
タスク2の7件 = 32件」と想定していたのに対し、実際に `tests/unit/` に存在する
既存テストは27件（`WCD_RuleTest` 4件、`WCD_CalculatorTest` 6件、`WCD_SettingsTest`
6件、`WCD_Cart_Row_BuilderTest` 11件）であることに気づいた。第一段階の完了後、
本レポート初版記載の15件から `WCD_Cart_Row_BuilderTest`（11件）が追加されており、
実装計画作成時に参照した「15件」という数字はその追加前の値のまま古くなっていた。
新規17件（`WCD_Exclusion_CalculatorTest` 10件 + `WCD_Exclusion_SettingsTest` 7件）と
合わせた実装当初の合計は **44件**であった。この乖離は機能に影響しないため実装方針は
変更していないが、実装計画中の期待値の数字を鵜呑みにせず `phpunit`
（`composer test`）の実際の出力で都度確認する必要がある、という実例として記録する。

**その後の更新（2周目修正）**: 上記「11. 受注再計算の例外安全性とランク0境界値の
テスト漏れ」で記録した通り、2周目のevaluator REJECTを受けて`WCD_Exclusion_SettingsTest.php`
にランク0境界値の回帰テスト2件（コミット `38a9ae6`）を追加したため、**現在の実際の
合計は46件**である（`vendor/bin/phpunit` 実測: 46 tests, 53 assertions）。本節が
記録する44件という数字は実装当初（タスク1〜11完了時点）のものであり、現時点の
最終値ではない点に注意すること。

### 並行 generator 間のコミット競合とその復旧（並列運用上の実例）

タスク7（`WCD_Integration::filter_order_recalculation()` への文脈受け渡し追加）担当と
タスク8・9（`WCD_Admin` の除外設定UI追加・保存処理接続）担当は、generator フェーズで
同一バッチ内の別サブエージェントとして並行実行された。作業対象ファイルは分かれていた
（タスク7は `includes/class-wcd-integration.php`、タスク8・9は
`includes/class-wcd-admin.php`）にもかかわらず、タスク7担当が `git add` 後に
`git commit` した際、並行してステージされていたタスク8・9担当の
`class-wcd-admin.php` の変更を巻き込んでコミットしてしまう競合が一時的に発生した。
双方とも、他方が作業ツリーに残していた変更履歴を失わせないよう `git rebase` や
`git commit --amend` は使わず、`git reset --soft` で直前のコミットを取り消してから
対象ファイルを個別に `git add` し直し、意図通りの分離コミット（`0801e20` /
`b7bc047` / `63a232e`）に復旧した。この経緯は `git reflog` にも残っている
（`53713ee` への `reset: moving to HEAD~1` のエントリ）。同一リポジトリ上で複数
generator サブエージェントが並行して `git add` / `git commit` を行う運用では、
ステージング領域が共有資源になるため、担当ファイルを明示的に指定した `git add`
（`git add -A` を避ける）だけでは競合を完全には防げず、発生時の復旧手順
（他者の履歴を書き換えない `git reset --soft` の使用）まで含めて運用ルールに
織り込む必要があることを示す実例だった。

### 第二段階のコミット順序と実装計画のタスク番号の関係

`git log --pretty=format:"%h %ad %s" --date=iso --reverse` で確認すると、第二段階
（タスク1〜11）の実際のコミット順序は次の通りで、計画のタスク番号順（1→11の昇順）と
**完全に一致した**。

```
eae51e1 2026-08-20 10:03:48 +0900 feat: WCD_Exclusion_Calculator による除外の純粋計算を追加        (タスク1)
c542bff 2026-08-20 10:05:12 +0900 feat: WCD_Exclusion_Settings::normalize() による除外設定の正規化を追加  (タスク2)
adaac81 2026-08-20 10:05:46 +0900 chore: 除外設定クラスの require を配線する                          (タスク3)
6300dec 2026-08-20 10:07:16 +0900 feat: WCD_Exclusion_Settings に option の読み書きを追加            (タスク4)
897cc2f 2026-08-20 10:07:53 +0900 feat: WCD_Exclusion アダプタでカテゴリ除外・ランク除外のフィルタコールバックを実装 (タスク5)
53713ee 2026-08-20 10:08:55 +0900 feat: 除外用フィルタ2本を WCD_Exclusion に登録する                  (タスク6)
0801e20 2026-08-20 10:09:41 +0900 feat: 受注再計算時に対象受注IDを WCD_Exclusion へ通知する            (タスク7)
b7bc047 2026-08-20 10:10:51 +0900 feat: 設定画面に除外条件（会員ランク・商品カテゴリ）のUIを追加        (タスク8)
63a232e 2026-08-20 10:11:08 +0900 feat: 除外設定の保存を既存のnonce・権限チェックに相乗りさせる         (タスク9)
0e062b6 2026-08-20 10:14:09 +0900 style: 除外条件実装のWPCS違反を解消                                (タスク10)
e1884f9 2026-08-20 10:15:26 +0900 chore: 除外設定の翻訳文字列を .pot に反映する                       (タスク11)
```

第一段階（本レポート冒頭「コミット順序と実装計画のタスク番号の関係」節）では、
Docker/CI 設定タスクの先出しやタスク6〜10の内部順序の入れ替わりなど、計画の番号順との
乖離が生じていた。第二段階でこの乖離がほぼ解消された理由は、今回はタスク間の依存関係が
直列に近い形（`WCD_Exclusion_Calculator` → `WCD_Exclusion_Settings` → require配線 →
`get`/`save` → アダプタ → フィルタ登録 → Integration連携 → Admin UI → Admin保存 →
lint → i18n）で、独立して並列実行できるタスクの組が第一段階ほど多くなかったためと
考えられる。唯一、タスク7とタスク8・9は実際には同一バッチで並行実行されており（上記
「並行 generator 間のコミット競合とその復旧」参照）、コミットの一時的な競合が発生した
にもかかわらず、復旧後の最終的なコミット順序は計画のタスク番号順と一致した。

---

## E2E テスト（Playwright）

加点要件「テストコード」の拡張として、`docker/` 検証環境に対する Playwright E2E テスト
（`e2e/`）を追加した。設計（`.nipper/chot/specs/2026-08-20-e2e-tests-design.md`）・
実装計画（`.nipper/chot/plans/2026-08-20-e2e-tests.md`）の作成、実装（タスク1〜9）、
本節を含むドキュメント更新（タスク10・11）はいずれも Claude Code が同一セッション内で
担当した。

**実装計画そのものがコード例を含んでおり、それ自体が AI（writing-plans フェーズの
Claude Code）の出力である。** 実装（タスク1〜9）でDocker環境での検証を行うと、計画書のコード例の
大半に実際の Welcart 挙動との相違が見つかった。「計画書は正しいはず」と鵜呑みにせず、
1つずつDocker環境で実際に確認しながら実装した結果、以下の誤りを実装が実際にコードへ混入する前に
（あるいは1回の実行結果として）発見・修正できた。第一段階・第二段階の教訓
（実ソース確認プロセスの重要性、上記1・4・8など）が、E2Eというブラウザ操作の領域でも
同型で再現した形になる。

### 12. 商品の投稿タイプに関する誤った前提

Welcart の商品は独自の投稿タイプ（`usces_item` 等）を持つという想定は、第一段階の
「受注が独自テーブルか投稿タイプか」（上記「AIの出力が誤っていた箇所」4）と対になる
誤りだった。実際には `post_type=post` に `post_mime_type=item` を付与した投稿として
保存されている。加えて `wp post list --post_type=... --post_mime_type=item` は
`WP_Query` 経由だと0件を返す（Welcart 側のフックが介在するとみられる。原因の完全な特定
までは行っていない）こともDocker環境での検証で判明した。`e2e/bin/env-up.sh` の商品数チェックは
`wp eval` 経由で `$wpdb->get_var()` により `wp_posts` を直接カウントする方式に修正した
（`WHERE post_type="post" AND post_mime_type="item" AND post_status="publish"`）。

### 13. カート画面のURL（`/usces-cart/` ではなく `/?page_id=5`）

設計書・実装計画のコード例はいずれもカートURLを `/usces-cart/` と想定していたが、
この検証環境のパーマリンク設定が「基本」（プレーン）であるため実際には404になる
（`curl` で確認）。カートページの実体は固定ページで、そのページIDは option
`usces_cart_number` が保持している（この環境では5）。`e2e/helpers/shop.ts` の
`CART_URL` を `/?page_id=5` に修正した。

### 14. 商品ページへの導線（トップページの一覧に全商品が出ない）

実装計画はトップページから商品名リンクを探してクリックする方式を想定していたが、
実測するとトップページには25商品中一部（6点）しか一覧表示されず、テストで使う
Practice Pad Set 等がそこに現れなかった。`ITEMS` に商品ページURL（`?p=<投稿ID>`）を
直接持たせ、そこへ遷移する方式に変更した（`e2e/helpers/shop.ts`）。

### 15. `page` フィクスチャのシャドウィングによるカート内容の喪失（実装中に発生・自己発見して修正）

Welcart のカートはセッション（cookie）で保持される。Playwright の `page` フィクスチャは
テストごとに新しいブラウザコンテキスト（cookie未保持）を生成するため、serial実行の
複数テストで素朴に `{ page }` を受け取ると2件目以降でカートが空になる問題があった。
これは計画書のコード例（各 `test(...)` のコールバックで `{ page }` を分割代入する形）を
そのまま実装したことで**実際に一度発生させてしまった**不具合であり、他の項目のような
「実装前に発見した誤り」ではない。`test.beforeAll` で単一の `page` を生成して
`describe` ブロック内の全テストで使い回す方式に修正して解決した
（`e2e/tests/01-cart-display.spec.ts`、`02-checkout-consistency.spec.ts`）。

### 16. 「次へ」ボタンの name 属性は画面ごとに異なる

実装計画は購入フロー全画面で共通の `nextpage` という name を想定していたが、実際には
画面ごとに異なる（カート→お客様情報: `customerinfo`、お客様情報→配送: `deliveryinfo`、
配送→確認: `confirm`、確認→完了: `purchase`）。実際にカートから購入完了まで進めて
確認し、`e2e/helpers/shop.ts` の `completePurchase()` を画面ごとの実測値に修正した。

### 17. お客様情報フォームの入力項目不足

実装計画は住所欄を単一の `address1` のみと想定していたが、実際のフォームは
`pref`（都道府県、セレクト必須）／`address1`（市区郡町村）／`address2`（番地、
「＊番地」として必須）の3分割で、`address2` を埋めないと先へ進めなかった。
メールアドレスも確認用の `mailaddress2` が別途必須だった。いずれも計画書のコード例には
含まれておらず、実ページのフォームを確認して `TEST_CUSTOMER` とフォーム入力処理
（`completePurchase()`）に追加した。

### 18. 完了画面に受注番号が表示されない（正規表現スクレイピング方式は成立しなかった）

実装計画は完了画面の本文を正規表現（`/(\d{3,})/` 等）でスクレイピングして受注IDを
取得する方式を想定していたが、検証用テーマの完了画面は Welcart 本体の汎用完了
テンプレートをそのまま使用しており、受注番号を一切表示しない（定型文のみ）ことが
Docker環境での検証で判明した。この方式は原理的に成立しない誤りだった。`e2e/helpers/wpcli.ts` に
`getLatestOrderId()` を追加し、`wp eval` 経由で `$wpdb->get_var("SELECT MAX(ID) FROM
{$wpdb->prefix}usces_order")` により受注テーブルを直接問い合わせる方式に変更した。

### 19. 受注編集画面のURLはnonce必須で直接構築できない

実装計画は `admin.php?page=usces_orderlist&order_action=edit&order_id=<ID>` へ
直接遷移する方式を想定していたが、実際にはnonceが無いとWelcart側のディスパッチャが
リクエストを受け付けず、受注リスト画面が表示されるだけで編集画面に遷移しない。
WP-CLIで生成したnonce（`wp_create_nonce()`）はログインセッションのセッショントークンを
鍵の一部に含むため、ブラウザのログインセッションに対しては使えない。そのため、受注
リスト画面を開いて実際に描画された（本物のnonce付きの）リンクを`href`から取得し、
そこへ遷移する方式に変更した（`e2e/helpers/admin.ts` の `openOrderEdit()`）。
実装の過程で、`href`の部分一致セレクタでは`order_id=101`が`order_id=1014`にも
誤って一致することにも気づき、`URL#searchParams`で厳密に比較する方式にした。

### 20. `wp db query` のTLS/SSLエラー

計画書の調査ステップは `wp db query` によるDB直接問い合わせを例示していたが、この
検証環境では `wp db query` がTLS/SSLエラーで失敗する。DB直接問い合わせが必要な箇所
（受注ID取得など）はすべて `wp eval` 経由で `$wpdb` を使う方式に統一した。

### 21. 意図的な失敗確認（空振り検知）の実施記録（2周目修正）

evaluator の REJECT（`idempotency-verification-integrity` 軸・`intentional-failure-checks` 軸・
`plan-compliance` 軸）を受けて、実装計画タスク4ステップ5・タスク7ステップ3が要求する
「意図的に条件を壊してテストが空振りしないことを確認する」検証を、稼働中の Docker
検証環境（`http://localhost:8080`）に対して実際に自分の手で再実施した。evaluator の
指摘は、検証自体は evaluator が代わりに実施して健全性を確認済みだが、**提出物のどこにも
実施記録が残っておらず第三者が確認できない**という点だった。以下はその再実施の記録であり、
実行したコマンドと実際の出力をそのまま残す。

#### タスク4ステップ5: `wcd_settings` を無効化して `01-cart-display.spec.ts` を落とす

計画書どおりにまず以下を実行した。

```bash
docker compose -f docker/docker-compose.yml run --rm -T wpcli wp --path=/var/www/html \
  option update wcd_settings '[]' --format=json
cd e2e && npx playwright test tests/01-cart-display.spec.ts
```

結果は **3件とも PASS**した。計画書が想定する「割引行が出なくなり FAIL する」にはならず、
一見すると空振りを起こしているように見えた。原因を調べたところ、`01-cart-display.spec.ts`
自身の `test.beforeAll`（`e2e/tests/01-cart-display.spec.ts:15-18`）が

```typescript
test.beforeAll(async ({ browser }) => {
  resetToKnownState()
  page = await browser.newPage()
})
```

という形で `resetToKnownState()` を呼んでおり、これがテスト本体の実行前に
`wcd_settings` を既知の2段ルールへ**上書きして戻してしまう**ため、外部から
`wp option update` で壊した値はテストが実際に検証を始める時点ではすでに元に戻っていた
（`option get wcd_settings` で確認すると2段ルールのままだった）。

これは実装計画が書かれた時点（タスク4ステップ3で `beforeAll` に `resetToKnownState()` を
入れる設計にした）と、タスク4ステップ5が想定する「外部から option を壊せばテスト実行中も
壊れたままのはず」という前提が、計画内部で矛盾していたことを意味する。calling
`resetToKnownState()` in `beforeAll` は「他 spec の状態を持ち越さない」という別の目的で
必要な設計であり、削除すべきものではない。そこで、この意図的な失敗確認の**間だけ**
`beforeAll` 内の `resetToKnownState()` 呼び出しを一時的にコメントアウトし
（`e2e/tests/01-cart-display.spec.ts` を一時改変、コミットしない）、その状態で
`wcd_settings` を `[]` にしてから再実行した。

```bash
docker compose -f docker/docker-compose.yml run --rm -T wpcli wp --path=/var/www/html \
  option update wcd_settings '[]' --format=json
cd e2e && npx playwright test tests/01-cart-display.spec.ts
```

結果:

```
✓  1 しきい値未満では割引行が出ない (886ms)
✘  2 1段目に到達すると -500 と割引後合計が出る (729ms)
   Error: expect(received).toBe(expected)
   Expected: 500
   Received: null
-  3 2段目に到達すると -2000 に切り替わり、累積しない（did not run）
1 failed, 1 did not run, 1 passed
```

1件目（しきい値未満）は割引ルールの有無にかかわらずそもそも割引が出ない条件のため
PASS のままなのが正しい。2件目（1段目）は割引額 `500` を期待して `null`
（割引行そのものが存在しない）を受け取り FAIL、3件目は `test.describe.configure({ mode:
'serial' })` により直前のテストが失敗した時点で以降が実行されない（`did not run`）。
計画書は「1段目・2段目のテストが FAIL する」と書いていたが、serial モードの仕様上
2件目は明示的な FAIL、3件目は「未実行」という形で現れる。いずれにせよ、割引が機能しない
状態でテストがそのまま PASS してしまう空振りは起きないことを確認できた。

確認後、`git checkout e2e/tests/01-cart-display.spec.ts` で一時改変を取り消し、
`./e2e/bin/env-reset.sh` で `wcd_settings` を既知の状態に戻した上で再実行し、
3件とも PASS することを確認した。

#### タスク7ステップ3: 差し戻しロジックを単純加算に書き換えて `02-checkout-consistency.spec.ts` を落とす

`includes/class-wcd-integration.php` の `filter_order_recalculation()` を実際に読み、
二重計上を防ぐ差し戻しロジックが（計画書が想定していた144-150行付近から行番号が
ずれておらず）現物でも144-150行の `else` 節にあることを確認した。

```php
} else {
    // この経路の $discount には前回本プラグインが注入した割引額が
    // 含まれている. その既知の値だけを差し戻し、他の割引成分を復元する.
    $previous_injected = self::get_injected_discount( $order_id );
    $other_components  = $discount + $previous_injected;
    $new_discount      = $other_components - $amount;
}
```

これを一時的に単純加算へ書き換えた（コミットしない）。

```php
} else {
    // TEMP(意図的な失敗確認用、コミットしない): 前回注入額を差し戻さない
    // 単純加算に書き換え、二重計上を検出できることを確認する。
    $new_discount = $discount - $amount;
}
```

実行:

```bash
cd e2e && npx playwright test tests/02-checkout-consistency.spec.ts
```

結果:

```
✓  1 カート画面と購入確認画面で割引額が一致し、購入を完了できる (5.1s)
✓  2 確定後の受注データにも同じ割引額が記録されている (1.7s)
✘  3 再計算を3回繰り返しても割引額が変動しない（二重計上の非再発） (1.1s)
   Error: 1 回目の再計算後
   expect(received).toBe(expected)
   Expected: 500
   Received: 1000
1 failed, 2 passed
```

計画書が想定した通り、1・2件目（カート・確認画面・受注データの3箇所整合）は割引の
初回注入ロジック自体には手を入れていないため PASS のまま、3件目（再計算のべき等性）は
1回目の再計算後に `500` ではなく `1000` を検出して期待通り FAIL した。これは
「前回注入額 -500 を差し戻さずに新しい割引額 -500 をさらに加算した結果、
-500 → -1,000 と二重計上される」という、このプラグイン最大のリスク領域（Welcart の
受注編集フォームが送信する `$discount` に前回保存値が含まれるという非直感的な仕様に
起因する不具合。詳細は本レポート「AIの出力が誤っていた箇所」3・4）を、E2Eが正しく
検知できることを直接示す結果である。

確認後、`git checkout includes/class-wcd-integration.php` で一時改変を取り消し、
`git status --short` で差分が無いことを確認した。

#### 元に戻した後の全件 PASS 確認

上記2件の意図的な失敗確認では、`02-checkout-consistency.spec.ts` の1件目が実際に
購入を完走させるため、対象商品（Practice Pad Set・Compact Tuner Pedal）の在庫を
消費する。今回の一連の再実施（`01-cart-display.spec.ts` を複数回、
`02-checkout-consistency.spec.ts` を複数回実行）の結果、この2商品の在庫
（`wp_usces_skus.stocknum`）が実際に0まで減少し、`docs/ai-report.md`
「うまくいかなかったこと・時間を要したこと（E2E、追記）」に既に記録されている
「商品在庫の枯渇による他specへの副作用」を、今回の検証作業自体で再現する形になった。
`env-reset.sh` は在庫を戻さない設計（意図的）のため、`wp eval` 経由で `$wpdb->update()`
により該当2商品の `stocknum` を種データの値（`docker/seed-items.php:345`）と同じ `10`
に戻してから、`./e2e/bin/env-reset.sh` で `wcd_settings` / `wcd_exclusions` を既知の
状態に戻し、`01-cart-display.spec.ts` と `02-checkout-consistency.spec.ts` を続けて
実行した。

```bash
cd e2e && npx playwright test tests/01-cart-display.spec.ts tests/02-checkout-consistency.spec.ts
```

結果:

```
✓  1 しきい値未満では割引行が出ない (792ms)
✓  2 1段目に到達すると -500 と割引後合計が出る (718ms)
✓  3 2段目に到達すると -2000 に切り替わり、累積しない (699ms)
✓  4 カート画面と購入確認画面で割引額が一致し、購入を完了できる (5.0s)
✓  5 確定後の受注データにも同じ割引額が記録されている (1.4s)
✓  6 再計算を3回繰り返しても割引額が変動しない（二重計上の非再発） (1.9s)
6 passed (15.8s)
```

6件全て PASS した。`git status --short` は空であり、`includes/class-wcd-integration.php`・
`e2e/tests/01-cart-display.spec.ts` のいずれも一時改変が残っていないことを確認済み。

**この検証の意味**: 二重計上バグ（-500 → -1,000 → -1,500 と積み上がる）は、本レポート
「AIの出力が誤っていた箇所」3で記録した通り、実装フェーズでは検出できずDocker環境での検証で
初めて発見された、このプラグインで最も再発しやすいリスク領域である。その修正の正しさを
守る `02-checkout-consistency.spec.ts` の3件目が、差し戻しロジックを欠いた実装に対して
確実に FAIL することを今回Docker環境で実際に確認できたことは、「テストが存在すること」ではなく
「テストが本当にこのリスクを検知できること」を担保する。また `01-cart-display.spec.ts`
側で発覚した `beforeAll` の `resetToKnownState()` が意図的な失敗確認を無効化するという
計画内部の矛盾は、計画書のコード例をそのまま実行するだけでは検証が成立しない場合が
あることを示す実例であり、E2E実装全体を通じた教訓（次項）に連なるものとして記録する。

### E2E実装全体を通じた教訓

12〜14・18〜20は、いずれも「計画書のコード例（AIの出力）を実行する前に、まず実ページ・
実DBで実測する」という、実装計画自身が定めていた方針（タスク5「購入フォームの入力欄名を
実ページから抽出する」等）に沿って実装した結果、コードに混入する前に発見できた誤りである。
一方15は、その方針があっても実装時の一度の実行で初めて顕在化し、実際に発生させてから
気づいて修正した不具合である。両者を区別して記録することで、「実測プロセスを踏んでも
なお発生しうる誤り」が存在することを正直に示す。

### 22. 隔離環境での冪等性検証によって発見した商品URLの投稿ID直値依存（2周目修正）

項目21が「意図的に壊して落とす」検証であるのに対し、本項目は**タスク10「全体通しの確認」
そのものをやり直す過程**で発見した別種の欠陥であり、項目14（1周目時点で発見した「トップ
ページの商品一覧に全商品が出ない」問題）とも異なる、2周目で新たに見つかった事実である。

**発見の経緯**: evaluator の REJECT（`entrypoint-and-idempotent-provisioning` 軸）を受け、
タスク10ステップ1が本来求める「`composer e2e:down` でまっさらな状態から再構築する」検証の
代替として、既存の共有検証環境（`docker-wordpress-1` 等、`docs/verification.md` 記録済みの
再現不能な受注データを保持している）を一切破壊せずに済む**隔離 Docker Compose 環境**
（`COMPOSE_PROJECT_NAME=wcd-e2e-freshcheck WP_PORT=8099`）を新規構築した。この隔離環境に
対してまっさらな状態から `e2e/bin/env-up.sh` を実行し、環境構築ロジック自体は健全である
ことをDocker環境で実際に確認できた。続けて `WP_PORT=8099 npx playwright test` を実行したところ、
管理画面 UI のみで完結する `04-admin-settings.spec.ts`（2件）は PASS した一方、商品を
カートに入れる `01-cart-display.spec.ts` / `02-checkout-consistency.spec.ts` /
`03-category-exclusion.spec.ts`（合計9件）はいずれも `addToCart()` の1回目の呼び出しで
失敗した。

**原因**: `e2e/helpers/shop.ts` の `ITEMS` が商品ページ URL を `/?p=181` のような
**投稿IDの直値**で保持しており、この値は共有環境（`docker-wordpress-1`）で実測した値に
過ぎなかった。隔離環境で同じ商品名の実際の投稿IDを確認すると、Practice Pad Set=78 /
Compact Tuner Pedal=40 / OD-1 Overdrive=36 / Maple Snare 14inch=46 であり、共有環境の値
（181 / 171 / 167 / 177）とはまったく一致しなかった。WordPress の投稿IDはインストール
ごとの投稿作成順・件数に依存する自動採番であり、真にまっさらな環境では共有環境と同じ値に
なる保証がない。

**なぜAIの誤りと言えるか**: 実装計画・過去の実装（項目14で商品ページURLを投稿ID直値で
`ITEMS` に持たせる設計にした時点を含む）は、いずれも「今稼働中の共有環境で動けばよい」
という暗黙の前提でこの値をハードコードしており、設計書・`docs/design-notes.md` が明記する
「まっさらな状態からでも1コマンドで通る」という中核目標を、実際には満たしていなかった。
この矛盾はコードレビューだけでは気づけず、隔離環境での実際の操作による検証によって初めて発見できた。

**修正内容**: `e2e/helpers/wpcli.ts` に `getItemPostId(name)` / `getItemUrl(name)` を
追加し、商品名から投稿IDを動的に解決する方式に変更した。`wp post list --title=...
--post_mime_type=item` は項目12と同じ理由（Welcart側の `pre_get_posts` フックが介在する
とみられ、`WP_Query` 経由のフィルタとして機能しない。原因の完全な特定までは行っていない）
で使えないため、`getCategoryId()`
（`wp term list`）と同じパターンは踏襲できず、`$wpdb->prepare()` を使う `wp eval` 経由で
`{$wpdb->posts}` を直接問い合わせる方式にした。`e2e/helpers/shop.ts` の `ITEMS` からは
`url`（投稿ID直値）フィールドを削除し、`addToCart()` 内で商品名ごとに `itemUrlCache`
（プロセス内 `Map`）にキャッシュしつつ都度 `getItemUrl()` で解決する設計に変更した
（呼び出しのたびに WP-CLI を叩く速度低下を避けるため）。

**修正後の確認結果**: 共有検証環境（`docker-wordpress-1`）に対して `cd e2e && npx
playwright test` を実行し、既存4 spec・11件が全件 PASS することを確認した（動的解決に
変えても共有環境の商品名・投稿IDの対応関係自体は変わらないため、以前と同じ結果になる）。
隔離環境は検証終了後 `docker compose -p wcd-e2e-freshcheck -f docker/docker-compose.yml
down -v` で完全に破棄し、共有環境の受注件数・コンテナ作成時刻に影響がないことも確認した。
詳細な実測値・コマンド出力は `docs/design-notes.md`（460〜538行付近）に記録している。

## うまくいかなかったこと・時間を要したこと（E2E、追記）

### 商品在庫の枯渇による他specへの副作用（設計上の盲点）

`02-checkout-consistency.spec.ts` は3箇所整合を検証するために実際に購入を完走させる。
そのため実行のたびに対象商品（Practice Pad Set・Compact Tuner Pedal）の在庫
（`wp_usces_skus.stocknum`）を1個ずつ消費する。`stocknum` が0になると `stock` フラグが
売り切れ扱いになり、カート追加ボタンはDOM上に存在し続けるが押しても何も起きなくなる
（エラーメッセージも出ない）。これにより、`01-cart-display.spec.ts` と
`02-checkout-consistency.spec.ts` を続けて何度も実行すると、原因の見えないflakyな
失敗が発生した。

**原因調査に時間を要した理由**: エラーが一切出ないため、最初はセレクタの問題か
ネットワークタイミングの問題を疑い、`waitForLoadState` の待ち方を見直すなど的外れな
修正を試みた。最終的にDBで `wp_usces_skus.stocknum` を直接確認し、対象商品の在庫が
0になっていることに気づいて原因が判明した。

**未解決である理由**: `env-reset.sh` は `wcd_settings` / `wcd_exclusions` の2option
のみをリセットする設計であり（`docs/design-notes.md`「`env-reset.sh` が受注を削除
しない理由」参照）、在庫を毎回補充する処理は意図的に含めていない。在庫補充を
`env-reset.sh` に追加することも検討したが、「テストが作成した受注を触らない」という
既存の設計方針（受注データを直接いじる操作は事故のリスクが高い）と一貫させるため、
今回は在庫を戻す処理を実装せず、この限界を正直に記録するに留めた。回避策として
README・design-notes.md に明記した通り、在庫切れが疑われる場合は `composer e2e:down`
で検証環境を作り直すことを運用上の対処とする。
