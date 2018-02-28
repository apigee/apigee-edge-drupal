ARG PHP_VERSION="7.1"
ARG PHP_IMAGE_VERSION="-4.0.2"

FROM wodby/drupal-php:${PHP_VERSION}${PHP_IMAGE_VERSION}

USER root

# For manipulating JSON files if necessary.
RUN apk add --update jq

USER wodby

ARG DRUPAL_CORE="8.5"
ENV DRUPAL_CORE=${DRUPAL_CORE}
ARG DEPENDENCIES=""
ENV DEPENDENCIES=${DEPENDENCIES}

ENV COMPOSER_OPTIONS="/var/www/html --no-interaction"

# We are using drupal-composer/drupal-project instead of drupal/drupal because we would like to update all
# libraries, including Drupal, to the latest version when doing "highest" testing.
RUN if [[ "$DEPENDENCIES" = --prefer-lowest ]]; then \
  composer create-project drupal/drupal:$DRUPAL_CORE $COMPOSER_OPTIONS; else \
  composer create-project drupal/drupal:^$DRUPAL_CORE $COMPOSER_OPTIONS && composer update -o --with-dependencies; \
  fi

COPY --chown=www-data:www-data . /opt/drupal-module
