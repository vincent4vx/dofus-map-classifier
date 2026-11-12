FROM php:8.4-cli-bookworm

# librsvg is used to render SVG sprites through Imagick
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip librsvg2-bin \
    && rm -rf /var/lib/apt/lists/*

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd imagick zip opcache

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

RUN echo "date.timezone=Europe/Paris" > /usr/local/etc/php/conf.d/timezone.ini \
    && echo "memory_limit=-1" > /usr/local/etc/php/conf.d/memory.ini \
    && printf "opcache.enable_cli=1\nopcache.jit=tracing\nopcache.jit_buffer_size=128M\n" > /usr/local/etc/php/conf.d/jit.ini

# The container runs with the host uid, so HOME must be writable
ENV HOME=/tmp
ENV COMPOSER_HOME=/tmp/composer

WORKDIR /app
