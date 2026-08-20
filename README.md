# Welcart Cart Discount

Welcart のカート合計金額に応じて自動割引を適用する独立プラグイン。
管理画面から「しきい値金額」と「割引額」の組を複数段設定でき、到達した最上位の
1段のみがカート画面・購入確認画面・受注データの3箇所で整合して適用される。

株式会社Welcart 採用選考の実技課題として作成した。設計の経緯は
`docs/design-notes.md`、AI活用の詳細は `docs/ai-report.md`、動作確認の記録は
`docs/verification.md` を参照。

## 動作確認環境

以下の構成で、カート画面・購入確認画面・受注データの割引額整合、および
受注編集時の再計算を **実機で操作して動作を確認した**（`docs/verification.md`
参照）。「動くはず」ではなく、この組み合わせで実際に動作したことを記載する。

| 項目 | バージョン | 確認方法 |
|---|---|---|
| WordPress | 6.6.2 | Docker イメージ `wordpress:6.6-php8.2-apache`。管理画面フッターの表示で確認 |
| PHP | 8.2.25 | 上記 Docker イメージの Apache 起動ログ（`PHP/8.2.25`）で確認 |
| Welcart（usc-e-shop） | 2.12.1.2608181 | wordpress.org から取得した `usc-e-shop.2.12.1.zip`。プラグイン一覧・各管理画面のフッターで確認 |
| MariaDB | 10.11 | Docker イメージ `mariadb:10.11` |

上記構成での検証内容（`docs/verification.md` に詳細）:

- 管理画面での複数段ルール設定と保存
- しきい値未満／到達時／上位段切り替え時のカート画面の割引表示
- 購入確認画面の割引額とカート画面の整合
- 受注データ（一覧・詳細・DB の `wp_usces_order.order_discount`）との整合
- 受注編集画面での数量変更・再計算後も正しい段の割引が再適用されること

**未確認の項目**: PHP 7.4／8.0／8.1 系や WordPress 6.6 以外のバージョンでの動作、
本番相当環境（Dokploy）へのデプロイ後の動作は未確認。設計書では Dokploy への
デプロイ検証も検討していたが、時間の制約により今回は Docker Compose 環境での
検証のみで完了とした。

Composer の開発依存（PHPUnit 9.6.36 / PHP_CodeSniffer 3.13.6 相当、
`composer.json` の `^` 指定範囲内）はローカル環境（PHP 8.2.29 / Composer 2.10.2）
でインストールし、`composer lint` ・`composer test` を実行して確認した
（`docs/screenshots/10-composer-lint.txt` ／ `docs/screenshots/11-composer-test.txt`）。

### CI（GitHub Actions）

`.github/workflows/ci.yml` で以下の4ジョブを実行している（`main` への push と
プルリクエストで起動）。

| ジョブ | PHP | 内容 |
| --- | --- | --- |
| `lint (WPCS / plugin)` | 8.2 | `composer lint` — プラグイン本体の WPCS 準拠 |
| `lint (WPCS / verification env)` | 8.2 | `composer lint:env` — `docker/` 配下（検証環境）の WPCS 準拠 |
| `test (PHPUnit)` | 8.2 | `composer test` |
| `syntax (php -l on 7.4)` | 7.4 | 全 PHP ファイルの構文チェック |

`lint` / `test` を 8.2 で回しているのは、実際に動作確認した PHP バージョン
（上表参照）に合わせるため。

**当初 CI を PHP 7.4 で回そうとして失敗した経緯**: 最初は GitLab CI
（`.gitlab-ci.yml`）で `php:7.4-cli` を指定していたが、`composer.lock` は
PHP 8 系で生成されており開発依存（`doctrine/instantiator` 2.0、
`myclabs/deep-copy` 1.14 等）が PHP 8.1 以上を要求するため `composer install` が
依存を解決できずに失敗した。**リモートに push して実際に CI を回して初めて
判明した**もので、ローカルで `composer lint` / `composer test` が通っていても
気づけない類の問題だった。この経緯は `docs/ai-report.md` にも記録している。

`composer.json` の `require` は `"php": ">=7.4"` としている。この下限については
開発依存を必要としない構文チェック（`php -l`）を 7.4 で実行して裏付けている。
**7.4 で PHPUnit のテストが全て通ることまでは確認していない**（`composer.lock` が
8 系前提のため）。下限の根拠は構文互換の範囲に留まる。

## 必須要件チェックリスト

