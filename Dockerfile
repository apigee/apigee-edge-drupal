ARG PHP_IMAGE="wodby/drupal-php:7.1-dev-4.2.2"

FROM ${PHP_IMAGE}

USER root

# For manipulating JSON files if necessary.
RUN apk add --update jq

COPY --chown=wodby:www-data . /opt/drupal-module

USER wodby
