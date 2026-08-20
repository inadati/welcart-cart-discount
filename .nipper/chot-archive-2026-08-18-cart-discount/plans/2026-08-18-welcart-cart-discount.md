# Welcart カート合計金額連動 自動割引プラグイン 実装計画

**作成日:** 2026-08-18
**設計書:** .nipper/chot/specs/2026-08-18-welcart-cart-discount-design.md

**ゴール:** Welcart のカート合計金額に応じて自動割引を適用する独立 WordPress プラグインを、カート画面・購入確認画面・受注データの3箇所で整合させて実装し、テスト・lint・Docker検証・提出物一式を揃える。

**アーキテクチャ:** WordPress/Welcart に非依存の純粋な計算コア（`WCD_Rule`, `WCD_Calculator`）を中心に据え、Welcart への依存を `WCD_Integration` の1ファイルに閉じ込める。設定の読み書きは `WCD_Settings`、管理画面は `WCD_Admin`、フック登録の一元管理は `WCD_Plugin` が担う。第二段階の拡張点（`wcd_eligible_subtotal` / `wcd_available_rules`）は本計画の対象内で用意するが、実装（会員ランク・商品カテゴリ除外）は行わない。

**技術スタック:** PHP 7.4+ / WordPress 5.6+ / Welcart 2.12.1 / PHPUnit 9.x / PHP_CodeSniffer + WordPress-Coding-Standards / Docker Compose（WordPress + MariaDB）/ GitLab CI

---

## 前提の確認事項

- 設計書は Welcart 2.12.1 の実ソース調査に基づく（`usceshop.class.php:8318` 等）。本計画内の Task 5 で、実装直前にもう一度該当箇所をソースから再確認する。設計書と食い違いが見つかった場合はコードではなく設計書側の記述を優先し、`docs/design-notes.md` に差分を記録する。
- リポジトリはまだコミットが1つも無い（`git status` で確認済み）。Task 1 の最初のコミットが初回コミットになる。
- 各タスク末尾のコミットは「作業の区切りごとに意図の分かるメッセージで積む」という CLAUDE.md の運用ルールに従う。

---

### タスク 1: プロジェクト雛形とツールチェーン設定

**ファイル:**
- 作成: `welcart-cart-discount.php`
- 作成: `composer.json`
- 作成: `.gitignore`
- 作成: `phpcs.xml.dist`
- 作成: `includes/.gitkeep`（後続タスクで実体ファイルに置き換わるため一時的に空ディレクトリを維持する目的。Task 2 で削除）

- [ ] **ステップ 1: ディレクトリを作成する**

実行:
```bash
mkdir -p includes tests/unit languages docker docs
```

- [ ] **ステップ 2: `.gitignore` を作成する**

```gitignore
/vendor/
/node_modules/
/.phpunit.result.cache
/docker/wp-content/
/docker/.env
*.log
.DS_Store
```

- [ ] **ステップ 3: プラグイン本体ファイルを作成する**

```php
<?php
/**
 * Plugin Name:       Welcart Cart Discount
 * Plugin URI:         https://github.com/inadati/welcart-cart-discount
 * Description:        Welcart のカート合計金額に応じて自動割引を適用する。
 * Version:            1.0.0
 * Requires at least:  5.6
 * Requires PHP:       7.4
 * Author:             Itaya Inadati
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        welcart-cart-discount
 * Domain Path:        /languages
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCD_VERSION', '1.0.0' );
define( 'WCD_PLUGIN_FILE', __FILE__ );
define( 'WCD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WCD_PLUGIN_DIR . 'includes/class-wcd-rule.php';
require_once WCD_PLUGIN_DIR . 'includes/class-wcd-calculator.php';
require_once WCD_PLUGIN_DIR . 'includes/class-wcd-settings.php';
require_once WCD_PLUGIN_DIR . 'includes/class-wcd-integration.php';
require_once WCD_PLUGIN_DIR . 'includes/class-wcd-admin.php';
require_once WCD_PLUGIN_DIR . 'includes/class-wcd-plugin.php';

add_action( 'plugins_loaded', array( 'WCD_Plugin', 'init' ) );
```

（`includes/` 配下の各ファイルは Task 2 以降で作成する。この時点では require が404にならないよう、`touch` で空ファイルを先に置く）

実行:
```bash
touch includes/class-wcd-rule.php includes/class-wcd-calculator.php includes/class-wcd-settings.php includes/class-wcd-integration.php includes/class-wcd-admin.php includes/class-wcd-plugin.php
rm -f includes/.gitkeep
```

- [ ] **ステップ 4: `composer.json` を作成する**

```json
{
    "name": "asweed/welcart-cart-discount",
    "description": "Welcart のカート合計金額に応じた自動割引プラグイン",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "squizlabs/php_codesniffer": "^3.9",
        "wp-coding-standards/wpcs": "^3.1",
        "phpcsstandards/phpcsutils": "^1.0",
        "phpcsstandards/phpcsextra": "^1.2",
        "dealerdirect/phpcodesniffer-composer-installer": "^1.0"
    },
    "config": {
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    },
    "scripts": {
        "test": "phpunit --bootstrap tests/bootstrap.php tests/unit",
        "lint": "phpcs --standard=phpcs.xml.dist"
    }
}
```

- [ ] **ステップ 5: `phpcs.xml.dist` を作成する**

```xml
<?xml version="1.0"?>
<ruleset name="WelcartCartDiscount">
	<description>WordPress Coding Standards for welcart-cart-discount</description>

	<file>.</file>
	<exclude-pattern>/vendor/*</exclude-pattern>
	<exclude-pattern>/tests/*</exclude-pattern>
	<exclude-pattern>/docker/*</exclude-pattern>

	<arg value="sp"/>
	<arg name="basepath" value="."/>
	<arg name="extensions" value="php"/>

	<config name="minimum_supported_wp_version" value="5.6"/>

	<rule ref="WordPress"/>
	<rule ref="WordPress-Extra"/>

	<rule ref="WordPress.NamingConventions.PrefixAllGlobals">
		<properties>
			<property name="prefixes" type="array">
				<element value="wcd_"/>
				<element value="WCD_"/>
			</property>
		</properties>
	</rule>
</ruleset>
```