- [x] 独立したプラグインとして実装（Welcart 本体・テーマは無改変）
- [x] 管理画面からしきい値金額と割引額を複数段設定可能（`Welcart Shop > 自動割引設定`）
- [x] カート画面・購入確認画面・受注データの3箇所で割引が整合（実機確認済み。
      受注編集時の再計算も新規受注では整合を確認・不具合修正済み。ただし
      **メタ未記録の既存受注には既知の限界が残る**。詳細は下記「制限事項」参照）
- [x] Welcart の既存フック／フィルタのみで実現（一覧は `docs/design-notes.md`）
- [x] 設定保存時の nonce 検証・入力サニタイズ・権限チェックを実装
- [x] WordPress コーディングスタンダード準拠（`composer lint` 違反0件）

## 加点要件チェックリスト

- [x] 特定の会員ランクや商品カテゴリを除外する条件設定
      （`Welcart Shop > 自動割引設定` の「除外条件」セクション。設計は
      `docs/design-notes.md`「第二段階: 除外条件設定」を参照）

## 制限事項

### しきい値・割引額は整数のみ対応（小数単位のある通貨は非対応）

管理画面で設定するしきい値・割引額は整数として扱う。そのため、補助単位を
小数で扱う通貨（例: USD の cent）で「$5.50 引き」のような設定はできない。

なお、カート表に表示する金額の**通貨記号と桁区切りは Welcart 側の通貨設定に
追従する**（Welcart 本体が金額表示に使う `usces_crform()` に委譲しているため）。
当初は円記号を直書きしていたため、Welcart の通貨設定（既定値は USD）が
何であっても円記号で表示され、カート表の他の金額と記号が食い違っていた。

### 受注再計算の既知の限界（メタ未記録の既存受注）

本プラグイン導入・修正より前に作成された受注（受注登録時に本プラグインが
注入した割引額を記録する `wcd_injected_discount` メタが存在しない受注）に対しては、
修正後最初の1回の「Recalculation」に限り誤った割引額が表示される
（実機確認値: 正しくは -¥500 のところ **-¥1,000**、さらにそこから数量変更を
重ねて再計算すると **-¥2,500**）。この誤りは1回では収まらず、以降の再計算でも
そのずれを引きずり続ける。本プラグイン適用後に新規で確定した受注（メタが
自動記録される受注）ではこの問題は発生しない。

**回避手段**: 管理画面の受注編集画面で「Campaign discount」欄の値を手動で
正しい割引額に書き換えてから「change decision」（保存）を行う。これにより
記録済みメタと表示中の値の矛盾が解消し、以降の再計算が正しく動作することを
実機で確認済み。

