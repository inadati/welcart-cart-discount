<?php
/**
 * WCD_Cart_Row_Builder のテスト。
 *
 * 割引行が挿入先テーブルの列構成（colspan を含む）と一致することを検証する。
 * 列構成が不正だとブラウザの表の自動レイアウトが崩れ、割引行だけでなく
 * 他の列（サムネイル列など）の幅計算まで壊れるため、ここは実装の要点である。
 *
 * @package Welcart_Cart_Discount
 */

use PHPUnit\Framework\TestCase;

class WCD_Cart_Row_BuilderTest extends TestCase {

	/**
	 * Welcart 本体 templates/cart/cart.php:55-66 が出力するカート表フッター（8列）。
	 *
	 * @return string
	 */
	private function cart_footer() {
		return '</tbody>
		<tfoot>
		<tr>
			<th class="num">&nbsp;</th>
			<th class="thumbnail">&nbsp;</th>
			<th colspan="3" scope="row" class="aright">total items</th>
			<th class="aright subtotal">&yen;34,000</th>
			<th class="stock">&nbsp;</th>
			<th class="action">&nbsp;</th>
		</tr>
		</tfoot>
	</table>';
	}

	/**
	 * 単一行を組み立てるヘルパ。
	 *
	 * @param string $footer フッター HTML.
	 * @return string
	 */
	private function build_one( $footer ) {
		return WCD_Cart_Row_Builder::build(
			$footer,
			array(
				array(
					'class'  => 'wcd-discount-row',
					'label'  => '自動割引',
					'amount' => '-&yen;2,000',
				),
			)
		);
	}

	/**
	 * 行の列数（colspan の合計）を数える。
	 *
	 * @param string $row_html 行 HTML.
	 * @return int
	 */
	private function count_columns( $row_html ) {
		preg_match_all( '#<(th|td)\b([^>]*)>#i', $row_html, $cells, PREG_SET_ORDER );

		$columns = 0;
		foreach ( $cells as $cell ) {
			if ( preg_match( '#\bcolspan\s*=\s*"(\d+)"#i', $cell[2], $m ) ) {
				$columns += (int) $m[1];
				continue;
			}
			++$columns;
		}

		return $columns;
	}

	public function test_generated_row_spans_the_same_column_count_as_the_table() {
		$row = $this->build_one( $this->cart_footer() );

		// 本体のフッター行と同じ8列でなければ表の自動レイアウトが崩れる。
		$this->assertSame( 8, $this->count_columns( $row ) );
	}

	public function test_label_cell_inherits_colspan_from_the_template_row() {
		$row = $this->build_one( $this->cart_footer() );

		$this->assertStringContainsString( '<td colspan="3" class="aright">自動割引</td>', $row );
	}

	public function test_amount_is_placed_in_the_subtotal_column() {
		$row = $this->build_one( $this->cart_footer() );

		$this->assertStringContainsString( '<td class="aright subtotal">-&yen;2,000</td>', $row );
	}

	public function test_scope_attribute_is_removed_because_cells_are_output_as_td() {
		$row = $this->build_one( $this->cart_footer() );

		$this->assertStringNotContainsString( 'scope=', $row );
	}

	public function test_row_class_is_applied() {
		$row = $this->build_one( $this->cart_footer() );

		$this->assertStringContainsString( '<tr class="wcd-discount-row">', $row );
	}

	public function test_multiple_rows_are_generated_in_order() {
		$html = WCD_Cart_Row_Builder::build(
			$this->cart_footer(),
			array(
				array(
					'class'  => 'wcd-discount-row',
					'label'  => '自動割引',
					'amount' => '-2,000',
				),
				array(
					'class'  => 'wcd-discounted-total-row',
					'label'  => '割引後合計',
					'amount' => '32,000',
				),
			)
		);

		$this->assertSame( 2, substr_count( $html, '<tr class=' ) );
		$this->assertLessThan(
			strpos( $html, 'wcd-discounted-total-row' ),
			strpos( $html, 'wcd-discount-row' )
		);
	}

	public function test_adapts_to_a_seven_column_table() {
		/*
		 * 確認画面（templates/cart/confirm.php:54-60）は action 列があり
		 * stock 列が無い7列構成。列構成をハードコードしていないことの検証。
		 */
		$footer = '<tfoot>
			<tr class="total_items_price">
			<th class="num">&nbsp;</th>
			<th class="thumbnail">&nbsp;</th>
			<th colspan="3" class="aright totallabel">total items</th>
			<th class="aright totalend">&yen;34,000</th>
			<th class="action">&nbsp;</th>
			</tr>
			</tfoot>';

		$row = $this->build_one( $footer );

		$this->assertSame( 7, $this->count_columns( $row ) );
		$this->assertStringContainsString( '<td class="aright totalend">-&yen;2,000</td>', $row );
	}

	public function test_falls_back_to_a_minimal_row_when_the_column_layout_is_unreadable() {
		/*
		 * 列構成が読み取れない場合でも、割引額を表示しないより表示する方を優先する
		 * （カート・確認・受注データの3箇所で割引が整合することが課題要件の核心のため）。
		 */
		$row = $this->build_one( '<div>no table here</div>' );

		$this->assertStringContainsString( '<tr class="wcd-discount-row">', $row );
		$this->assertStringContainsString( '自動割引', $row );
		$this->assertStringContainsString( '-&yen;2,000', $row );
	}

	public function test_returns_empty_string_for_no_rows() {
		$this->assertSame( '', WCD_Cart_Row_Builder::build( $this->cart_footer(), array() ) );
	}

	public function test_parse_template_returns_null_without_a_colspan_cell() {
		// colspan を持つセルが無い行はラベル列を特定できないため null を返す。
		$footer = '<tfoot><tr><th class="a">x</th><td class="b">y</td></tr></tfoot>';

		$this->assertNull( WCD_Cart_Row_Builder::parse_template( $footer ) );
	}

	public function test_parse_template_returns_null_for_empty_input() {
		$this->assertNull( WCD_Cart_Row_Builder::parse_template( '' ) );
	}
}
