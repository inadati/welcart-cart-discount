# 検証環境用の WordPress イメージ。
# Welcart（usc-e-shop）と、動作確認用テーマ（welcart-shop-theme）を同梱する。
#
# 本プラグインの動作確認には Welcart 本体が必要だが、毎回 wordpress.org から
# 手動でダウンロード・インストールするのは再現の手間になるため、
# イメージのビルド時に取得しておく。テーマも同様に同梱して、
# クローン直後から実際の購買導線に近い見た目で確認できるようにする。
#
# 配置先を /var/www/html ではなく /usr/src/wordpress にしているのは、
# 公式 wordpress イメージの docker-entrypoint.sh が初回起動時に
# /usr/src/wordpress を /var/www/html へ tar で展開する作りになっているため。
# /var/www/html は名前付きボリュームでマスクされるので、そちらに置いても残らない。
# entrypoint は wp-content/*/*/（プラグイン・テーマ）を「差し替え可能な内容」として
# 扱い、展開先に既に存在する場合はスキップするため、ボリュームを残したまま
# 再起動しても既存の Welcart / テーマを上書きしない。
#
# 逆に言うと、テーマを編集して再ビルドしても既存ボリュームには反映されない。
# テーマを編集しながら確認する場合は docker/compose.dev.yml を重ねて
# bind mount する（README「テーマを編集しながら確認する場合」参照）。

ARG WORDPRESS_TAG=6.6-php8.2-apache
FROM wordpress:${WORDPRESS_TAG}

# README に記載した検証済みバージョンに固定する。
# 「動くはず」ではなく「このバージョンで動作を確認した」状態を再現できるようにするため、
# latest ではなくバージョン指定の zip を取得する。
ARG WELCART_VERSION=2.12.1

RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends ca-certificates curl unzip; \
	rm -rf /var/lib/apt/lists/*; \
	curl -fsSL -o /tmp/usc-e-shop.zip \
		"https://downloads.wordpress.org/plugin/usc-e-shop.${WELCART_VERSION}.zip"; \
	unzip -q /tmp/usc-e-shop.zip -d /usr/src/wordpress/wp-content/plugins/; \
	rm /tmp/usc-e-shop.zip; \
	test -f /usr/src/wordpress/wp-content/plugins/usc-e-shop/usc-e-shop.php; \
	chown -R www-data:www-data /usr/src/wordpress/wp-content/plugins/usc-e-shop

# 動作確認用テーマ。これは提出物ではなく検証環境の一部である
# （本プラグインはテーマに依存せず、どのテーマでも動作する）。
COPY --chown=www-data:www-data theme/welcart-shop-theme \
	/usr/src/wordpress/wp-content/themes/welcart-shop-theme