発生条件の解析・実機確認の手順の詳細は、`docs/design-notes.md` の
「受注再計算の既知の限界（2周目修正で判明・未解決）」節
（[直接リンク](docs/design-notes.md#受注再計算の既知の限界2周目修正で判明未解決)）を参照。

## インストール

1. `welcart-cart-discount` ディレクトリを `wp-content/plugins/` に配置する
2. WordPress 管理画面のプラグイン一覧から「Welcart Cart Discount」を有効化する
   （Welcart Shop（`usc-e-shop`）が有効化されていない場合は管理画面に通知が表示され、
   フック登録は行われない）
3. `Welcart Shop > 自動割引設定` からしきい値と割引額を設定する

## ローカル動作確認手順

Docker があれば、次の2コマンドで**動作確認できる状態のECサイトごと**立ち上がる。
WordPress・Welcart・本プラグイン・動作確認用テーマ・商品25点・割引ルール2段が
すべて設定済みの状態になる。

```bash
docker compose -f docker/docker-compose.yml up -d --build
./docker/setup.sh
```

| | |
| --- | --- |
| サイト | http://localhost:8080 |
| 管理画面 | http://localhost:8080/wp-admin/（`admin` / `admin`） |
| 割引設定 | http://localhost:8080/wp-admin/admin.php?page=wcd_settings |

初期の割引ルールは課題文の例（10,000円以上で500円引き／30,000円以上で2,000円引き）を
入れてある。サイトの商品をカートに入れるとしきい値到達で自動割引が適用される。

8080 番が使用中の場合は `WP_PORT` で変更できる。

```bash
WP_PORT=8090 docker compose -f docker/docker-compose.yml up -d --build
WP_PORT=8090 ./docker/setup.sh
```

初回はイメージのビルドに数分かかる。

### 2コマンドが何をしているか

- **`docker/Dockerfile`** … 公式 `wordpress:6.6-php8.2-apache` イメージに、
  wordpress.org から取得した Welcart（`usc-e-shop` **2.12.1** 固定）と、
  動作確認用テーマ（`docker/theme/welcart-shop-theme`）を同梱する。
  配置先を `/var/www/html` ではなく `/usr/src/wordpress/wp-content/` 配下に
  しているのは、公式イメージの `docker-entrypoint.sh` が初回起動時に
  `/usr/src/wordpress` を `/var/www/html` へ展開する作りのため
  （`/var/www/html` は名前付きボリュームでマスクされるので残らない）。
  entrypoint はプラグイン・テーマを「差し替え可能な内容」として扱い、
  展開先に既に存在する場合はスキップするので、ボリュームを残したまま
  再起動しても既存の Welcart / テーマを上書きしない。
- **`docker/setup.sh`** … WP-CLI で以下を行う。**冪等**で、何度実行しても
  同じ結果になる。
  - `wp core install` と日本語言語パックの導入
  - `usc-e-shop` / `welcart-cart-discount` の有効化、テーマの有効化
  - Welcart の日本向け設定（通貨・表示言語・住所形式・対象国・配送方法）
  - `docker/seed-items.php` による動作確認用の商品25点の投入
  - 割引ルールの初期値の設定（未設定の場合のみ）

本プラグイン自体は `docker-compose.yml` の bind mount により
`wp-content/plugins/welcart-cart-discount` としてマウントされる
（リポジトリ直下がそのままプラグインディレクトリになる）。

### 動作確認用テーマについて

`docker/theme/welcart-shop-theme` は**提出物ではなく検証環境の一部**である。
本プラグインは Welcart のカート系フィルタに接続するだけでテーマに依存しないため、
**どのテーマでも動作する**（デフォルトテーマでも割引は適用される）。
実際の購買導線に近い見た目で動作確認・スクリーンショット取得を行うために用意した。

テーマ側も Welcart 本体のファイルは一切改変していない。カート導線6画面は
`usc-e-shop/templates/cart/*.php` を `include` してロジックをそのまま流用し、
CSS のみで装飾している。

### テーマを編集しながら確認する場合

テーマはイメージに焼き込んでいるため、編集して再ビルドしても既存ボリュームには
反映されない（entrypoint が既存テーマをスキップするため）。編集を即座に
反映させたい場合は `docker/compose.dev.yml` を重ねて bind mount する。

```bash
docker compose -f docker/docker-compose.yml -f docker/compose.dev.yml up -d
```

### Welcart の既定値についての補足

Welcart の初期状態は米国向け（表示言語 英語・通貨 USD・US住所形式・国際便）に
なっている。本プラグインの動作条件ではないが、既定のままだと金額が `$` 表示になり
本 README のスクリーンショットと食い違うため、`setup.sh` で日本向けに設定している。

| 設定項目（`usces` オプション） | 既定値 | `setup.sh` 適用後 |
| --- | --- | --- |
| `['system']['currency']` | `US` | `JP` |
| `['system']['front_lang']` | `en` | `ja` |
| `['system']['addressform']` | `US` | `JP` |
| `['system']['target_market']` | `['US']` | `['JP']` |
| `['delivery_method'][n]['intl']` | `1` | `0` |

`front_lang` は WordPress のサイト言語とは独立した Welcart 独自の設定である。
`usc-e-shop/usc-e-shop.php` が `add_filter( 'locale', 'usces_filter_locale' )` で
フロント側のロケールを上書きするため、WordPress 側を日本語にしても
`get_locale()` は `en` を返し、カート画面の文言は英語のままになる。

### テストとコーディング規約チェック

```bash
composer install
composer test   # PHPUnit
composer lint   # PHP_CodeSniffer（WPCS）
```

### E2E テスト（Playwright）

カート画面・購入確認画面・受注データの3箇所整合と、受注編集画面での再計算の
べき等性を、実ブラウザ（Playwright）で自動検証できる。上記「ローカル動作確認手順」の
Docker 検証環境に対して動作するため、前提として **Docker と Node.js が必要**
（検証した Node のバージョン: v24.12.0）。

```bash
composer e2e         # 検証環境の用意（未起動なら起動・不足があれば補う）→ 既知の状態へリセット → Playwright 実行
composer e2e:up      # 検証環境の用意のみ（起動済み・準備済みなら何もしない。冪等）
composer e2e:reset   # 割引設定（wcd_settings / wcd_exclusions）だけを既知の状態に戻す
composer e2e:down    # 検証環境をボリュームごと破棄する（次回の e2e:up はまっさらから再構築）
```

4つの spec（`e2e/tests/`）・計11件がすべて PASS することを確認済み。

- `01-cart-display.spec.ts` — カート画面の割引表示（しきい値未満・1段目到達・2段目への切り替え）
- `02-checkout-consistency.spec.ts` — カート・購入確認・受注データの3箇所整合、
  受注編集画面での再計算3回のべき等性（課題の核心要件に対応）
- `03-category-exclusion.spec.ts` — 除外カテゴリによる部分除外（加点要件）
- `04-admin-settings.spec.ts` — 管理画面からの設定保存、未ログイン時のアクセス拒否

**E2Eは `docker/` の検証環境専用であり、汎用のリグレッションスイートではない。** 検証用
テーマ（`docker/theme/welcart-shop-theme`）が描画する DOM 構造・`docker/seed-items.php`
が投入する商品データ・管理者アカウント（`admin` / `admin`）に依存しており、他の環境や
テーマに対してそのまま実行できることは意図していない。CI（GitHub Actions）にも
載せていない（理由は `docs/design-notes.md` を参照）。

**既知の限界（在庫消費）**: `02-checkout-consistency.spec.ts` は実際に購入を完走するため、
実行のたびに対象商品（Practice Pad Set・Compact Tuner Pedal）の在庫を1個ずつ消費する。
`composer e2e:reset` が戻すのは `wcd_settings` / `wcd_exclusions` の2オプションのみで、
在庫数は戻さない。在庫が尽きるとカート追加ボタンがエラーを出さずに無反応となり、他の
spec がエラーメッセージのない原因不明の失敗に見えることがある。発生した場合は
`composer e2e:down` で検証環境を作り直す（商品データは `docker/setup.sh` により
再投入される）。

## ディレクトリ構成

```
welcart-cart-discount/          ← このディレクトリがそのままプラグインになる
├── welcart-cart-discount.php   プラグインのエントリポイント
├── includes/                   プラグイン本体
│   ├── class-wcd-rule.php            1段の割引ルール（しきい値・割引額）
│   ├── class-wcd-calculator.php      小計から適用段を決める計算
│   ├── class-wcd-settings.php        設定の正規化・保存・読み出し
│   ├── class-wcd-cart-row-builder.php カート表に挿す割引行の組み立て
│   ├── class-wcd-integration.php     Welcart のフックへの接続
│   ├── class-wcd-exclusion-calculator.php 除外分の純粋計算
│   ├── class-wcd-exclusion-settings.php   除外設定の正規化・保存・読み出し
│   ├── class-wcd-exclusion.php            除外条件のフックへの接続
│   ├── class-wcd-admin.php           管理画面
│   └── class-wcd-plugin.php          フック登録
├── languages/                  翻訳ファイル（i18n）
├── tests/                      PHPUnit（WordPress 非依存の単体テスト）
├── docs/                       提出用ドキュメント（設計メモ・AI活用レポート等）
├── docker/                     検証環境（提出物ではない）
│   ├── Dockerfile              Welcart とテーマを同梱したイメージ
│   ├── docker-compose.yml
│   ├── compose.dev.yml         テーマ編集用の bind mount 追加設定
│   ├── setup.sh                初期化スクリプト（冪等）
│   ├── seed-items.php          動作確認用の商品25点投入
│   └── theme/welcart-shop-theme/  動作確認用テーマ
├── e2e/                         E2Eテスト（Playwright、docker/ 検証環境専用。提出物ではあるがプラグイン本体の動作には不要）
│   ├── bin/                    環境準備（env-up.sh）・リセット（env-reset.sh）・破棄（env-down.sh）・統合入口（run.sh）
│   ├── helpers/                shop.ts（カート・購入フロー）/ admin.ts（管理画面）/ wpcli.ts（WP-CLI経由の前提データ投入）
│   ├── tests/                  4 spec（カート表示・3箇所整合・除外・管理画面設定、計11件）
│   └── playwright.config.ts
├── composer.json / phpcs.xml.dist
└── .github/workflows/          CI（GitHub Actions）
    └── ci.yml                  WPCS×2・PHPUnit・構文チェックの4ジョブ
```

`docker/` 以下は検証環境用であり、プラグインの動作には不要
（実サイトへ配置する際は `docker/` `e2e/` `tests/` `vendor/` を除外してよい）。

## ドキュメント

- 設計メモ（フック選定理由・検討したが不採用の候補）: `docs/design-notes.md`
- AI活用レポート（使用ツール・誤りの発見と修正・うまくいかなかったこと）: `docs/ai-report.md`
- 動作確認の記録（スクリーンショット）: `docs/verification.md`