- [ ] **ステップ 6: Composer で開発依存をインストールする**

実行: `composer install`
期待: `vendor/` 配下に phpunit・phpcs・wpcs がインストールされ、エラーなく終了する。

- [ ] **ステップ 7: コミット**

```bash
git add welcart-cart-discount.php composer.json composer.lock phpcs.xml.dist .gitignore includes/
git commit -m "chore: プロジェクト雛形とツールチェーンを追加"
```

---

### タスク 2: WCD_Rule 値オブジェクト（TDD）

**ファイル:**
- 作成: `includes/class-wcd-rule.php`
- テスト: `tests/unit/WCD_RuleTest.php`
- 作成: `tests/bootstrap.php`

- [ ] **ステップ 1: PHPUnit ブートストラップを作成する**

```php
<?php
/**
 * PHPUnit ブートストラップ。WordPress を起動せず、
 * テスト対象が依存する最小限の WordPress 関数のみを代替実装する。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! function_exists( 'absint' ) ) {
	/**
	 * WordPress の absint() の簡易代替。
	 *
	 * @param mixed $value 変換対象。
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../includes/class-wcd-rule.php';
require_once __DIR__ . '/../includes/class-wcd-calculator.php';
require_once __DIR__ . '/../includes/class-wcd-settings.php';
```

- [ ] **ステップ 2: 失敗するテストを書く**

```php
<?php
/**
 * WCD_Rule のテスト。
 *
 * @package Welcart_Cart_Discount
 */

use PHPUnit\Framework\TestCase;

class WCD_RuleTest extends TestCase {

	public function test_holds_threshold_and_amount() {
		$rule = new WCD_Rule( 10000, 500 );

		$this->assertSame( 10000, $rule->get_threshold() );
		$this->assertSame( 500, $rule->get_amount() );
	}

	public function test_rejects_zero_threshold() {
		$this->expectException( InvalidArgumentException::class );

		new WCD_Rule( 0, 500 );
	}

	public function test_rejects_negative_amount() {
		$this->expectException( InvalidArgumentException::class );

		new WCD_Rule( 10000, -500 );
	}

	public function test_rejects_non_numeric_threshold() {
		$this->expectException( InvalidArgumentException::class );

		new WCD_Rule( 'abc', 500 );
	}
}
```

- [ ] **ステップ 3: 失敗することを確認するために実行する**

実行: `vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/unit/WCD_RuleTest.php`
期待: FAIL（`class-wcd-rule.php` が空ファイルのため `WCD_Rule` が未定義でエラー）

- [ ] **ステップ 4: 最小限の実装を書く**

```php
<?php
/**
 * 割引ルール1段を表す値オブジェクト。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 割引ルール1段（しきい値と割引額の組）を表す不変オブジェクト。
 */
class WCD_Rule {

	/**
	 * しきい値金額（円）。
	 *
	 * @var int
	 */
	private $threshold;

	/**
	 * 割引額（円）。
	 *
	 * @var int
	 */
	private $amount;

	/**
	 * コンストラクタ。
	 *
	 * @param mixed $threshold しきい値金額。0より大きい整数値であること。
	 * @param mixed $amount    割引額。0より大きい整数値であること。
	 * @throws InvalidArgumentException しきい値または割引額が数値でない、または0以下の場合。
	 */
	public function __construct( $threshold, $amount ) {
		if ( ! is_numeric( $threshold ) || ! is_numeric( $amount ) ) {
			throw new InvalidArgumentException( 'threshold and amount must be numeric.' );
		}

		$threshold = (int) $threshold;
		$amount    = (int) $amount;

		if ( $threshold <= 0 || $amount <= 0 ) {
			throw new InvalidArgumentException( 'threshold and amount must be positive integers.' );
		}

		$this->threshold = $threshold;
		$this->amount    = $amount;
	}

	/**
	 * しきい値金額を返す。
	 *
	 * @return int
	 */
	public function get_threshold() {
		return $this->threshold;
	}

	/**
	 * 割引額を返す。
	 *
	 * @return int
	 */
	public function get_amount() {
		return $this->amount;
	}
}
```

- [ ] **ステップ 5: パスすることを確認するために実行する**

実行: `vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/unit/WCD_RuleTest.php`
期待: PASS（4 tests, 4 assertions）

- [ ] **ステップ 6: コミット**

```bash
git add includes/class-wcd-rule.php tests/unit/WCD_RuleTest.php tests/bootstrap.php
git commit -m "feat: WCD_Rule 値オブジェクトを追加"
```

---

### タスク 3: WCD_Calculator 割引計算コア（TDD）

**ファイル:**
- 作成: `includes/class-wcd-calculator.php`
- テスト: `tests/unit/WCD_CalculatorTest.php`

- [ ] **ステップ 1: 失敗するテストを書く**

設計書「テスト方針」の観点表（しきい値未満／境界値／最上位1段のみ／ルール未設定／クランプ／未整列／重複）のうち、`WCD_Calculator` が担う観点をここでカバーする。

```php
<?php
/**
 * WCD_Calculator のテスト。
 *
 * @package Welcart_Cart_Discount
 */

use PHPUnit\Framework\TestCase;

class WCD_CalculatorTest extends TestCase {

	/**
	 * @return WCD_Rule[]
	 */
	private function sample_rules() {
		return array(
			new WCD_Rule( 10000, 500 ),
			new WCD_Rule( 30000, 2000 ),
		);
	}

	public function test_below_threshold_returns_zero() {
		$this->assertSame( 0.0, WCD_Calculator::calculate( 9999, $this->sample_rules() ) );
	}

	public function test_boundary_value_is_inclusive() {
		$this->assertSame( 500.0, WCD_Calculator::calculate( 10000, $this->sample_rules() ) );
	}

	public function test_highest_reached_tier_only() {
		$this->assertSame( 2000.0, WCD_Calculator::calculate( 35000, $this->sample_rules() ) );
	}

	public function test_no_rules_returns_zero() {
		$this->assertSame( 0.0, WCD_Calculator::calculate( 50000, array() ) );
	}

	public function test_amount_exceeding_subtotal_is_clamped() {
		$rules = array( new WCD_Rule( 10000, 50000 ) );

		$this->assertSame( 10000.0, WCD_Calculator::calculate( 10000, $rules ) );
	}

	public function test_unsorted_rules_still_pick_correct_tier() {
		$rules = array(
			new WCD_Rule( 30000, 2000 ),
			new WCD_Rule( 10000, 500 ),
		);

		$this->assertSame( 2000.0, WCD_Calculator::calculate( 35000, $rules ) );
	}
}
```

