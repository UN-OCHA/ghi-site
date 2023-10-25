# Build the code.
FROM public.ecr.aws/unocha/php-k8s:8.2-stable as builder

ARG  BRANCH_ENVIRONMENT
ENV  NODE_ENV=$BRANCH_ENVIRONMENT

COPY . /srv/www
WORKDIR /srv/www

# Clean up previous composer installation and run new one.
RUN rm -rf ./vendor && \
    # Update composer to avoid issues with missing drupal files.
    # @see https://github.com/drupal-composer/drupal-project/issues/282
    composer self-update 2.5.5 && \
    COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --no-interaction --prefer-dist

# Copy settings to default site location.
RUN mkdir -m 0775 -p html/sites/default && \
    cp -a docker/settings.php docker/services.yml docker/memcache.services.yml html/sites/default

################################################################################

# Generate the image.
FROM public.ecr.aws/unocha/php-k8s:8.2-stable

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
COPY --from=builder /srv/www/patches /srv/www/patches
COPY --from=builder /srv/www/scripts /srv/www/scripts
COPY --from=builder /srv/www/docker/etc/nginx/apps/drupal/fastcgi_drupal.conf /etc/nginx/apps/drupal/fastcgi_drupal.conf
COPY --from=builder /srv/www/docker/etc/nginx/apps/drupal/drupal.conf /etc/nginx/apps/drupal/drupal.conf
COPY --from=builder /srv/www/docker/etc/nginx/custom /etc/nginx/custom/
COPY --from=builder /srv/www/docker/etc/nginx/sites-enabled/02_mapbox_proxy_cache.conf /etc/nginx/sites-enabled/02_mapbox_proxy_cache.conf
COPY --from=builder /srv/www/docker/99-elastic-apm-custom.ini /tmp/99-elastic-apm-custom.ini

RUN  curl -L -o /tmp/apm-agent-php_all.apk https://github.com/elastic/apm-agent-php/releases/download/v1.10.0/apm-agent-php_1.10.0_all.apk \
     apk add --allow-untrusted /tmp/apm-agent-php_all.apk && \
     rm -f /tmp/apm-agent-php_all.apk && \
     mv -f /tmp/99-elastic-apm-custom.ini /etc/php82/conf.d/99-elastic-apm-custom.ini
