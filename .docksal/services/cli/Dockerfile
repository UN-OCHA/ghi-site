ARG DOCKSAL_CLI_IMAGE=docksal/cli:php8.4-3.12

FROM composer:2.11.1 AS composer

FROM ${DOCKSAL_CLI_IMAGE}

COPY --from=composer /usr/bin/composer /usr/local/bin/composer2

RUN composer2 --version
