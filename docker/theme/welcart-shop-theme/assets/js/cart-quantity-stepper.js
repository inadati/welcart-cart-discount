/**
 * カート画面の数量欄に − / + ボタンを付け、変更時に自動で数量更新を行う。
 *
 * ■ なぜフォーム送信（＝ページ再読み込み）なのか
 * Welcart には数量更新用の Ajax エンドポイントが存在しない。
 * カート画面の `uscesCart.upCart()`（classes/usceshop.class.php:2432 が出力する
 * インラインJS）は在庫・注文制限のバリデーションのみを行い true/false を返す
 * 関数であり、実際の更新は name="upButton" を含むフォームPOSTに対して
 * サーバ側の Welcart_Cart::upCart()（classes/cart.class.php:101）が行う
 * （POST の upButton をキーにしたディスパッチ。
 *  usces_register_action( 'upButton', 'post', 'upButton', ... ) / usceshop.class.php:3262）。
 *
 * 独自の Ajax ハンドラを作って $_SESSION['usces_cart'] を直接書き換える方法も
 * 考えられるが、それは Welcart のカートロジックの再実装になる。さらに割引額は
 * サーバ側でカート内容から算出されるため、Ajax で明細だけを更新すると
 * 割引行が古い値のまま取り残される危険がある。「カート・確認・受注データの
 * 3箇所で割引が整合すること」が課題要件の核心なので、その整合を壊す可能性の
 * ある実装は採らない。
 *
 * そこで、既存の更新ボタンを click() して Welcart 自身の更新経路に乗せる。
 * これにより Welcart の在庫バリデーションもそのまま通り、割引額も必ず
 * サーバ側で再計算される。
 *
 * ■ 進歩的強化（progressive enhancement）
 * このスクリプトが動作したときだけ、案内文と更新ボタン（div.upbutton）を隠す。
 * 自動更新される状態では「数量を変更した場合は必ず更新ボタンを押してください。」
 * という案内が誤りになるため。JSが無効な環境では従来どおり更新ボタンが残る。
 */
