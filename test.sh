#!/usr/bin/env bash

# Ensure that we are in the correct directory.
cd /var/www/html

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
# Make sure that the log folder is writable for www-data.
sudo -u root chown www-data:wodby /mnt/files/log
# Download the test runner
curl -L -o testrunner https://github.com/Pronovix/testrunner/releases/download/v0.4/testrunner-linux-amd64
chmod +x ./testrunner
# Do not exit if any phpunit tests fail, we still want to see the performance
# information.
sudo -u root -E sudo -u www-data -E ./testrunner -verbose -threads=$THREADS -root=./modules/contrib/apigee_edge/tests -command="./vendor/bin/phpunit -c core -v --debug"