- [ ] **ステップ 2: 失敗することを確認するために実行する**

実行: `vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/unit/WCD_CalculatorTest.php`
期待: FAIL（`WCD_Calculator` が未定義）

- [ ] **ステップ 3: 最小限の実装を書く**

```php
<?php
/**
 * 割引計算コア。WordPress・Welcart に非依存の純粋な計算ロジック。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * カート小計と割引ルール群から適用すべき割引額を計算する。
 */
class WCD_Calculator {

	/**
	 * 小計と割引ルール群から割引額（正の値）を計算する。
	 *
	 * 到達した最上位（しきい値が最大）の1段のみを適用する。
	 * 割引額はしきい値超過分を防ぐため小計でクランプする。
	 *
	 * @param float      $subtotal カート小計。
	 * @param WCD_Rule[] $rules    割引ルールの配列。
	 * @return float 割引額（正の値）。0以上。
	 */
	public static function calculate( $subtotal, array $rules ) {
		$sorted = $rules;

		usort(
			$sorted,
			function ( WCD_Rule $a, WCD_Rule $b ) {
				return $b->get_threshold() <=> $a->get_threshold();
			}
		);

		foreach ( $sorted as $rule ) {
			if ( $subtotal >= $rule->get_threshold() ) {
				return (float) min( $rule->get_amount(), $subtotal );
			}
		}

		return 0.0;
	}
}
```

- [ ] **ステップ 4: パスすることを確認するために実行する**

実行: `vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/unit/WCD_CalculatorTest.php`
期待: PASS（6 tests, 6 assertions）

- [ ] **ステップ 5: コミット**

```bash
git add includes/class-wcd-calculator.php tests/unit/WCD_CalculatorTest.php
git commit -m "feat: WCD_Calculator 割引計算コアを追加"
```

---

### タスク 4: WCD_Settings::normalize()（TDD）

**ファイル:**
- 作成: `includes/class-wcd-settings.php`
- テスト: `tests/unit/WCD_SettingsTest.php`

- [ ] **ステップ 1: 失敗するテストを書く**

```php
<?php
/**
 * WCD_Settings::normalize() のテスト。
 *
 * @package Welcart_Cart_Discount
 */

use PHPUnit\Framework\TestCase;

class WCD_SettingsTest extends TestCase {

	public function test_casts_values_to_absint() {
		$result = WCD_Settings::normalize(
			array(
				array(
					'threshold' => '10000',
					'amount'    => '500',
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'threshold' => 10000,
					'amount'    => 500,
				),
			),
			$result
		);
	}

	public function test_discards_non_positive_rows() {
		$result = WCD_Settings::normalize(
			array(
				array(
					'threshold' => 0,
					'amount'    => 500,
				),
				array(
					'threshold' => 10000,
					'amount'    => 0,
				),
				array(
					'threshold' => -1,
					'amount'    => 500,
				),
			)
		);

		$this->assertSame( array(), $result );
	}

	public function test_ignores_malformed_rows() {
		$result = WCD_Settings::normalize(
			array(
				'not-an-array',
				array( 'threshold' => 10000 ),
				array(
					'threshold' => 10000,
					'amount'    => 500,
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'threshold' => 10000,
					'amount'    => 500,
				),
			),
			$result
		);
	}

	public function test_duplicate_threshold_last_wins() {
		$result = WCD_Settings::normalize(
			array(
				array(
					'threshold' => 10000,
					'amount'    => 500,
				),
				array(
					'threshold' => 10000,
					'amount'    => 800,
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'threshold' => 10000,
					'amount'    => 800,
				),
			),
			$result
		);
	}

	public function test_sorted_by_threshold_ascending() {
		$result = WCD_Settings::normalize(
			array(
				array(
					'threshold' => 30000,
					'amount'    => 2000,
				),
				array(
					'threshold' => 10000,
					'amount'    => 500,
				),
			)
		);

		$this->assertSame( 10000, $result[0]['threshold'] );
		$this->assertSame( 30000, $result[1]['threshold'] );
	}
}
```

- [ ] **ステップ 2: 失敗することを確認するために実行する**

実行: `vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/unit/WCD_SettingsTest.php`
期待: FAIL（`WCD_Settings` が未定義）

- [ ] **ステップ 3: 最小限の実装を書く**

`get_rules()` / `save_rules()` は `get_option()` / `update_option()` に依存するため PHPUnit（WordPress 非起動）では対象外とする。設計書の記載通り、テスト対象は `normalize()` のみ。

```php
<?php
/**
 * 設定の読み書き・正規化。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * プラグイン設定（割引ルール）の読み書きと正規化を担う。
 */
class WCD_Settings {

	/**
	 * オプションキー。
	 *
	 * @var string
	 */
	const OPTION_KEY = 'wcd_settings';

	/**
	 * POST 等から渡された生の配列を正規化する。
	 *
	 * - 各値を absint() で整数化する
	 * - しきい値または割引額が0以下の行を破棄する
	 * - しきい値が重複する行は後勝ちで排除する
	 * - しきい値の昇順にソートする
	 *
	 * @param array $raw 生の入力。 array<array{threshold: mixed, amount: mixed}>。
	 * @return array 正規化済みの配列。 array<array{threshold: int, amount: int}>。
	 */
	public static function normalize( array $raw ) {
		$by_threshold = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['threshold'], $row['amount'] ) ) {
				continue;
			}

			$threshold = absint( $row['threshold'] );
			$amount    = absint( $row['amount'] );

			if ( $threshold <= 0 || $amount <= 0 ) {
				continue;
			}

			$by_threshold[ $threshold ] = array(
				'threshold' => $threshold,
				'amount'    => $amount,
			);
		}

		ksort( $by_threshold, SORT_NUMERIC );

		return array_values( $by_threshold );
	}

	/**
	 * 保存済みの割引ルールを WCD_Rule の配列として返す。
	 *
	 * @return WCD_Rule[]
	 */
	public static function get_rules() {
		$rows = get_option( self::OPTION_KEY, array() );

		$rules = array();
		foreach ( self::normalize( is_array( $rows ) ? $rows : array() ) as $row ) {
			$rules[] = new WCD_Rule( $row['threshold'], $row['amount'] );
		}

		return $rules;
	}

	/**
	 * 割引ルールを保存する。
	 *
	 * @param array $raw 生の入力。
	 * @return void
	 */
	public static function save_rules( array $raw ) {
		update_option( self::OPTION_KEY, self::normalize( $raw ) );
	}
}
```

