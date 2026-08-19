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

### CI（`.gitlab-ci.yml`）

GitLab Runner 上で以下の3ジョブを実行している。

| ジョブ | PHP | 内容 |
| --- | --- | --- |
| `lint` | 8.2 | `composer lint`（PHP_CodeSniffer / WPCS） |
| `test` | 8.2 | `composer test`（PHPUnit） |
| `syntax:php7.4` | 7.4 | 全 PHP ファイルの `php -l` |

`lint` / `test` を 8.2 で回しているのは、実際に動作確認した PHP バージョン
（上表参照）に合わせるため。当初 `php:7.4-cli` を指定していたが、
`composer.lock` は PHP 8 系で生成されており開発依存（`doctrine/instantiator` 2.0 等）が
PHP 8.1 以上を要求するため `composer install` が解決できずに失敗した。
これは **GitLab に push して実際にパイプラインを回して初めて判明した**もので、
ローカル実行だけでは気づけなかった。

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

Docker があれば、次の2コマンドで WordPress・Welcart・本プラグインが
有効化された状態の検証環境が立ち上がる。

```bash
docker compose -f docker/docker-compose.yml up -d --build
./docker/setup.sh
```

完了すると以下が使える状態になる（初回のビルドに数分かかる）。

| | |
| --- | --- |
| サイト | http://localhost:8080 |
| 管理画面 | http://localhost:8080/wp-admin/（`admin` / `admin`） |
| 割引設定 | http://localhost:8080/wp-admin/admin.php?page=wcd_settings |

8080 番が使用中の場合は `WP_PORT` で変更できる。

```bash
WP_PORT=8090 docker compose -f docker/docker-compose.yml up -d --build
WP_PORT=8090 ./docker/setup.sh
```

商品は登録されていないため、動作確認には管理画面の
「Welcart Shop > 新規商品追加」から商品を1点以上追加する必要がある。

### 2コマンドが何をしているか

- **`docker/Dockerfile`** … 公式 `wordpress:6.6-php8.2-apache` イメージに、
  wordpress.org から取得した Welcart（`usc-e-shop` **2.12.1** 固定）を同梱する。
  配置先を `/var/www/html` ではなく `/usr/src/wordpress/wp-content/plugins/` に
  しているのは、公式イメージの `docker-entrypoint.sh` が初回起動時に
  `/usr/src/wordpress` を `/var/www/html` へ展開する作りのため
  （`/var/www/html` は名前付きボリュームでマスクされるので残らない）。
  entrypoint はプラグイン・テーマを「差し替え可能な内容」として扱い、
  展開先に既に存在する場合はスキップするので、ボリュームを残したまま
  再起動しても既存の Welcart を上書きしない。
- **`docker/setup.sh`** … WP-CLI で `wp core install`、日本語言語パックの導入、
  `usc-e-shop` と `welcart-cart-discount` の有効化、Welcart の日本向け設定
  （通貨・表示言語・住所形式・対象国・配送方法）を行う。**冪等**で、
  何度実行しても同じ結果になる。

本プラグイン自体は `docker-compose.yml` の bind mount により
`wp-content/plugins/welcart-cart-discount` としてマウントされる
（リポジトリ直下がそのままプラグインディレクトリになる）。

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

## ドキュメント

- 設計メモ（フック選定理由・検討したが不採用の候補）: `docs/design-notes.md`
- AI活用レポート（使用ツール・誤りの発見と修正・うまくいかなかったこと）: `docs/ai-report.md`
- 動作確認の記録（スクリーンショット）: `docs/verification.md`
