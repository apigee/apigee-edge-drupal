#!/bin/bash -ex

# Runs CodeSniffer checks on a Drupal module.

if [ ! -f dependencies_updated ]
then
  ./update-dependencies.sh $1
fi

# Install dependencies and configure phpcs
vendor/bin/phpcs --config-set installed_paths vendor/drupal/coder/coder_sniffer

# Check coding standards
vendor/bin/phpcs -p -s -n --colors --standard=modules/apigee_edge/phpcs.xml.dist --report=junit --report-junit=artifacts/phpcs/phpcs.xml modules/$1
