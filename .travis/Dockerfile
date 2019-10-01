ARG PHP_IMAGE="wodby/drupal-php:7.1-dev-4.5.0"

FROM ${PHP_IMAGE}

COPY --chown=wodby:www-data . /opt/drupal-module

USER wodby

RUN composer global require --no-suggest dealerdirect/phpcodesniffer-composer-installer \
    drupal/coder
