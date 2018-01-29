#!/usr/bin/env bash

set -e

# This library has to be updated to the latest version, because the lowest installed 2.0.4 is in conflict with
# one of the Apigee PHP SDK's required library's (symfony/property-info:^3.2) minimum requirement.
# Also require ^3.1.0 from this library, because earlier version's minimum requirement from phpdocumentor/type-resolver
# is 0.1.5, which does not have the fix for this problem: https://github.com/phpDocumentor/TypeResolver/pull/16.
# We have to update Drush too, because 9.0.0-beta4 installed by Drupal project only allows
# phpdocumentor/reflection-docblock < 3.0.
# apigee/edge dev-2.x-dev conflicts with guzzlehttp/psr7[1.3.1].
composer require phpspec/prophecy:^1.6.1 phpdocumentor/reflection-docblock:^3.1.0 guzzlehttp/psr7:^1.4.1
# After we have upgraded the dependencies to the highest possible then we can try to downgrade them to the
# lowest possible. Without the first upgrade this does not work.
composer require ${DEPENDENCIES} phpspec/prophecy:^1.6.1 phpdocumentor/reflection-docblock:^3.1.0 guzzlehttp/psr7:^1.4.1
composer config repositories.library path /opt/drupal-module
composer require ${DEPENDENCIES} "drupal/${DRUPAL_MODULE_NAME}"
composer show
php vendor/bin/phpunit -c core --group apigee_edge -v --debug