- [ ] **ステップ 4: パスすることを確認するために実行する**

実行: `vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/unit/WCD_SettingsTest.php`
期待: PASS（5 tests）

- [ ] **ステップ 5: 全ユニットテストを通しで実行する**

実行: `composer test`
期待: `WCD_RuleTest` / `WCD_CalculatorTest` / `WCD_SettingsTest` すべて PASS（合計15 tests）

- [ ] **ステップ 6: コミット**

```bash
git add includes/class-wcd-settings.php tests/unit/WCD_SettingsTest.php
git commit -m "feat: WCD_Settings::normalize() を追加"
```

---

### タスク 5: Welcart 実ソースでのフック仕様の再確認（調査タスク・コード変更なし）

設計書は Welcart 2.12.1 のソース調査に基づいているが、Integration 層の実装（タスク6・7）に着手する前に、**カート情報 `$cart` の実際の取得方法**など、設計書に明記されていない実装細部をソースから確認する。「フックの実在は必ず Welcart のソースを読んで確認し、推測でフック名を使わない」という制約は個々のフック名だけでなく、コールバックの引数の中身にも適用する。

**ファイル:**
- 変更: なし（調査結果は `docs/design-notes.md` の下書きとして一時ファイルに記録し、タスク17で本文に統合する）

- [ ] **ステップ 1: Welcart 本体を一時ディレクトリに取得する（コミット対象外）**

実行:
```bash
mkdir -p /tmp/welcart-src
curl -L -o /tmp/welcart-src/usc-e-shop.zip "https://downloads.wordpress.org/plugin/usc-e-shop.2.12.1.zip"
unzip -q /tmp/welcart-src/usc-e-shop.zip -d /tmp/welcart-src
```
期待: `/tmp/welcart-src/usc-e-shop/` にソース一式が展開される。

- [ ] **ステップ 2: `usces_order_discount` の呼び出し箇所と引数を確認する**

実行: `grep -n "usces_order_discount" -r /tmp/welcart-src/usc-e-shop`
確認内容: `classes/usceshop.class.php` と `classes/tax.class.php` それぞれの呼び出し行で、`apply_filters()` に渡されている `$cart` の実体（関数内のどの変数か）と、フィルタ適用前後の `$discount` の符号を確認する。

- [ ] **ステップ 3: `usces_filter_cart_table_footer` 周辺のテンプレートを確認する**

実行: `grep -n "usces_filter_cart_table_footer" -r /tmp/welcart-src/usc-e-shop` に続けて該当ファイル（`templates/cart/cart.php`）を読み、`</tfoot>` を含む文字列がどのタイミング・どの変数で組み立てられているか、また `$usces` グローバルからカート情報を取得する具体的な呼び出し（`$usces->cart->get_cart()` か、別のメソッドか）を確認する。

- [ ] **ステップ 4: `usces_filter_order_discount_recalculation` の呼び出し箇所を確認する**

実行: `grep -n "usces_filter_order_discount_recalculation" -r /tmp/welcart-src/usc-e-shop` に続けて `functions/item_post.php:2805` 付近と `:3023` 付近を読み、引数の順序と型（`$cart` は配列かオブジェクトか、`$condition` の取りうる値）を確認する。

- [ ] **ステップ 5: 確認結果をメモに残す**

`docs/design-notes.md` はタスク17で作成するため、ここでは一時メモとして `/tmp/welcart-src/findings.md` に、各フックについて「呼び出し元ファイル:行番号」「引数の実体」「符号や型の注意点」を箇条書きで残す。設計書の記述と食い違いがあれば明記する。

- [ ] **ステップ 6: コミット（該当なし）**

このタスクはコード変更を伴わないためコミットしない。ステップ5のメモはタスク17で `docs/design-notes.md` に統合してからコミットする。

---

### タスク 6: プラグイン本体・WCD_Plugin（フック登録の一元管理）

**ファイル:**
- 作成: `includes/class-wcd-plugin.php`

このクラスは WordPress 起動が前提のため PHPUnit ユニットテスト対象外（設計書の「テスト方針」表に含まれない）。動作確認はタスク14のDocker環境で行う。

- [ ] **ステップ 1: `WCD_Plugin` を実装する**

タスク5で確認した Welcart の有効性判定クラス名（`usc_e_shop`、`classes/usceshop.class.php` で定義されるメインクラス）を用いて、Welcart 非有効時は全フック登録を見送る。

```php
<?php
/**
 * フック登録の一元管理。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * プラグイン全体のフック登録を一元管理する。
 */
class WCD_Plugin {

	/**
	 * plugins_loaded で呼ばれる初期化処理。
	 *
	 * Welcart が有効でない場合はフックを登録せず、管理画面に通知のみ出す。
	 *
	 * @return void
	 */
	public static function init() {
		load_plugin_textdomain(
			'welcart-cart-discount',
			false,
			dirname( plugin_basename( WCD_PLUGIN_FILE ) ) . '/languages'
		);

		if ( ! class_exists( 'usc_e_shop' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_missing_welcart_notice' ) );
			return;
		}

		add_filter( 'usces_order_discount', array( 'WCD_Integration', 'filter_order_discount' ), 10, 2 );
		add_filter( 'usces_filter_cart_table_footer', array( 'WCD_Integration', 'filter_cart_table_footer' ) );
		add_filter( 'usces_filter_order_discount_recalculation', array( 'WCD_Integration', 'filter_order_recalculation' ), 10, 4 );

		add_action( 'admin_menu', array( 'WCD_Admin', 'register_menu' ) );
		add_action( 'admin_post_wcd_save_settings', array( 'WCD_Admin', 'handle_save' ) );
	}

	/**
	 * Welcart 未有効時の管理画面通知を描画する。
	 *
	 * @return void
	 */
	public static function render_missing_welcart_notice() {
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Welcart Cart Discount には Welcart Shop プラグインの有効化が必要です。', 'welcart-cart-discount' )
		);
	}
}
```

