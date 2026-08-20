# Welcart カート合計金額連動 自動割引プラグイン 設計書

**作成日:** 2026-08-18
**ゴール:** Welcart のカート合計金額に応じて自動割引を適用する独立 WordPress プラグインを、カート画面・購入確認画面・受注データの3箇所で整合させて実装する。

---

## 背景・目的

株式会社Welcart の採用選考における実技課題の成果物。提出期限は 2026-08-28。

評価は「完成度の高さよりも判断の過程」を重視するとされている。したがって本設計書および
コミット履歴・issue は成果物の一部であり、判断の根拠を明示的に残す。

課題は「当社の受託カスタマイズ業務の縮図」と説明されている。受託カスタマイズの本質的な
難しさは初回実装ではなく、後から要件が追加されたときに既存動作を壊さないことにある。
本設計はその点を最重要視する。

### 検証・提出環境

- ソース管理は GitLab。Dokploy 環境へデプロイし、本番想定で最終確認を行う
- wordpress.org のプラグインディレクトリへの公開申請は行わない（課題要件外）
- WordPress 本体・Welcart 本体はリポジトリに含めず、Docker イメージおよび
  wordpress.org から取得する

### 調査済みの前提（Welcart 2.12.1 実ソースで確認）

- Welcart 2.12.1 / Requires WordPress 5.6+ / Requires PHP 7.4+
- 割引額は**負の値**で表現される（`$discount = $discount * -1`）
- `$usces->get_order_discount()` が割引額の唯一の入口であり、
  末尾で `apply_filters( 'usces_order_discount', $discount, $cart )` を実行する
- 上記の戻り値が `set_cart_fees()` で `$entries['order']['discount']` に格納され、
  確認画面表示・税計算・支払総額・DB の `order_discount` カラムまで一本で流れる
- カート画面の標準テンプレートには割引行が存在しない（商品合計のみ）

---

## 開発の段階分け

### 第一段階（本設計書の対象）

必須要件6つに加え、テストコードと第二段階のための拡張点を含む。
完了条件は「必須要件を満たし、3箇所整合を実機検証し、README・設計メモを含めて
提出可能な状態」とする。この時点でリポジトリは単体で完成品となる。

### 第二段階（issue ベースで管理・本設計書の対象外）

加点要件のうち、会員ランク除外・商品カテゴリ除外・i18n の実装。
第一段階で用意した拡張点に実装を足すのみとし、既存コードとテストは変更しない。
その diff の小ささ自体を変更容易性の証拠として提出する。

なお i18n は第一段階からテキストドメインを全文字列に通しておくため、
第二段階の作業は翻訳ファイル整備が中心となる。

---

## 確定した仕様判断

| 論点 | 決定 | 根拠 |
|---|---|---|
| 複数段の適用方式 | 到達した最上位の1段のみ適用 | 課題文の例示（35,000円→2,000円引き）と整合。店舗が最終割引額を把握しやすい。累積方式は設定ミスを誘発する |
| しきい値の判定基準額 | 商品合計 `$usces->get_total_price( $cart )` | Welcart 標準のキャンペーン割引が同じ値を基準にしている（`usceshop.class.php:8294`）。送料はカート画面時点で未確定のため、含めると3箇所整合が破綻する |
| しきい値の境界 | 「以上」（`>=`） | 課題文が「10,000円以上」と明記。実装とテストの両方で固定する |
| 割引額の型 | 整数（円） | 日本円運用を前提とする。多通貨の小数対応は行わず README に明記 |
| 既存割引との関係 | 上書きせず加算 | 上書きすると Welcart 標準キャンペーン割引や他プラグインの割引が消える |
| 設定画面の位置 | Welcart Shop メニュー配下 | 店舗運営者の導線に一致。権限も Welcart 体系に乗る |
| 設定保存の実装 | Settings API を使わず自前処理 | 動的増減する行と Settings API のフィールド登録モデルが噛み合わない。また必須要件5の明示的実装を示すため |

---

## アーキテクチャ

### 命名規約

Welcart 本体の接頭辞 `usces_` と衝突させないため、独自接頭辞 `wcd`（Welcart Cart
Discount）を使用する。PHP 7.4 以上のため名前空間も選択可能だが、WordPress プラグインの
慣習とレビューのしやすさを優先し、プレフィックス付きクラス名で統一する
（WPCS の `PrefixAllGlobals` を満たす）。

| 対象 | 規約 | 例 |
|---|---|---|
| 関数 | `wcd_` | `wcd_get_settings()` |
| クラス | `WCD_` | `WCD_Calculator` |
| オプションキー | `wcd_` | `wcd_settings` |
| 独自フック | `wcd_` | `wcd_eligible_subtotal` |
| テキストドメイン | `welcart-cart-discount` | `__( 'text', 'welcart-cart-discount' )` |
| 定数 | `WCD_` | `WCD_VERSION` |

