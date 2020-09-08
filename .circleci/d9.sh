#!/bin/bash -ex

if [ ! -f dependencies_updated ]
then
  ./update-dependencies.sh $1
fi

vendor/bin/drupal-check --no-progress --memory-limit=1000M --format=junit $1 > /var/www/html/artifacts/d9/d9check.xml

