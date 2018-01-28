FROM wodby/drupal-php:7.1

ARG DRUPAL_CORE="8.4"
ENV DRUPAL_CORE=${DRUPAL_CORE}
ARG DEPENDENCIES=""
ENV DEPENDENCIES=${DEPENDENCIES}

# We are using drupal-composer/drupal-project instead of drupal/drupal because we would like to update all
# libraries, including Drupal, to the latest version when doing "highest" testing.
RUN composer create-project drupal/drupal:^$DRUPAL_CORE /var/www/html --no-interaction

RUN composer update -o --with-dependencies $DEPENDENCIES

COPY --chown=www-data:www-data .  /opt/drupal-module