### ファイル構成

```
welcart-cart-discount.php     プラグインヘッダ、定数、ブートストラップ
includes/
  class-wcd-plugin.php        フック登録の一元管理（唯一の add_filter 集約点）
  class-wcd-settings.php      設定の読み書き・正規化・既定値
  class-wcd-rule.php          割引ルール1段を表す値オブジェクト
  class-wcd-calculator.php    割引計算コア（WordPress 非依存・純粋）
  class-wcd-integration.php   Welcart フックへの接続（薄いアダプタ）
  class-wcd-admin.php         設定画面の描画と保存処理
languages/
  welcart-cart-discount.pot
tests/
  bootstrap.php
  unit/                       WordPress 不要のユニットテスト
docker/
  Dockerfile, docker-compose.yml, .env.example
docs/
  design-notes.md             設計メモ（提出物3）
  ai-report.md                AI活用レポート（提出物4）
  verification.md             動作確認の記録（提出物5）
README.md                     提出物2
composer.json                 phpunit / phpcs の dev 依存
.gitlab-ci.yml                lint + test
```

プラグイン本体をリポジトリ直下に置く構成とした。評価者が見るのはプラグインのコードであり、
zip 化もディレクトリをそのまま固めるだけで済む。WordPress 本体を同梱する構成は、
コミット履歴が埋もれるため採用しない。

### 依存の方向

```
Integration ──> Calculator <── Settings
  (Welcart)      (純粋)        (WP option)
```

`class-wcd-calculator.php` と `class-wcd-rule.php` は WordPress 関数も Welcart も
一切参照しない。これが PHPUnit を WordPress 起動なしで実行できる根拠であり、
第二段階でも変更しない層である。

Welcart への依存は `class-wcd-integration.php` の1ファイルに閉じ込める。
Welcart 側の仕様変更で影響を受ける範囲がこのファイルに限定される。

---

## コンポーネント構成

### WCD_Rule（値オブジェクト）

割引ルール1段を表す不変オブジェクト。`threshold`（int）と `amount`（int）を保持し、
生成時に負値・0・非数値を拒否する。

### WCD_Calculator（割引計算コア）

```php
WCD_Calculator::calculate( float $subtotal, array $rules ): float
```

戻り値は**正の割引額**。Welcart の「割引は負値」という規約はアダプタ層で変換する。
コアは「10,000円のとき500円引き」という自然な意味を保ち、Welcart の表現規約に
汚染されない。テストも直感的に記述できる。

計算手順:

1. `$rules` をしきい値の降順に並べる
2. `$subtotal >= threshold` を満たす最初のルールを採用する
3. 割引額を `min( $amount, $subtotal )` でクランプして返す

3 のクランプは、しきい値より大きい割引額を誤設定した場合に合計が負になるのを防ぐ。
Welcart 側にも `if ( $total_price < 0 ) $total_price = 0;` のガードはあるが、
そこに頼ると割引行の表示だけが不整合になるため、コア側で止める。

1 の降順ソートは `WCD_Settings::normalize()` による保存時の正規化と重複するが、
Calculator 単体の正当性を入力順に依存させないため、防御的に実施する。

### WCD_Settings（設定の読み書きと正規化）

オプションキー `wcd_settings` に割引ルールの配列を保存する。

```php
WCD_Settings::normalize( array $raw ): array   // 静的・純粋
```

正規化の内容:

- 各値を `absint()` で整数化する
- しきい値または割引額が0以下の行を破棄する
- しきい値が重複する行は後勝ちで排除する
- しきい値の昇順にソートする

保存時点で正規化することで読み出し側が単純になる。この関数は WordPress 関数のうち
`absint()` のみに依存するため、テスト用のシンプルな代替実装を bootstrap で用意して
ユニットテスト対象とする。

### WCD_Integration（Welcart アダプタ）

Welcart のフックに接続する薄い層。負値変換・独自フィルタの適用・カート画面への
HTML 挿入を担当する。

### WCD_Admin（管理画面）

設定画面の描画と保存処理。

### WCD_Plugin（フック登録の一元管理）

`add_filter` / `add_action` の登録をこのクラスに集約する。どのフックを使っているかが
1ファイルを読めば分かる状態にし、設計メモのフック一覧と対応させる。

---

## データフロー

### 割引適用の流れ

