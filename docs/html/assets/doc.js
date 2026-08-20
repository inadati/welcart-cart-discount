/**
 * 提出物ドキュメントHTML版の挙動スクリプト。
 *
 * 実装する3つの拡張（すべてプログレッシブエンハンスメント）:
 *   1. 目次の現在地ハイライト（IntersectionObserver）
 *   2. 画像の拡大表示（<dialog>）
 *   3. サイドバーの開閉（狭い画面向け）
 *
 * JSが無効・非対応の環境でも、本文・画像リンク・ログの内容には
 * 素のHTMLだけで到達できる（<a href> と <details> がそれぞれ機能する）。
 * このスクリプトはあくまで閲覧体験の上乗せであり、動作しなくても
 * 内容への到達性は失われない。
 */
( function () {
	'use strict';

	/**
	 * 目次の現在地ハイライト。
	 *
	 * article.doc 内の見出し（h2[id] / h3[id]）を IntersectionObserver で
	 * 監視し、画面上部付近に来た見出しに対応する nav.toc a.toc-link に
	 * .is-current を付ける（他は外す）。IntersectionObserver 非対応環境
	 * では何もしない（エラーを出さずスキップする）。
	 */
	function initTocHighlight() {
		if ( ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		var article = document.querySelector( 'article.doc' );
		var tocLinks = document.querySelectorAll( 'nav.toc a.toc-link' );

		if ( ! article || ! tocLinks.length ) {
			return;
		}

		var headings = article.querySelectorAll( 'h2[id], h3[id]' );

		if ( ! headings.length ) {
			return;
		}

		var headingOrder = [];
		var linkById = {};

		Array.prototype.forEach.call( headings, function ( heading ) {
			headingOrder.push( heading.id );
		} );

		Array.prototype.forEach.call( tocLinks, function ( link ) {
			var href = link.getAttribute( 'href' ) || '';
			if ( 0 === href.indexOf( '#' ) ) {
				linkById[ href.slice( 1 ) ] = link;
			}
		} );

		var intersectingIds = [];
		var currentId = null;

		function sortByDocumentOrder( ids ) {
			return ids.slice().sort( function ( a, b ) {
				return headingOrder.indexOf( a ) - headingOrder.indexOf( b );
			} );
		}

		function applyCurrent() {
			if ( intersectingIds.length ) {
				// 画面上部の帯に複数の見出しが同時に入ることは通常ないが、
				// 万一入った場合は文書順で最初のものを採用する。
				currentId = intersectingIds[ 0 ];
			}
			// 帯の中に見出しが1つも無い状態（見出し間の本文を読んでいる間や、
			// 最後の見出し以降の末尾を読んでいる間）は、直前にハイライトして
			// いた見出しをそのまま維持する。

			Array.prototype.forEach.call( tocLinks, function ( link ) {
				link.classList.remove( 'is-current' );
			} );

			if ( currentId && linkById[ currentId ] ) {
				linkById[ currentId ].classList.add( 'is-current' );
			}
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					var id = entry.target.id;
					var index = intersectingIds.indexOf( id );

					if ( entry.isIntersecting ) {
						if ( -1 === index ) {
							intersectingIds.push( id );
						}
					} else if ( -1 !== index ) {
						intersectingIds.splice( index, 1 );
					}
				} );

				intersectingIds = sortByDocumentOrder( intersectingIds );
				applyCurrent();
			},
			{
				root: null,
				// 画面上部から見て、上30%の帯に見出しが入ったら「現在地」とみなす。
				rootMargin: '0px 0px -70% 0px',
				threshold: 0,
			}
		);

		Array.prototype.forEach.call( headings, function ( heading ) {
			observer.observe( heading );
		} );
	}

	/**
	 * 画像の拡大表示。
	 *
	 * a.lightbox-trigger のクリックをイベント委譲で捕捉し、#lightbox
	 * （<dialog>）の #lightbox-img に data-img の画像を差し替えて表示する。
	 * <dialog> / showModal 非対応環境では preventDefault を呼ばず、通常の
	 * リンク遷移（href の原寸画像を直接開く）に任せる。
	 */
	function initLightbox() {
		var dialog = document.getElementById( 'lightbox' );
		var dialogImg = document.getElementById( 'lightbox-img' );

		if ( ! dialog || ! dialogImg ) {
			return;
		}

		var supportsDialog = 'function' === typeof dialog.showModal;
		var closeButton = dialog.querySelector( '.lightbox-close' );

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target && event.target.closest ?
				event.target.closest( 'a.lightbox-trigger' ) :
				null;

			if ( ! trigger ) {
				return;
			}

			if ( ! supportsDialog ) {
				// dialog非対応環境では、リンクのhrefへの通常遷移に任せる。
				return;
			}

			event.preventDefault();

			var src = trigger.getAttribute( 'data-img' ) || trigger.getAttribute( 'href' );
			var triggerImg = trigger.querySelector( 'img' );
			var alt = triggerImg ? ( triggerImg.getAttribute( 'alt' ) || '' ) : '';

			dialogImg.setAttribute( 'src', src || '' );
			dialogImg.setAttribute( 'alt', alt );
			dialog.showModal();
		} );

		if ( ! supportsDialog ) {
			return;
		}

		if ( closeButton ) {
			closeButton.addEventListener( 'click', function () {
				dialog.close();
			} );
		}

		// dialog要素自身がクリックされた＝内容（画像・閉じるボタン）の外側
		// （::backdrop相当）がクリックされた場合に閉じる。内部要素をクリック
		// した場合は event.target がその要素になるため、ここでは反応しない。
		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );

		// Escキーでの close は <dialog> の標準動作（cancel イベント）に任せる。
	}

	/**
	 * サイドバーの開閉（画面幅900px未満向け）。
	 *
	 * .nav-toggle のクリックで #sidebar の .is-open を付け外しし、
	 * aria-expanded を同期する。目次リンクをクリックしたときは、
	 * 画面幅900px未満であればサイドバーを閉じる。
	 */
	function initSidebarToggle() {
		var toggleButton = document.querySelector( '.nav-toggle' );
		var sidebar = document.getElementById( 'sidebar' );

		if ( ! toggleButton || ! sidebar ) {
			return;
		}

		toggleButton.addEventListener( 'click', function () {
			var isOpen = sidebar.classList.toggle( 'is-open' );
			toggleButton.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		} );

		var narrowScreen = window.matchMedia ?
			window.matchMedia( '(max-width: 900px)' ) :
			null;

		var tocLinks = sidebar.querySelectorAll( 'nav.toc a.toc-link' );

		Array.prototype.forEach.call( tocLinks, function ( link ) {
			link.addEventListener( 'click', function () {
				if ( narrowScreen && narrowScreen.matches ) {
					sidebar.classList.remove( 'is-open' );
					toggleButton.setAttribute( 'aria-expanded', 'false' );
				}
			} );
		} );
	}

	function init() {
		// 3つの拡張は互いに独立している。1つが想定外の環境で例外を出しても、
		// 他の拡張や素のHTMLの閲覧性を道連れにしないよう、個別に保護する。
		try {
			initTocHighlight();
		} catch ( error ) {
			// 目次ハイライトが使えなくても本文の閲覧には影響しない。
		}

		try {
			initLightbox();
		} catch ( error ) {
			// 拡大表示が使えなくても、画像リンクは通常のリンクとして機能する。
		}

		try {
			initSidebarToggle();
		} catch ( error ) {
			// 開閉ボタンが使えなくても、サイドバーの内容自体は表示されている。
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
