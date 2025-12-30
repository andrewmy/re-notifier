FROM php:8.5.1-cli-alpine

LABEL org.opencontainers.image.source = "https://github.com/andrewmy/re-notifier"

RUN apk add --no-cache \
        sqlite-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
    && rm -rf /var/cache/apk/*

ARG TARGETARCH
ARG SUPERCRONIC_VERSION=v0.2.41
RUN wget -q "https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-${TARGETARCH}" \
        -O /usr/local/bin/supercronic \
    && chmod +x /usr/local/bin/supercronic

RUN addgroup -g 1000 app \
    && adduser -u 1000 -G app -s /bin/sh -D app

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# composer files first for better layer caching
COPY --chown=app:app composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --optimize-autoloader --no-cache

COPY --chown=app:app . .

RUN rm -f /usr/bin/composer

RUN mkdir -p /app/var && chown app:app /app/var

USER app

CMD ["supercronic", "/app/crontab"]
