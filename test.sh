#!/usr/bin/env bash

set -e

# This library has to be updated to the latest version, because the lowest installed 2.0.4 is in conflict with
# one of the Apigee PHP SDK's required library's (symfony/property-info:^3.2) minimum requirement.
if [[ "$DEPENDENCIES" = --prefer-lowest ]]; then composer require phpdocumentor/reflection-docblock:3.1.0; fi;
composer config repositories.library path /opt/drupal-module
composer require ${DEPENDENCIES} "drupal/${DRUPAL_MODULE_NAME}"
# Install this to get more detailed output from PHPUnit.
composer require limedeck/phpunit-detailed-printer:^2.0
composer show
php vendor/bin/phpunit -c core --group apigee_edge -v --debug --printer '\LimeDeck\Testing\Printer'
