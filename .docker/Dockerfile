FROM php:7-fpm-alpine as base

LABEL Description="This is the docker image for OpenCFP, a PHP-based conference talk submission system." \
      org.label-schema.name=$IMAGE_NAME \
      org.label-schema.description="This is the docker image for OpenCFP, a PHP-based conference talk submission system." \
      org.label-schema.build-date=$BUILD_DATE \
      org.label-schema.vcs-url="https://github.com/opencfp/opencfp.git" \
      org.label-schema.vcs-ref=$VCS_REF \
      org.label-schema.vendor="OpenCFP" \
      org.label-schema.version=$VERSION \
      org.label-schema.schema-version="1.0.0"

RUN apk update && \
    apk add --no-cache \
    nginx \
    runit && \
    mkdir /tmp/nginx

RUN docker-php-ext-install pdo_mysql

ENV CFP_ENV ${CFP_ENV:-development}
ENV CFP_DB_HOST ${CFP_DB_HOST:-"127.0.0.1"}
ENV CFP_DB_PASS ${CFP_DB_PASS:-root}

FROM base as composer-deps
# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

# Copy only what's needed for composer install
COPY composer.json composer.lock ./

# Install dependencies with optimal flags
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-interaction \
    --no-progress

# Copy the rest of the application
COPY . .

# Generate optimized autoloader and remove unnecessary files
RUN composer dump-autoload --optimize --classmap-authoritative && \
    # Remove composer cache and unnecessary files
    rm -rf /root/.composer && \
    # Remove test directories and documentation from vendor
    find vendor -type d -name "tests" -o -name "Tests" -o -name "test" -o -name "Test" | xargs rm -rf && \
    find vendor -type d -name "docs" -o -name "doc" -o -name "examples" | xargs rm -rf && \
    find vendor -name "*.md" -o -name "*.txt" -o -name "*.xml" -o -name "*.yml" | xargs rm -f && \
    find vendor -name "composer.json" -o -name "composer.lock" | xargs rm -f && \
    # Remove .git directories if any
    find vendor -name ".git" -type d | xargs rm -rf

FROM base AS production

ENV CFP_ENV=production

# Copy application files
COPY --from=composer-deps --chown=www-data:www-data /app /srv/application
COPY . /srv/application

COPY .docker/conf/nginx.conf /etc/nginx/nginx.conf
COPY .docker/conf/fpm.conf /usr/local/etc/php-fpm.conf
COPY .docker/bin/runit-nginx /etc/service/nginx/run
COPY .docker/bin/runsvinit /sbin/runsvinit

RUN mkdir -p /etc/service/fpm /etc/service/nginx && \
    echo '#!/bin/sh' > /etc/service/fpm/run && \
    echo 'exec php-fpm -FR' >> /etc/service/fpm/run && \
    chmod +x /etc/service/*/run && \
    chmod +x /sbin/runsvinit && \
    mkdir -p /srv/application/cache && \
    mkdir -p /srv/application/log && \
    mkdir -p /srv/application/web/uploads && \
    mkdir -p /tmp/nginx/{body,proxy,fastcgi,uwsgi,scgi} && \
    mkdir -p /var/run && \
    mkdir -p /var/cache/nginx && \
    mkdir -p /var/log/nginx && \
    chown -R nginx:nginx /srv/application/cache /srv/application/web/uploads /srv/application/log && \
    chmod -R 755 /srv/application/cache /srv/application/web/uploads /srv/application/log && \
    chgrp -R nginx /srv/application/cache /srv/application/web/uploads /srv/application/log && \
    # Ensure nginx can write to temp and cache directories
    chown -R nginx:nginx /tmp/nginx /var/cache/nginx /var/log/nginx && \
    chmod -R 755 /tmp/nginx /var/cache/nginx /var/log/nginx

ENTRYPOINT ["/sbin/runsvinit"]
EXPOSE 80