- [ ] **ステップ 2: 構文チェックを行う**

実行: `php -l includes/class-wcd-plugin.php`
期待: `No syntax errors detected`

- [ ] **ステップ 3: コミット**

```bash
git add includes/class-wcd-plugin.php
git commit -m "feat: WCD_Plugin によるフック登録の一元管理を追加"
```

---

### タスク 7: WCD_Integration（割引額注入・受注再計算）

**ファイル:**
- 作成: `includes/class-wcd-integration.php`（本タスクでは `filter_order_discount` / `filter_order_recalculation` と共通処理 `calculate_amount` のみ実装。カート表示は Task 8）

- [ ] **ステップ 1: `WCD_Integration` の骨格と割引額注入・再計算メソッドを実装する**

タスク5で確認した `$cart` の実体（`get_total_price( $cart )` にそのまま渡せる形）を前提とする。

```php
<?php
/**
 * Welcart フックへの接続を担うアダプタ。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Welcart のフックと WCD_Calculator を接続する薄いアダプタ層。
 */
class WCD_Integration {

	/**
	 * usces_order_discount フィルタ。割引額を注入する。
	 *
	 * Welcart は割引額を負値で扱うため、既存の割引額から算出した割引額を減算する。
	 *
	 * @param float $discount 既存の割引額（負値、または0）。
	 * @param array $cart     カート情報。
	 * @return float 加算後の割引額（負値）。
	 */
	public static function filter_order_discount( $discount, $cart ) {
		return $discount - self::calculate_amount( $cart );
	}

	/**
	 * usces_filter_order_discount_recalculation フィルタ。受注編集時の再計算。
	 *
	 * @param float  $discount  既存の割引額。
	 * @param array  $cart      カート情報。
	 * @param string $condition 再計算の条件。
	 * @param int    $order_id  受注ID。
	 * @return float
	 */
	public static function filter_order_recalculation( $discount, $cart, $condition, $order_id ) {
		return $discount - self::calculate_amount( $cart );
	}

	/**
	 * 現在の設定とカートから割引額を計算する。独自フィルタの適用点でもある。
	 *
	 * @param array $cart カート情報。
	 * @return float 割引額（正の値）。
	 */
	private static function calculate_amount( $cart ) {
		global $usces;

		$subtotal = ( isset( $usces ) && is_object( $usces ) )
			? (float) $usces->get_total_price( $cart )
			: 0.0;

		/**
		 * 割引判定の対象となる小計を変更する。第二段階の商品カテゴリ除外で使用する。
		 *
		 * @param float $subtotal 小計。
		 * @param array $cart     カート情報。
		 */
		$subtotal = apply_filters( 'wcd_eligible_subtotal', $subtotal, $cart );

		/**
		 * 適用可能な割引ルールを変更する。第二段階の会員ランク除外で使用する。
		 *
		 * @param WCD_Rule[] $rules 割引ルールの配列。
		 * @param array      $cart  カート情報。
		 */
		$rules = apply_filters( 'wcd_available_rules', WCD_Settings::get_rules(), $cart );

		return WCD_Calculator::calculate( $subtotal, $rules );
	}
}
```

- [ ] **ステップ 2: 構文チェックを行う**

実行: `php -l includes/class-wcd-integration.php`
期待: `No syntax errors detected`

- [ ] **ステップ 3: コミット**

```bash
git add includes/class-wcd-integration.php
git commit -m "feat: WCD_Integration に割引額注入と受注再計算を追加"
```

---

### タスク 8: WCD_Integration（カート画面表示）

**ファイル:**
- 変更: `includes/class-wcd-integration.php`

- [ ] **ステップ 1: `filter_cart_table_footer` を追加する**

タスク5ステップ3で確認したカート情報取得方法をここに反映する（下記は設計書の想定に基づく暫定実装。ステップ3の確認結果と食い違う場合は取得方法を修正する）。

```php
	/**
	 * usces_filter_cart_table_footer フィルタ。割引行をカート表に挿入する。
	 *
	 * @param string $footer カート表フッターの HTML。
	 * @return string
	 */
	public static function filter_cart_table_footer( $footer ) {
		global $usces;

		if ( ! isset( $usces ) || ! is_object( $usces ) ) {
			return $footer;
		}

		$cart   = $usces->cart->get_cart();
		$amount = self::calculate_amount( $cart );

		if ( $amount <= 0 ) {
			return $footer;
		}

		$needle = '</tfoot>';
		if ( false === strpos( $footer, $needle ) ) {
			return $footer;
		}

		$subtotal = (float) $usces->get_total_price( $cart );
		$row      = sprintf(
			'<tr class="wcd-discount-row"><th>%1$s</th><td>-&yen;%2$s</td></tr>' .
			'<tr class="wcd-discounted-total-row"><th>%3$s</th><td>&yen;%4$s</td></tr>',
			esc_html__( '自動割引', 'welcart-cart-discount' ),
			esc_html( number_format( $amount ) ),
			esc_html__( '割引後合計', 'welcart-cart-discount' ),
			esc_html( number_format( max( 0, $subtotal - $amount ) ) )
		);

		return str_replace( $needle, $row . $needle, $footer );
	}
```

このメソッドは `filter_order_discount` の直後、`calculate_amount` メソッドの前に挿入する。

- [ ] **ステップ 2: 構文チェックを行う**

実行: `php -l includes/class-wcd-integration.php`
期待: `No syntax errors detected`

- [ ] **ステップ 3: コミット**

```bash
git add includes/class-wcd-integration.php
git commit -m "feat: カート画面への割引行表示を追加"
```

