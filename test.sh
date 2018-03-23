#!/usr/bin/env bash

set -e

SITE_ROOT="/var/www/html/web"

# We mounted the cache/files folder from the host so we have to fix permissions
# on the parent cache folder because it did not exist before.
sudo -u root sh -c "chown -R wodby:wodby /home/wodby/.composer/cache"

# Ensure that we are in the correct directory.
mkdir -p ${SITE_ROOT} && cd ${SITE_ROOT}

COMPOSER_OPTIONS="$SITE_ROOT --no-interaction --prefer-dist"

# We are using drupal-composer/drupal-project instead of drupal/drupal because we would like to update all
# libraries, including Drupal, to the latest version when doing "highest" testing.
if [[ "$DEPENDENCIES" = --prefer-lowest ]]; then \
  composer create-project drupal/drupal:${DRUPAL_CORE} ${COMPOSER_OPTIONS}; else \
  composer create-project drupal/drupal:^${DRUPAL_CORE} ${COMPOSER_OPTIONS} && composer update -o --with-dependencies; \
  fi

# Based on https://www.drupal.org/node/244924.
# Also fix permissions on directory and .htaccess file.
sudo -u root sh -c "chown -R wodby:www-data . \
    && find . -type d -exec chmod 6750 '{}' \; \
    && find . -type f -exec chmod 0640 '{}' \; \
    && chmod 755 . \
    && chmod 644 .htaccess"

sudo -u root sh -c "mkdir -p $SITE_ROOT/sites/default/files \
    && chown -R wodby:www-data $SITE_ROOT/sites/default/files \
    && chmod 6770 $SITE_ROOT/sites/default/files"

## Pre-create simpletest directory.
sudo -u root mkdir -p ${SITE_ROOT}/sites/simpletest

# Make sure that the log folder is writable for both www-data and wodby users.
# Also create a dedicated folder for PHPUnit outputs.
sudo -u root sh -c "chown -R www-data:wodby /mnt/files/log \
 && chmod -R 6750 /mnt/files/log \
 && mkdir -p /mnt/files/log/simpletest/browser_output \
 && chown -R www-data:wodby /mnt/files/log/simpletest \
 && chmod -R 6750 /mnt/files/log/simpletest"

# Change location of the browser_output folder, because it seems even if
# BROWSERTEST_OUTPUT_DIRECTORY is set the html output is printed out to
# https://github.com/drupal/core/blob/8.5.0/tests/Drupal/Tests/BrowserTestBase.php#L1086
sudo -u root ln -s /mnt/files/log/simpletest/browser_output ${SITE_ROOT}/sites/simpletest/browser_output

# Fix permissions on on simpletest and its subfolders.
sudo -u root sh -c "chown -R www-data:wodby $SITE_ROOT/sites/simpletest \
    && chmod -R 6750 $SITE_ROOT/sites/simpletest"

# This library has to be updated to the latest version, because the lowest installed 2.0.4 is in conflict with
# one of the Apigee PHP SDK's required library's (symfony/property-info:^3.2) minimum requirement.
if [[ "$DEPENDENCIES" = --prefer-lowest ]]; then composer require phpdocumentor/reflection-docblock:3.1.0; fi;
# Do not allow to install the latest PHPUnit version on lowest testing.
# We have to install PHPUnit >= 6.5 instead of >=6.1 here until this
# Drupal core issue has not been fixed:
# https://www.drupal.org/project/drupal/issues/2947888
if [[ "$DEPENDENCIES" = --prefer-lowest ]]; then jq '. + { "conflict": { "phpunit/phpunit": ">6.5.0" }}' composer.json > tmp.json && mv tmp.json composer.json; fi;
composer drupal-phpunit-upgrade
composer config repositories.library path /opt/drupal-module
composer require ${DEPENDENCIES} "drupal/${DRUPAL_MODULE_NAME}"
# Install this to get more detailed output from PHPUnit.
composer require ${DEPENDENCIES} limedeck/phpunit-detailed-printer:^3.2.0
composer show
# Download the test runner
curl -L -o /var/www/html/testrunner https://github.com/Pronovix/testrunner/releases/download/v0.2/testrunner-linux-amd64
chmod +x /var/www/html/testrunner
# Do not exit if any phpunit tests fail, we still want to see the performance
# information.
set +e
sudo -u root -E sudo -u www-data -E /var/www/html/testrunner -threads=$THREADS -root=${SITE_ROOT}/modules/contrib/apigee_edge/tests -command="$SITE_ROOT/vendor/bin/phpunit -c $SITE_ROOT/core -v --debug --printer \Drupal\Tests\Listeners\HtmlOutputPrinter"
