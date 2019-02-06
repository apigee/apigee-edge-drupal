#!/usr/bin/env bash

set -e

IS_ENV_VARS_SET=1
for VAR in APIGEE_EDGE_AUTH_TYPE APIGEE_EDGE_ENDPOINT APIGEE_EDGE_ORGANIZATION APIGEE_EDGE_USERNAME APIGEE_EDGE_PASSWORD; do
    if [[ -z "${!VAR}" ]] ; then
        IS_ENV_VARS_SET=0
        echo "Incomplete configuration. The variable ${VAR} should exist and not be empty."
    fi
done

if [[ ${IS_ENV_VARS_SET} -eq 0 ]]; then
  echo "Exiting"
  exit 1
fi

# Make sure that script is standalone (it can be used even if it is not called
# by run-test.sh).
THREADS=${THREADS:-4}
MODULE_PATH=${MODULE_PATH:-"/opt/drupal-module"}
WEB_ROOT=${WEB_ROOT:-"/var/www/html/build"}
WEB_ROOT_PARENT=${WEB_ROOT_PARENT:-"/var/www/html"}
TEST_ROOT=${TEST_ROOT:-modules/custom}
TESTRUNNER=${TESTRUNNER:-"/var/www/html/testrunner"}

COMPOSER_GLOBAL_OPTIONS="--no-interaction --no-suggest -o"

# We mounted the cache/files folder from the host so we have to fix permissions
# on the parent cache folder because it did not exist before.
sudo -u root sh -c "chown -R wodby:wodby /home/wodby/.composer/cache"

cd ${MODULE_PATH}/.travis

# Install module with the highest dependencies first.
composer update ${COMPOSER_GLOBAL_OPTIONS}

# Allow to run tests with a specific Drupal core version (ex.: latest dev).
if [[ -n "${DRUPAL_CORE}" ]]; then
  composer require drupal/core:${DRUPAL_CORE} webflo/drupal-core-require-dev:${DRUPAL_CORE} ${COMPOSER_GLOBAL_OPTIONS};
fi

# Downgrade dependencies if needed.
# (This fix is necessary since PR#130 had been merged because after that lowest
# builds started to fail. Probably caused by a merge plugin issue because this
# problem could be reproduced only in this environment.)
if [[ -n "${DEPENDENCIES}" ]]; then
  composer update ${COMPOSER_GLOBAL_OPTIONS} ${DEPENDENCIES} --with-dependencies
fi

# Die if environment preparation has failed.
if [[ ! -d ${MODULE_PATH}/.travis/build ]]; then
  exit 1;
fi

# Copying Drupal to the right place.
# Symlinking is not an option because the webserver container would not be
# able to access to files.
cp -R ${MODULE_PATH}/.travis/build ${WEB_ROOT_PARENT}
cp -R ${MODULE_PATH}/.travis/vendor ${WEB_ROOT_PARENT}

# Symlink module to the contrib folder.
ln -s ${MODULE_PATH} ${WEB_ROOT}/modules/contrib/${DRUPAL_MODULE_NAME}

# Pre-create simpletest and screenshots directories...
sudo -u root -E mkdir -p ${WEB_ROOT}/sites/simpletest
sudo -u root mkdir -p /mnt/files/log/screenshots
# and some other.
# (These are required by core/phpunit.xml.dist).
sudo -u root mkdir -p ${WEB_ROOT}/profiles
sudo -u root mkdir -p ${WEB_ROOT}/themes

# Based on https://www.drupal.org/node/244924, but we had to grant read
# access to files and read + execute access to directories to "others"
# otherwise Javascript tests failed by using webdriver.
# (Error: jQuery was not found an AJAX form.)
sudo -u root -E sh -c "chown -R wodby:www-data $WEB_ROOT \
    && find $WEB_ROOT -type d -exec chmod 6755 '{}' \; \
    && find $WEB_ROOT -type f -exec chmod 0644 '{}' \;"

sudo -u root -E sh -c "mkdir -p $WEB_ROOT/sites/default/files \
    && chown -R wodby:www-data $WEB_ROOT/sites/default/files \
    && chmod 6770 $WEB_ROOT/sites/default/files"

# Make sure that the log folder is writable for both www-data and wodby users.
# Also create a dedicated folder for PHPUnit outputs.
sudo -u root sh -c "chown -R www-data:wodby /mnt/files/log \
 && chmod -R 6750 /mnt/files/log \
 && mkdir -p /mnt/files/log/simpletest/browser_output \
 && chown -R www-data:wodby /mnt/files/log/simpletest \
 && chmod -R 6750 /mnt/files/log/simpletest \
 && chown -R www-data:wodby /mnt/files/log/screenshots \
 && chmod -R 6750 /mnt/files/log/screenshots"

# Change location of the browser_output folder, because it seems even if
# BROWSERTEST_OUTPUT_DIRECTORY is set the html output is printed out to
# https://github.com/drupal/core/blob/8.5.0/tests/Drupal/Tests/BrowserTestBase.php#L1086
sudo -u root ln -s /mnt/files/log/simpletest/browser_output ${WEB_ROOT}/sites/simpletest/browser_output

# Fix permissions on on simpletest and its sub-folders.
sudo -u root sh -c "chown -R www-data:wodby $WEB_ROOT/sites/simpletest \
    && chmod -R 6750 $WEB_ROOT/sites/simpletest"

# Let's display installed dependencies and their versions.
composer show

# Downloading the test runner.
curl -s -L -o ${TESTRUNNER} https://github.com/Pronovix/testrunner/releases/download/v0.4/testrunner-linux-amd64
chmod +x ${TESTRUNNER}
