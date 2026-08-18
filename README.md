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

### `.gitlab-ci.yml` について（未実行であることの明記）

`.gitlab-ci.yml` は `lint` / `test` ステージで `composer lint` / `composer test` を
実行する設定を用意しているが、**このリポジトリは GitLab にリモートとして push しておらず
（`git remote` 未設定）、GitLab Runner 上でこのパイプラインが実際に実行された記録は無い**。
実施したのは YAML 構文の妥当性チェックのみである。

```bash
python3 -c "import yaml; yaml.safe_load(open('.gitlab-ci.yml'))"
```

`composer lint` / `composer test` そのものは、上記の通りローカル環境（および後述の
Docker コンテナ内）で実行し、結果を確認している。GitLab Runner 環境での実行確認は
「動くはず」の域を出ておらず、今回は行っていない。

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

### 割引額は整数（円）のみ対応

割引額・しきい値は整数（円）として扱う。日本円での運用を前提としており、
多通貨対応や小数（例: 通貨によっては存在する小数単位）には対応していない。

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

```bash
docker compose -f docker/docker-compose.yml up -d
composer install
composer test
composer lint
```

`docker compose` 起動後、`http://localhost:8080` にアクセスして WordPress の
初期セットアップと Welcart（`usc-e-shop`）のインストール・有効化を行う。
本プラグインは `docker-compose.yml` の bind mount により
`wp-content/plugins/welcart-cart-discount` として自動的にマウントされる。

## ドキュメント

- 設計メモ（フック選定理由・検討したが不採用の候補）: `docs/design-notes.md`
- AI活用レポート（使用ツール・誤りの発見と修正・うまくいかなかったこと）: `docs/ai-report.md`
- 動作確認の記録（スクリーンショット）: `docs/verification.md`
