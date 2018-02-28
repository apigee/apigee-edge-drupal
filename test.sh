#!/usr/bin/env bash

set -e

# This library has to be updated to the latest version, because the lowest installed 2.0.4 is in conflict with
# one of the Apigee PHP SDK's required library's (symfony/property-info:^3.2) minimum requirement.
if [[ "$DEPENDENCIES" = --prefer-lowest ]]; then composer require phpdocumentor/reflection-docblock:3.1.0; fi;
# Do not allow to install the latest PHPUnit version on lowest testing.
# We have to install PHPUnit >= 6.5 instead of >=6.1 here until this
# Drupal core issue has not been fixed:
# https://www.drupal.org/project/drupal/issues/2947888
if [[ "$DEPENDENCIES" = --prefer-lowest ]]; then jq '. + { "conflict": { "phpunit/phpunit": ">6.5.0" }' composer.json > tmp.json && mv tmp.json composer.json; fi;
composer drupal-phpunit-upgrade
composer config repositories.library path /opt/drupal-module
composer require ${DEPENDENCIES} "drupal/${DRUPAL_MODULE_NAME}"
# Install this to get more detailed output from PHPUnit.
composer require ${DEPENDENCIES} limedeck/phpunit-detailed-printer:^3.2.0
composer show
php vendor/bin/phpunit -c core --group apigee_edge -v --debug --printer '\LimeDeck\Testing\Printer'
# Print API calls and performance data.
cat apigee_edge_debug.log
