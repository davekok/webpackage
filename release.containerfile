FROM composer AS composer
COPY composer.* /app
RUN composer install --ignore-platform-reqs --no-dev

FROM php:8.1.0RC6
COPY --from=composer /app/vendor /opt/wpk/vendor
COPY bin /opt/wpk/bin
COPY src /opt/wpk/src
WORKDIR /app
ENTRYPOINT ["/usr/local/bin/php", "/opt/wpk/bin/wpk"]
