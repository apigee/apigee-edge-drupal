ARG PHP_IMAGE="wodby/drupal-php:7.1-dev-4.4.1"

FROM ${PHP_IMAGE}

COPY --chown=wodby:www-data . /opt/drupal-module

USER wodby