```
Welcart: $usces->get_order_discount()
   │
   ├─ apply_filters( 'usces_order_discount', $discount, $cart )
   │        │
   │        └─> WCD_Integration::filter_order_discount()
   │                 │
   │                 ├─ $subtotal = $usces->get_total_price( $cart )
   │                 ├─ $subtotal = apply_filters( 'wcd_eligible_subtotal', $subtotal, $cart )
   │                 ├─ $rules    = apply_filters( 'wcd_available_rules', WCD_Settings::get_rules(), $cart )
   │                 ├─ $amount   = WCD_Calculator::calculate( $subtotal, $rules )
   │                 └─ return $discount - $amount      // 負値規約へ変換・既存割引に加算
   │
   └─> set_cart_fees() ─> $entries['order']['discount']
            │
            ├─> 購入確認画面の割引行（templates/cart/confirm.php:61）… 自動整合
            ├─> total_price / tax / total_full_price       … 自動整合
            └─> DB: wp_usces_order.order_discount           … 自動整合
```

**3箇所整合の要点**は、割引額の注入が `usces_order_discount` 1本で完結することにある。
購入確認画面と受注データは追加実装なしで自動的に整合する。カート画面のみ表示の追加が
必要となる。

軽減税率が有効な場合は `classes/tax.class.php:368` 側の同名フィルタが呼ばれるが、
フィルタ名が同一のため `add_filter` 1回で両経路をカバーできる。

### カート画面の表示

```
templates/cart/cart.php
   └─ apply_filters( 'usces_filter_cart_table_footer', $cart_table_footer )
            └─> WCD_Integration::filter_cart_table_footer()
                     └─ </tfoot> の直前に割引行と割引後合計行を挿入
```

### 受注編集時の再計算

```
functions/item_post.php:2805, :3023
   └─ apply_filters( 'usces_filter_order_discount_recalculation', $discount, $cart, $condition, $order_id )
            └─> WCD_Integration::filter_order_recalculation()
```

管理画面で受注内容を編集して再計算した際に、割引だけが消える不整合を防ぐ。
計算ロジックは `filter_order_discount()` と共通の内部メソッドを使う。

---

## 使用するフック一覧

### Welcart のフック（すべて実ソースで存在を確認済み）

| フック | 種別 | 用途 | 出典 |
|---|---|---|---|
| `usces_order_discount` | filter | 割引額の注入（全経路の起点） | `classes/usceshop.class.php:8318`, `classes/tax.class.php:368` |
| `usces_filter_cart_table_footer` | filter | カート画面への割引行挿入 | `templates/cart/cart.php:66` |
| `usces_confirm_discount_label` | filter | 割引ラベルの文言変更 | `templates/cart/confirm.php:65` ほか計11箇所 |
| `usces_filter_order_discount_recalculation` | filter | 受注編集時の再計算 | `functions/item_post.php:2805`, `:3023` |

### WordPress のフック

| フック | 種別 | 用途 |
|---|---|---|
| `plugins_loaded` | action | Welcart 有効性の確認とフック登録 |
| `admin_menu` | action | 設定画面のサブメニュー登録 |
| `admin_post_wcd_save_settings` | action | 設定の保存処理 |
| `admin_notices` | action | Welcart 不在時の通知 |
| `init` | action | テキストドメインの読み込み |

### 本プラグインが公開する独自フック（第二段階の拡張点）

| フック | 第一段階の挙動 | 第二段階での用途 |
|---|---|---|
| `wcd_eligible_subtotal` | カート合計をそのまま返す | 商品カテゴリ除外（対象外商品を差し引く） |
| `wcd_available_rules` | 設定値をそのまま返す | 会員ランク除外（対象外ランクなら空配列を返す） |

Calculator を純粋に保つため、独自フィルタの適用は Integration 層で行い、
Calculator 自身は `apply_filters()` を呼ばない。

第二段階はこの2つに `add_filter` するだけで完結し、Calculator・Rule・Integration の
既存コードは1行も変更しない。既存テストも書き換わらない。副産物として、
第三者による拡張も可能になる。

---

## 管理画面

`Welcart Shop` メニュー（親スラッグ `USCES_PLUGIN_BASENAME`）配下にサブメニュー
（スラッグ `wcd_settings`）を追加する。画面は割引ルールの表で、1行が「しきい値金額」と
「割引額」の組。行の追加・削除は素の JavaScript で行い、
`wcd_rules[0][threshold]` 形式の配列として POST する。

保存は `admin_post_wcd_save_settings` で受ける。

---

## セキュリティ

保存処理の冒頭で以下を順に実行する（必須要件5）。

```php
check_admin_referer( 'wcd_save_settings', 'wcd_nonce' );          // nonce 検証
if ( ! current_user_can( wcd_get_capability() ) ) {                // 権限チェック
    wp_die( esc_html__( '...', 'welcart-cart-discount' ) );
}
$rules = WCD_Settings::normalize( $_POST['wcd_rules'] ?? array() ); // サニタイズ
```

