# Build the code.
FROM public.ecr.aws/unocha/php-k8s:8.3-stable AS builder

ARG  BRANCH_ENVIRONMENT
ENV  NODE_ENV=$BRANCH_ENVIRONMENT

COPY . /srv/www
WORKDIR /srv/www

# Clean up previous composer installation and run new one.
RUN rm -rf ./vendor && \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    composer install --no-dev --no-interaction --prefer-dist

# Copy settings to default site location.
RUN mkdir -m 0775 -p html/sites/default && \
    cp -a docker/settings.php docker/services.yml docker/memcache.services.yml html/sites/default

################################################################################

# Generate the image.
FROM public.ecr.aws/unocha/php-k8s:8.3-stable

ARG VCS_REF
ARG VCS_URL
ARG BUILD_DATE
ARG GITHUB_ACTOR
ARG GITHUB_REPOSITORY
ARG GITHUB_SHA
ARG GITHUB_REF

ENV  NGINX_SERVERNAME=ghi.unocha.org \
     PHP_ENVIRONMENT=production \
     PHP_MEMORY_LIMIT=256M \
     PHP_MAX_CHILDREN=16 \
     GIT_BLAME=$GITHUB_ACTOR \
     GIT_REPO=$GITHUB_REPOSITORY \
     GIT_SHA=$GITHUB_SHA \
     GIT_REF=$GITHUB_REF \
     PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/srv/www/vendor/bin

LABEL info.humanitarianresponse.build.date=$BUILD_DATE \
      info.humanitarianresponse.build.vcs-url=$VCS_URL \
      info.humanitarianresponse.build.vcs-ref=$VCS_REF

RUN mkdir -p /etc/nginx/custom && \
    # Permit nginx access to the MAPBX_SECRET env variable.
    sed -i 's/env NGINX_OVERRIDE_PROTOCOL;/env NGINX_OVERRIDE_PROTOCOL;\n\n## Mapbox access token.\nenv MAPBOX_TOKEN;/' /etc/nginx/nginx.conf

COPY --from=builder /srv/www/config /srv/www/config/
COPY --from=builder /srv/www/config_dev /srv/www/config_dev/
COPY --from=builder /srv/www/html /srv/www/html/
COPY --from=builder /srv/www/vendor /srv/www/vendor/
COPY --from=builder /srv/www/composer.json /srv/www/composer.json
COPY --from=builder /srv/www/composer.patches.json /srv/www/composer.patches.json
COPY --from=builder /srv/www/composer.lock /srv/www/composer.lock
COPY --from=builder /srv/www/PATCHES /srv/www/PATCHES
COPY --from=builder /srv/www/scripts /srv/www/scripts
COPY --from=builder /srv/www/docker/etc/nginx/ratelimit.conf.template /etc/nginx/ratelimit.conf.template
COPY --from=builder /srv/www/docker/etc/nginx/custom /etc/nginx/custom/
COPY --from=builder /srv/www/docker/etc/nginx/sites-enabled/02_mapbox_proxy_cache.conf /etc/nginx/sites-enabled/02_mapbox_proxy_cache.conf
COPY --from=builder /srv/www/docker/99-elastic-apm-custom.ini /tmp/99-elastic-apm-custom.ini

RUN APM_ARCH=$(uname -m | sed 's/x86_64/amd64/') && \
    curl -L -o /tmp/apm-agent-php_1.14.1_${APM_ARCH}.apk https://github.com/elastic/apm-agent-php/releases/download/v1.14.1/apm-agent-php_1.14.1_${APM_ARCH}.apk && \
    apk add --allow-untrusted /tmp/apm-agent-php_1.14.1_${APM_ARCH}.apk && \
    rm -f /tmp/apm-agent-php_1.14.1_${APM_ARCH}.apk && \
    mv -f /tmp/99-elastic-apm-custom.ini /etc/php83/conf.d/99-elastic-apm-custom.ini
