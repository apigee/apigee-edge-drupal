ARG PHP_VERSION="7.1"
ARG PHP_IMAGE_VERSION="-dev-4.1.1"

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

USER root

# Based on https://www.drupal.org/node/244924.
RUN chown -R wodby:www-data . \
    && find . -type d -exec chmod 6750 '{}' \; \
    && find . -type f -exec chmod 0640 '{}' \; \
    # Fix permissions on directory and .htaccess file.
    && chmod 755 . \
    && chmod 644 .htaccess

RUN mkdir -p /var/www/html/sites/default/files \
    && chown -R wodby:www-data /var/www/html/sites/default/files \
    && chmod 6770 /var/www/html/sites/default/files

# Create simpletest directory with correct permissions.
RUN mkdir -p /var/www/html/sites/simpletest \
    && chown -R www-data:wodby /var/www/html/sites/simpletest \
    && chmod 6750 /var/www/html/sites/simpletest

USER wodby

COPY --chown=wodby:www-data . /opt/drupal-module