---

### タスク 9: WCD_Admin（メニュー登録・設定画面描画）

**ファイル:**
- 作成: `includes/class-wcd-admin.php`

- [ ] **ステップ 1: capability 判定・メニュー登録・画面描画を実装する**

```php
<?php
/**
 * 設定画面の描画と保存処理。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 割引ルール設定画面。
 */
class WCD_Admin {

	/**
	 * 保存処理・メニュー登録で共通して使う capability を返す。
	 *
	 * Welcart 独自の capability（wel_manage_setting）が administrator ロールに
	 * 存在しない環境では manage_options にフォールバックする。
	 *
	 * @return string
	 */
	public static function get_capability() {
		$administrator = get_role( 'administrator' );

		if ( $administrator && $administrator->has_cap( 'wel_manage_setting' ) ) {
			return 'wel_manage_setting';
		}

		return 'manage_options';
	}

	/**
	 * admin_menu アクション。Welcart Shop メニュー配下にサブメニューを追加する。
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			USCES_PLUGIN_BASENAME,
			__( '自動割引設定', 'welcart-cart-discount' ),
			__( '自動割引設定', 'welcart-cart-discount' ),
			self::get_capability(),
			'wcd_settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * 設定画面を描画する。
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::get_capability() ) ) {
			wp_die( esc_html__( 'この画面にアクセスする権限がありません。', 'welcart-cart-discount' ) );
		}

		$rules = get_option( WCD_Settings::OPTION_KEY, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '自動割引設定', 'welcart-cart-discount' ); ?></h1>
			<p><?php esc_html_e( 'カート合計金額のしきい値と割引額を複数段設定できます。到達した最上位の1段のみが適用されます。', 'welcart-cart-discount' ); ?></p>
			<?php if ( isset( $_GET['wcd_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '設定を保存しました。', 'welcart-cart-discount' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wcd_save_settings" />
				<?php wp_nonce_field( 'wcd_save_settings', 'wcd_nonce' ); ?>
				<table class="widefat" id="wcd-rules-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'しきい値金額（円）', 'welcart-cart-discount' ); ?></th>
							<th><?php esc_html_e( '割引額（円）', 'welcart-cart-discount' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rules as $index => $rule ) : ?>
							<tr>
								<td><input type="number" min="1" step="1" name="wcd_rules[<?php echo (int) $index; ?>][threshold]" value="<?php echo esc_attr( $rule['threshold'] ); ?>" /></td>
								<td><input type="number" min="1" step="1" name="wcd_rules[<?php echo (int) $index; ?>][amount]" value="<?php echo esc_attr( $rule['amount'] ); ?>" /></td>
								<td><button type="button" class="button wcd-remove-row"><?php esc_html_e( '削除', 'welcart-cart-discount' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="wcd-add-row"><?php esc_html_e( '行を追加', 'welcart-cart-discount' ); ?></button></p>
				<?php submit_button( __( '設定を保存', 'welcart-cart-discount' ) ); ?>
			</form>
		</div>
		<script>
		( function() {
			var table = document.getElementById( 'wcd-rules-table' ).getElementsByTagName( 'tbody' )[0];

			function rowTemplate( index ) {
				var tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td><input type="number" min="1" step="1" name="wcd_rules[' + index + '][threshold]" /></td>' +
					'<td><input type="number" min="1" step="1" name="wcd_rules[' + index + '][amount]" /></td>' +
					'<td><button type="button" class="button wcd-remove-row"><?php echo esc_js( __( '削除', 'welcart-cart-discount' ) ); ?></button></td>';
				return tr;
			}

			document.getElementById( 'wcd-add-row' ).addEventListener( 'click', function() {
				table.appendChild( rowTemplate( table.children.length ) );
			} );

			table.addEventListener( 'click', function( event ) {
				if ( event.target.classList.contains( 'wcd-remove-row' ) ) {
					event.target.closest( 'tr' ).remove();
				}
			} );
		} )();
		</script>
		<?php
	}
}
```

`$_GET['wcd_saved']` は表示切り替えのみに使い値を出力しないため、nonce 検証は不要（読み取り専用の表示分岐）。

- [ ] **ステップ 2: 構文チェックを行う**

実行: `php -l includes/class-wcd-admin.php`
期待: `No syntax errors detected`

- [ ] **ステップ 3: コミット**

```bash
git add includes/class-wcd-admin.php
git commit -m "feat: WCD_Admin に設定画面の描画を追加"
```

---

### タスク 10: WCD_Admin（保存処理・セキュリティ）

**ファイル:**
- 変更: `includes/class-wcd-admin.php`

- [ ] **ステップ 1: `handle_save` を追加する**

必須要件5（nonce検証・権限チェック・サニタイズ）をこのメソッド冒頭に集約する。`class WCD_Admin {` の直後、`get_capability()` の前後どちらでもよいが、ここでは末尾に追加する。

```php
	/**
	 * admin_post_wcd_save_settings アクション。設定を保存する。
	 *
	 * @return void
	 */
	public static function handle_save() {
		check_admin_referer( 'wcd_save_settings', 'wcd_nonce' );

		if ( ! current_user_can( self::get_capability() ) ) {
			wp_die( esc_html__( 'この操作を行う権限がありません。', 'welcart-cart-discount' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce は上の check_admin_referer() で検証済み。
		$raw = isset( $_POST['wcd_rules'] ) && is_array( $_POST['wcd_rules'] )
			? wp_unslash( $_POST['wcd_rules'] )
			: array();

		WCD_Settings::save_rules( $raw );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'wcd_settings',
					'wcd_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
```

`WCD_Settings::save_rules()` 内部の `normalize()` が `absint()` で整数化するため、`$_POST['wcd_rules']` の各値は文字列のままここでは渡してよい（サニタイズは normalize 側の責務）。

- [ ] **ステップ 2: 構文チェックを行う**

実行: `php -l includes/class-wcd-admin.php`
期待: `No syntax errors detected`

- [ ] **ステップ 3: コミット**

```bash
git add includes/class-wcd-admin.php
git commit -m "feat: 設定保存処理に nonce検証・権限チェック・サニタイズを実装"
```

