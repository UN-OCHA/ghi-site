#!/usr/bin/env bash

# This is the equivalent of the tests in `.github/workflows/run-tests.yml` that can be run locally.

# Build local image.
echo "Build local image."
make

# Get the containers up and run composer install.
echo "Setup environment."
docker-compose -f .github/tests/docker-compose.yml up -d
sleep 10
docker-compose -f .github/tests/docker-compose.yml ps
docker-compose -f .github/tests/docker-compose.yml exec -w /srv/www -T drupal composer install

# Install the common design subtheme.
echo "Make sure the common design subtheme is installed"
docker-compose -f .github/tests/docker-compose.yml exec -w /srv/www -T drupal /usr/bin/composer run sub-theme

# Check coding standards.
echo "Check coding standards."
docker-compose -f .github/tests/docker-compose.yml exec -u appuser -w /srv/www -T drupal phpcs -p --report=full --standard=phpcs.xml ./html/modules/custom ./html/themes/custom

# Install the site with the existing config.
echo "Install the site with the existing config."
docker-compose -f .github/tests/docker-compose.yml exec -T drupal drush -y si --existing-config minimal install_configure_form.enable_update_status_emails=NULL

# Run tests.
echo "Run all tests and generate coverage report."
docker-compose -f .github/tests/docker-compose.yml exec -T drupal drush -y en dblog
docker-compose -f .github/tests/docker-compose.yml exec -T drupal drush -y cset social_auth_hid.settings auto_redirect false --input-format=yaml
docker-compose -f .github/tests/docker-compose.yml exec -T drupal chmod -R 777 /srv/www/html/sites/default/files /srv/www/html/sites/default/private
docker-compose -f .github/tests/docker-compose.yml exec -T drupal mkdir -p /srv/www/html/build/logs
docker-compose -f .github/tests/docker-compose.yml exec -T drupal chmod -R 777 /srv/www/html/build/logs
docker-compose -f .github/tests/docker-compose.yml exec -T drupal mkdir -p /srv/www/html/sites/simpletest/browser_output
docker-compose -f .github/tests/docker-compose.yml exec -T drupal chmod -R 777 /srv/www/html/sites/simpletest/browser_output
docker-compose -f .github/tests/docker-compose.yml exec -T -u appuser -w /srv/www -e XDEBUG_MODE=coverage -e BROWSERTEST_OUTPUT_DIRECTORY=/tmp -e BROWSERTEST_OUTPUT_BASE_URL=http://127.0.0.1:8081 -e DTT_BASE_URL=http://127.0.0.1 -e SIMPLETEST_BASE_URL=http://127.0.0.1 -e SIMPLETEST_DB=mysql://ghi:ghi@mysql/ghi drupal ./vendor/bin/phpunit --coverage-clover /srv/www/html/build/logs/clover.xml --debug -c /srv/www

# Show logs.
echo "Show logs."
docker-compose -f .github/tests/docker-compose.yml exec -T drupal drush watchdog:show

# # Remove the image.
echo "Remove the test image"
docker-compose -f .github/tests/docker-compose.yml down -v
