FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
        curl \
    && docker-php-ext-install pdo_mysql mysqli \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . /app

RUN chmod +x scripts/start.sh \
    && mkdir -p storage \
    && chmod -R 775 storage

ENV DB_ENABLED=true

EXPOSE 8080

CMD ["sh", "scripts/start.sh"]