---

### タスク 11: PHPCS 実行と違反ゼロ化

**ファイル:**
- 変更: `includes/*.php`, `welcart-cart-discount.php`（lint 違反の修正のみ。ロジック変更は行わない）

- [ ] **ステップ 1: lint を実行する**

実行: `composer lint`
期待: 現時点での違反一覧が出力される（初回は0件でない可能性が高い）

- [ ] **ステップ 2: 違反を修正する**

出力された違反（インデント、Yoda条件、DocBlock不備、`esc_html__` の使用漏れ等）を1件ずつ修正する。修正内容はコードの意味を変えない範囲に限定する。

- [ ] **ステップ 3: 違反ゼロを確認するために再実行する**

実行: `composer lint`
期待: `No violations found` 相当の終了コード0

- [ ] **ステップ 4: ユニットテストが壊れていないことを確認する**

実行: `composer test`
期待: 15 tests すべて PASS

- [ ] **ステップ 5: コミット**

```bash
git add includes/ welcart-cart-discount.php
git commit -m "style: WPCS違反を解消"
```

---

### タスク 12: i18n（テキストドメイン読み込みと .pot 生成）

**ファイル:**
- 作成: `languages/welcart-cart-discount.pot`

`load_plugin_textdomain()` の呼び出しはタスク6で実装済み。ここでは翻訳抽出用の `.pot` を生成する。

- [ ] **ステップ 1: WP-CLI が使えるか確認する**

実行: `wp --info` （Docker環境が必要な場合はタスク13完了後に本タスクを実施してもよい。ここでは `wp i18n make-pot` が使える前提で進める）

- [ ] **ステップ 2: `.pot` を生成する**

実行: `wp i18n make-pot . languages/welcart-cart-discount.pot --domain=welcart-cart-discount --exclude=vendor,tests,docker`
期待: `languages/welcart-cart-discount.pot` が生成され、`__()` / `esc_html__()` 等で使われている全文字列が抽出されている

- [ ] **ステップ 3: 生成内容を確認する**

実行: `grep -c "msgid" languages/welcart-cart-discount.pot`
期待: タスク9・10で使用した文言（「自動割引設定」「設定を保存しました。」等）が含まれる

- [ ] **ステップ 4: コミット**

```bash
git add languages/welcart-cart-discount.pot
git commit -m "chore: 翻訳用 .pot を生成"
```

---

### タスク 13: Docker 検証環境構築

**ファイル:**
- 作成: `docker/docker-compose.yml`
- 作成: `docker/.env.example`

- [ ] **ステップ 1: `docker-compose.yml` を作成する**

```yaml
services:
  db:
    image: mariadb:10.11
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
      MYSQL_ROOT_PASSWORD: root
    volumes:
      - db_data:/var/lib/mysql

  wordpress:
    image: wordpress:6.6-php8.2-apache
    restart: unless-stopped
    depends_on:
      - db
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_NAME: wordpress
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
    ports:
      - "8080:80"
    volumes:
      - wp_data:/var/www/html
      - ../:/var/www/html/wp-content/plugins/welcart-cart-discount

volumes:
  db_data:
  wp_data:
```

- [ ] **ステップ 2: `.env.example` を作成する**

```
# docker compose --env-file docker/.env up -d の形で使用する。
# 本番相当の値は docker/.env にコピーしてから設定し、.env 自体はコミットしない。
COMPOSE_PROJECT_NAME=welcart-cart-discount
```

- [ ] **ステップ 3: コンテナを起動する**

実行: `docker compose -f docker/docker-compose.yml up -d`
期待: `db` / `wordpress` コンテナが起動する

- [ ] **ステップ 4: 起動確認する**

実行: `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/`
期待: `200` または WordPress インストール画面へのリダイレクトを示すステータス

- [ ] **ステップ 5: 動作したイメージタグを記録する**

`docker-compose.yml` の `wordpress:6.6-php8.2-apache` で Welcart 2.12.1 が動作しない場合（PHPバージョン非互換など）、動作した組み合わせに書き換える。最終的に動作確認できたタグをこのファイルに残す（README への転記はタスク17）。

- [ ] **ステップ 6: コミット**

```bash
git add docker/docker-compose.yml docker/.env.example
git commit -m "chore: Docker検証環境を追加"
```

---

### タスク 14: GitLab CI 設定

**ファイル:**
- 作成: `.gitlab-ci.yml`

- [ ] **ステップ 1: CI 設定を作成する**

```yaml
stages:
  - lint
  - test

default:
  image: php:7.4-cli
  before_script:
    - apt-get update -yqq && apt-get install -yqq git unzip libzip-dev zip
    - docker-php-ext-install zip
    - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    - composer install --no-interaction --prefer-dist

lint:
  stage: lint
  script:
    - composer lint

test:
  stage: test
  script:
    - composer test
```

- [ ] **ステップ 2: ローカルで構文を確認する**

実行: `ruby -ryaml -e "YAML.load_file('.gitlab-ci.yml')"` （Ruby が無ければ `python3 -c "import yaml; yaml.safe_load(open('.gitlab-ci.yml'))"`）
期待: エラーなくパースできる

- [ ] **ステップ 3: コミット**

```bash
git add .gitlab-ci.yml
git commit -m "chore: GitLab CI で lint と test を実行するよう設定"
```

---

### タスク 15: 実機検証（3箇所整合の手動確認）

**ファイル:**
- 変更なし（検証作業。スクリーンショットは Task 17 で `docs/verification.md` に整理する）

この課題の主眼は「カート・確認・受注データの3箇所整合」であるため、他のどのタスクよりも慎重に行う。

- [ ] **ステップ 1: WordPress 初期セットアップと Welcart インストール**

Docker環境（タスク13）の `http://localhost:8080` にアクセスし、WordPress の初期設定を行った上で、管理画面のプラグイン新規追加から Welcart Shop（`usc-e-shop`）をインストール・有効化する。本プラグインも `wp-content/plugins/welcart-cart-discount` としてマウント済みのため、有効化する。

- [ ] **ステップ 2: テスト商品と割引ルールを用意する**

