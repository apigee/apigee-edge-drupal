#!/bin/bash -ex

export SIMPLETEST_BASE_URL="http://localhost"
export SIMPLETEST_DB="sqlite://localhost//tmp/drupal.sqlite"
export BROWSERTEST_OUTPUT_DIRECTORY="/var/www/html/sites/simpletest"

if [ ! -f dependencies_updated ]
then
  ./update-dependencies.sh $1
fi

robo override:phpunit-config $1

timeout 60m sudo -E -u www-data robo test:coverage $1 /tmp/artifacts || true
tar czf artifacts/coverage.tar.gz -C artifacts coverage-html coverage-xml
