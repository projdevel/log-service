FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    librabbitmq-dev \
    && pecl install amqp \
    && docker-php-ext-enable amqp

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN groupadd -g 1000 appuser && \
    useradd -u 1000 -g appuser -m appuser

WORKDIR /var/www/html

RUN chown -R appuser:appuser /var/www/html

USER appuser

EXPOSE 9000
CMD ["php-fpm"]