Welcart の商品管理で単価が明確なテスト商品を1点登録する（例: 40,000円）。本プラグインの設定画面（`Welcart Shop > 自動割引設定`）で「10,000円以上で500円引き」「30,000円以上で2,000円引き」の2段を保存する。

- [ ] **ステップ 3: しきい値未満での確認**

カートに9,000円分の商品を入れ、カート画面に割引行が表示されないことを確認する。

- [ ] **ステップ 4: しきい値到達での確認（カート画面）**

カート内容を10,000円以上30,000円未満に調整し、カート画面に「自動割引 -¥500」「割引後合計」の行が表示されることを確認する。

- [ ] **ステップ 5: 上位段への切り替わりの確認**

カート内容を30,000円以上に調整し、割引額が¥2,000に切り替わる（¥500と¥2,000の合算にならない）ことを確認する。

- [ ] **ステップ 6: 購入確認画面での整合確認**

30,000円以上の状態のまま購入手続きに進み、確認画面の割引行が¥2,000、支払総額がカート画面の割引後合計と一致することを確認する。

- [ ] **ステップ 7: 受注データでの整合確認**

注文を確定し、管理画面の受注一覧・受注詳細で `order_discount` に対応する表示額が¥2,000になっていることを確認する。

- [ ] **ステップ 8: 受注編集時の再計算確認**

管理画面から当該受注の商品数量を変更し再計算した際、割引額が消えず、変更後の小計に対応する段の割引が再適用されることを確認する。

- [ ] **ステップ 9: 各ステップのスクリーンショットを保存する**

`docs/screenshots/` ディレクトリを作成し、ステップ2〜8それぞれの画面をスクリーンショットとして保存する（ファイル名は `01-settings.png` のように連番+内容）。

実行:
```bash
mkdir -p docs/screenshots
```

- [ ] **ステップ 10: `composer lint` と `composer test` の実行結果を保存する**

実行:
```bash
composer lint | tee docs/screenshots/10-composer-lint.txt
composer test | tee docs/screenshots/11-composer-test.txt
```

このステップのみテキスト出力であり、設計書の動作確認記録7番目の項目（「`composer lint` と `composer test` の実行結果」）に対応する。

---

### タスク 16: README・設計メモ・AI活用レポート・動作確認記録

**ファイル:**
- 作成: `README.md`
- 作成: `docs/design-notes.md`
- 作成: `docs/ai-report.md`
- 作成: `docs/verification.md`

- [ ] **ステップ 1: `docs/design-notes.md` を作成する**

設計書の「使用するフック一覧」節、「確定した仕様判断」節、およびタスク5の調査メモ（`/tmp/welcart-src/findings.md`）を統合し、フックごとに「採用した理由」「検討したが採用しなかった候補」を明記した設計メモを作成する。検討したが採用しなかった候補の例として、「割引の複数段累積適用（不採用: 課題文の例示と不整合）」「Settings API 経由の保存（不採用: 動的増減行と噛み合わない）」を含める。

- [ ] **ステップ 2: `docs/ai-report.md` を作成する**

以下の観点でAI活用レポートをまとめる。CLAUDE.md の運用ルールに従い、作業中に発生した誤り・修正過程は記憶に頼らず、本タスクまでの各コミットメッセージと `git log` を実際に確認しながら記述する。

実行: `git log --oneline`

内容の骨子:
- 使用ツール（Claude Code）と進め方（設計→計画→実装→検証の4段階、chot-harnessでの設計・計画作成を含む）
- タスク5で実施した Welcart 実ソースでの事前検証の理由（フック名の推測を避けるため）
- AIの出力が誤っていた箇所とその発見・修正方法（実装中に判明した内容をここに追記する。本計画作成時点では発生していないため、空欄のまま提出しない。実装フェーズで判明した事象を都度追記すること）
- うまくいかなかったこと（同上）

- [ ] **ステップ 3: `docs/verification.md` を作成する**

タスク15で取得したスクリーンショット（`docs/screenshots/`）を、設計書の「動作確認の記録」節にある7項目の順に並べ、各画像に1〜2文の説明を添える。

- [ ] **ステップ 4: `README.md` を作成する**

タスク13・15で実際に確認できた WordPress／PHP イメージタグと、Welcart 2.12.1 を用いた動作確認結果を記載する。「動くはず」ではなく「この構成で動作を確認した」と明記する。

構成:
```markdown
# Welcart Cart Discount

Welcart のカート合計金額に応じて自動割引を適用する独立プラグイン。

## 動作確認環境

- WordPress: （タスク13・15で確認した実際のバージョン）
- Welcart（usc-e-shop）: 2.12.1
- PHP: （タスク13・15で確認した実際のバージョン）
- 上記の組み合わせで、カート画面・購入確認画面・受注データの割引額整合を実機確認済み（docs/verification.md 参照）

## インストール

1. `welcart-cart-discount` ディレクトリを `wp-content/plugins/` に配置する
2. WordPress管理画面のプラグイン一覧から有効化する
3. `Welcart Shop > 自動割引設定` からしきい値と割引額を設定する

## ローカル動作確認手順

\`\`\`bash
docker compose -f docker/docker-compose.yml up -d
composer install
composer test
composer lint
\`\`\`

## ドキュメント

- 設計メモ: docs/design-notes.md
- AI活用レポート: docs/ai-report.md
- 動作確認の記録: docs/verification.md
```

- [ ] **ステップ 5: コミット**

```bash
git add README.md docs/
git commit -m "docs: README・設計メモ・AI活用レポート・動作確認記録を追加"
```

---

## 全体の完了条件チェックリスト

- [ ] 必須要件1〜6（設計書「確定した仕様判断」「セキュリティ」節に対応）をすべて満たしている
- [ ] `composer test` が全PASS
- [ ] `composer lint` が違反ゼロ
- [ ] カート・確認・受注データの3箇所整合をタスク15で実機確認済み
- [ ] 提出物5点（プラグイン一式／README／設計メモ／AI活用レポート／動作確認の記録）がすべて揃っている
- [ ] 第二段階の拡張点（`wcd_eligible_subtotal` / `wcd_available_rules`）が用意されており、第一段階のコードには会員ランク・商品カテゴリ除外の実装が含まれていない