`wcd_get_capability()` は Welcart の `wel_manage_setting` を返し、その権限が存在しない
環境では `manage_options` にフォールバックする。メニュー登録時と保存時の両方で同じ関数を
使い、表示できるが保存できない（またはその逆）というズレを防ぐ。

出力時は `esc_attr()` / `esc_html()` を徹底する。カート画面に挿入する HTML も同様。

---

## エラー処理

**フロント側で致命的エラーを出さず、購入導線を止めないことを最優先とする。**
EC サイトで購入できなくなる不具合が最も損害が大きいため。

| 状況 | 挙動 |
|---|---|
| Welcart が無効／未インストール | `plugins_loaded` で `class_exists( 'usc_e_shop' )` を確認し、不在なら管理画面通知を出して全フック登録を見送る |
| 設定が未登録・空 | 割引0を返し、フィルタの入力値をそのまま通過させる |
| カート表の文字列置換に失敗 | 元の文字列をそのまま返す（テーマによるテンプレート上書きへの防御） |
| 割引額が小計を超過 | `min( $amount, $subtotal )` でクランプ |

---

## テスト方針

Composer で PHPUnit 9.x（PHP 7.4 対応版）を dev 依存に入れ、WordPress を起動せずに
実行する。対象は `WCD_Calculator` / `WCD_Rule` / `WCD_Settings::normalize()` の3つ。

| 観点 | 入力 | 期待 |
|---|---|---|
| しきい値未満 | 9,999円 | 0 |
| 境界値ちょうど | 10,000円 | 500（`>=` であることの検証） |
| 最上位1段のみ | 35,000円 | 2,000（2,500 ではない） |
| ルール未設定 | 空配列 | 0 |
| 割引額がしきい値超過 | 小計10,000／割引50,000 | 10,000 にクランプ |
| ルールが降順未整列 | 順不同の配列 | 正しい段を選択 |
| 不正入力の除去 | 負値・空文字・非数値 | 該当行を破棄 |
| しきい値重複 | 同一しきい値の2行 | 後勝ちで1行に集約 |

境界値（しきい値ちょうど）は「以上」か「超過」かの解釈が割れる典型であり、
実装とテストの両方で `>=` を固定する。

### コーディング規約

`squizlabs/php_codesniffer` + `wp-coding-standards/wpcs` を dev 依存に入れ、
`composer lint` で実行する。**提出時に違反ゼロの状態にする**（必須要件6）。

### CI

GitLab CI で `composer lint` と `composer test` を実行する。追加コストは小さく、
エージェント駆動開発でもテストと規約チェックが自動で担保されていることを示せる。

---

## 検証環境

`docker/` に WordPress + MariaDB の Docker Compose を配置する。

- イメージは `wordpress:6.x-php8.2-apache` を第一候補とし、Welcart 2.12.1 の動作を
  実際に確認する。動作しなければ PHP のマイナーバージョンを下げ、
  **実測できた組み合わせを README に記載する**
- WordPress 本体・Welcart 本体はリポジトリに含めず、コンテナ側で取得する
- 本プラグインは `wp-content/plugins/welcart-cart-discount` にバインドマウントし、
  ソース編集が即反映される形にする
- 同構成のまま Dokploy にデプロイし、本番想定の環境で最終確認を行う

README には「動くはず」ではなく「この構成で動作を確認した」と記載する。

### 動作確認の記録（提出物5）

以下をスクリーンショットで記録し `docs/verification.md` にまとめる。

1. 管理画面での複数段ルール設定
2. しきい値未満のカート画面（割引なし）
3. しきい値到達後のカート画面（割引行の表示）
4. 購入確認画面の割引行と支払総額
5. 受注データ一覧・受注詳細の割引額
6. 上位段に到達したときの割引額の切り替わり
7. `composer lint` と `composer test` の実行結果

---

## 制約・禁止事項

- Welcart 本体およびテーマのファイルを改変しない（必須要件1）
- Welcart の既存フック／フィルタのみで実現する（必須要件4）。
  フックの実在は必ず Welcart のソースを読んで確認し、推測でフック名を使わない
- 接頭辞 `usces_` を本プラグインの関数・フック・オプション名に使わない
- 第一段階では加点要件（会員ランク除外・商品カテゴリ除外）の実装を行わない。
  拡張点の用意までに留める
- 第二段階で第一段階のテストコードを書き換えない。書き換えが必要になった場合は、
  拡張点の設計が誤っていたことを意味するため、その事実を設計メモに記録する
