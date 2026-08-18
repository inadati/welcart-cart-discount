<?php
/**
 * カート表フッターに挿入する行の HTML を組み立てる。
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * カート表フッターへ挿入する割引行を、挿入先テーブルの列構成に合わせて組み立てる。
 *
 * 当初この処理は `<tr><th>ラベル</th><td>金額</td></tr>` という2セルのみの行を
 * 出力していた。しかし Welcart のカート表は8列（`num` / `thumbnail` /
 * `productname` / `unitprice` / `quantity` / `subtotal` / `stock` / `action`）
 * あり、colspan の無い2セルの行はテーブルの列構成として不正である。
 * ブラウザはこの不正な行を自動レイアウトの幅計算に巻き込むため、
 * 割引行が先頭2列の幅に押し込められて折り返すだけでなく、他の列
 * （特に固定幅を期待している thumbnail 列）の幅計算まで崩れる。
 *
 * Welcart 本体は同じ表の「total items」行を
 * `templates/cart/cart.php:56-64` で colspan 付きの正しい8列構成として出力しており、
 * 確認画面の割引行（`tr.discount`）も `templates/cart/confirm.php:62-68` で
 * 同じ形を取っている。本クラスはこの本体の作りに倣い、挿入先フッターに
 * 実際に存在する行をテンプレートとして読み取って同じ列構成の行を生成する。
 *
 * 列構成をハードコードせず挿入先から読み取るのは、`usces_filter_cart_table_head`
 * / `usces_filter_cart_table_footer` によってテーマ側が列を増減できるためである。
 */
class WCD_Cart_Row_Builder {

	/**
	 * フッター HTML から列構成のテンプレートとなる行を読み取る。
	 *
	 * @param string $footer_html カート表フッターの HTML.
	 * @return array|null セル定義の配列。読み取れない場合は null。
	 *                    各要素は array{attrs:string, is_label:bool, is_amount:bool}。
	 */
	public static function parse_template( $footer_html ) {
		if ( ! is_string( $footer_html ) || '' === $footer_html ) {
			return null;
		}

		if ( ! preg_match( '#<tfoot[^>]*>(.*?)</tfoot>#is', $footer_html, $tfoot ) ) {
			return null;
		}

		if ( ! preg_match( '#<tr[^>]*>(.*?)</tr>#is', $tfoot[1], $tr ) ) {
			return null;
		}

		if ( ! preg_match_all( '#<(th|td)\b([^>]*)>#i', $tr[1], $cells, PREG_SET_ORDER ) ) {
			return null;
		}

		/*
		 * colspan を持つセルをラベル列、その直後のセルを金額列とみなす。
		 * Welcart 本体のフッター行はいずれもこの並び（ラベルに colspan、次が金額）を取る。
		 */
		$label_index = null;
		foreach ( $cells as $index => $cell ) {
			if ( preg_match( '#\bcolspan\s*=#i', $cell[2] ) ) {
				$label_index = $index;
				break;
			}
		}

		if ( null === $label_index || ! isset( $cells[ $label_index + 1 ] ) ) {
			return null;
		}

		$template = array();
		foreach ( $cells as $index => $cell ) {
			$template[] = array(
				/* scope 属性は見出しセル用のため、td として出力する本行では取り除く。 */
				'attrs'     => self::strip_scope( $cell[2] ),
				'is_label'  => ( $index === $label_index ),
				'is_amount' => ( $index === $label_index + 1 ),
			);
		}

		return $template;
	}

	/**
	 * 割引行を組み立てる。
	 *
	 * @param string $footer_html カート表フッターの HTML（列構成の読み取り元）.
	 * @param array  $rows        行定義の配列。各要素は
	 *                            array{class:string, label:string, amount:string}。
	 *                            label / amount はエスケープ済みの文字列を渡すこと.
	 * @return string 生成した行の HTML.
	 */
	public static function build( $footer_html, array $rows ) {
		$template = self::parse_template( $footer_html );
		$html     = '';

		foreach ( $rows as $row ) {
			$class  = isset( $row['class'] ) ? $row['class'] : '';
			$label  = isset( $row['label'] ) ? $row['label'] : '';
			$amount = isset( $row['amount'] ) ? $row['amount'] : '';

			if ( null === $template ) {
				/*
				 * 列構成を読み取れなかった場合のフォールバック。
				 * 整合性（割引額を必ず表示すること）を優先し、
				 * 列位置の正確さを犠牲にした最小構成の行を出力する。
				 */
				$html .= '<tr class="' . $class . '"><th>' . $label . '</th><td>' . $amount . '</td></tr>';
				continue;
			}

			$cells = '';
			foreach ( $template as $cell ) {
				if ( $cell['is_label'] ) {
					$cells .= '<td' . $cell['attrs'] . '>' . $label . '</td>';
				} elseif ( $cell['is_amount'] ) {
					$cells .= '<td' . $cell['attrs'] . '>' . $amount . '</td>';
				} else {
					$cells .= '<td' . $cell['attrs'] . '>&nbsp;</td>';
				}
			}

			$html .= '<tr class="' . $class . '">' . $cells . '</tr>';
		}

		return $html;
	}

	/**
	 * セルの属性文字列から scope 属性を取り除く。
	 *
	 * @param string $attrs 属性文字列.
	 * @return string
	 */
	private static function strip_scope( $attrs ) {
		return (string) preg_replace( '#\s*\bscope\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $attrs );
	}
}