( function () {
	'use strict';

	var DEBOUNCE_MS = 650;
	var SCROLL_KEY = 'wcdCartScrollY';

	/**
	 * 数量の上限を求める。
	 *
	 * Welcart 自身が upCart() のバリデーションで使う判定
	 * （classes/usceshop.class.php:2462-2476）と同じ順序・同じ条件で求める。
	 * ここで独自の基準を作ると、+ ボタンで増やせた数量が送信後に
	 * Welcart 側で弾かれるという食い違いが起きるため、本体の判定を写している。
	 *
	 * @param {string} restriction 1回の注文数制限（itemRestriction[i]）.
	 * @param {string} acceptable  取り寄せ可否（itemOrderAcceptable[i]。'1' で可）.
	 * @param {string} stock       在庫数（zaikonum[i][post_id][sku]）.
	 * @return {number} 上限。上限なしの場合は Infinity。
	 */
	function resolveMax( restriction, acceptable, stock ) {
		var r = parseInt( restriction, 10 );
		var s = parseInt( stock, 10 );
		var hasR = '' !== restriction && '0' !== restriction && ! isNaN( r );
		var hasS = '' !== stock && ! isNaN( s );
		var canOrder = '1' === acceptable;

		if ( hasR && hasS && r <= s ) {
			return r;
		}
		if ( ! canOrder && hasR && hasS && r > s ) {
			return s;
		}
		if ( ! canOrder && ! hasR && hasS ) {
			return s;
		}
		if ( hasR && ( ! hasS || 0 === s || r > s ) ) {
			return r;
		}

		return Infinity;
	}

	/**
	 * 数量欄が属する行の添字を name 属性から取り出す。
	 * name は quant[0][169][SF-EF-002] の形式（cart.php の明細行）。
	 *
	 * @param {HTMLInputElement} input 数量欄.
	 * @return {string|null}
	 */
	function rowIndexOf( input ) {
		var m = /^quant\[(\d+)\]/.exec( input.name || '' );
		return m ? m[ 1 ] : null;
	}

	/**
	 * 指定した name の hidden 値を返す（前方一致で探す）。
	 *
	 * @param {HTMLFormElement} form   フォーム.
	 * @param {string}          prefix name の前方一致文字列.
	 * @return {string}
	 */
	function hiddenValue( form, prefix ) {
		var el = form.querySelector( '[name^="' + prefix + '"]' );
		return el ? el.value : '';
	}

	function init() {
		var table = document.querySelector( '#cart_table' );
		if ( ! table ) {
			return;
		}

		var inputs = table.querySelectorAll( 'tbody input.quantity' );
		if ( ! inputs.length ) {
			return;
		}

		var upButton = document.querySelector( 'input[name="upButton"]' );
		if ( ! upButton ) {
			// 更新ボタンが無い場合（use_js が無効な構成等）は何もしない。
			return;
		}

		var form = upButton.form;
		var timer = null;
		var submitting = false;

		var submitUpdate = function () {
			if ( submitting ) {
				return;
			}
			submitting = true;

			try {
				sessionStorage.setItem( SCROLL_KEY, String( window.pageYOffset ) );
			} catch ( e ) {
				// プライベートブラウジング等で sessionStorage が使えない場合は
				// スクロール位置の復元を諦めるだけで、更新自体は続行する。
			}

			table.classList.add( 'is-updating' );

			/*
			 * click() で送信するのは、更新ボタンの onclick に設定された
			 * Welcart 自身のバリデーション（uscesCart.upCart()）を通すため。
			 * requestSubmit() や form.submit() ではこの検証を飛ばしてしまう。
			 * 検証で false が返った場合は Welcart が alert を出して送信されないので、
			 * 送信中フラグを戻して操作を続けられるようにする。
			 */
			upButton.click();

			window.setTimeout( function () {
				if ( ! document.hidden ) {
					submitting = false;
					table.classList.remove( 'is-updating' );
				}
			}, 3000 );
		};

		var scheduleUpdate = function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( submitUpdate, DEBOUNCE_MS );
		};

		Array.prototype.forEach.call( inputs, function ( input ) {
			var index = rowIndexOf( input );
			if ( null === index ) {
				return;
			}

			var max = resolveMax(
				hiddenValue( form, 'itemRestriction[' + index + ']' ),
				hiddenValue( form, 'itemOrderAcceptable[' + index + ']' ),
				hiddenValue( form, 'zaikonum[' + index + ']' )
			);

			// 数量0は Welcart のバリデーションで弾かれる（削除はDELETEボタン）。
			var min = 1;

			input.setAttribute( 'inputmode', 'numeric' );
			input.setAttribute( 'aria-label', '数量' );

			var wrap = document.createElement( 'div' );
			wrap.className = 'wcd-stepper';

			var makeButton = function ( label, text ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'wcd-stepper__button';
				b.textContent = text;
				b.setAttribute( 'aria-label', label );
				return b;
			};

			var minus = makeButton( '数量を1つ減らす', '−' );
			var plus = makeButton( '数量を1つ増やす', '＋' );

			input.parentNode.insertBefore( wrap, input );
			wrap.appendChild( minus );
			wrap.appendChild( input );
			wrap.appendChild( plus );

			var current = function () {
				var n = parseInt( input.value, 10 );
				return isNaN( n ) ? min : n;
			};

			var syncDisabled = function () {
				var n = current();
				minus.disabled = n <= min;
				plus.disabled = n >= max;
			};

			var setValue = function ( n, schedule ) {
				var clamped = Math.min( Math.max( n, min ), max );
				var changed = String( clamped ) !== input.value;
				input.value = String( clamped );
				syncDisabled();
				if ( schedule && changed ) {
					scheduleUpdate();
				}
			};

			minus.addEventListener( 'click', function () {
				setValue( current() - 1, true );
			} );

			plus.addEventListener( 'click', function () {
				setValue( current() + 1, true );
			} );

			// 手入力にも追従する。範囲外の値は確定時（change）に丸める。
			input.addEventListener( 'input', syncDisabled );
			input.addEventListener( 'change', function () {
				setValue( current(), true );
			} );

			syncDisabled();
		} );

		// 自動更新が有効になったので、案内文と更新ボタンは不要になる。
		var upbutton = document.querySelector( '.upbutton' );
		if ( upbutton ) {
			upbutton.classList.add( 'wcd-upbutton--auto' );
		}

		// 更新による再読み込みの前後でスクロール位置を保つ。
		try {
			var saved = sessionStorage.getItem( SCROLL_KEY );
			if ( null !== saved ) {
				sessionStorage.removeItem( SCROLL_KEY );
				window.scrollTo( 0, parseInt( saved, 10 ) || 0 );
			}
		} catch ( e ) {
			// 復元できなくても機能上の問題はない。
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
