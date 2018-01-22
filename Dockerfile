FROM wodby/drupal-php:7.1

RUN composer global require "hirak/prestissimo:^0.3"

ARG DRUPAL_MODULE_NAME="drupal"

ARG DEPENDENCIES="highest"

# We are using drupal-composer/drupal-project instead of drupal/drupal because we would like to update all
# libraries, including Drupal, to the latest version when doing "highest" testing.
RUN composer create-project drupal-composer/drupal-project:8.x-dev /var/www/html --no-interaction

# This library has to be updated to the latest version, because the lowest installed 2.0.4 is in conflict with
# one of the Apigee PHP SDK's required library's (symfony/property-info:^3.2) mininmum requirement.
# Also require ^3.1.0 from this library, because earlier version's minimum requirement from phpdocumentor/type-resolver
# is 0.1.5, which does not have the fix for this problem: https://github.com/phpDocumentor/TypeResolver/pull/16.
# We have to update Drush too, because 8.1.15 does not work with Drupal 8.4 and also conflicts with
# phpdocumentor/reflection-docblock too.
RUN composer require drush/drush:^9.0 && composer require phpdocumentor/reflection-docblock:^3.1.0

COPY --chown=www-data:www-data . "${WODBY_DIR_FILES}/${DRUPAL_MODULE_NAME}"

RUN composer config repositories.library path "${WODBY_DIR_FILES}/${DRUPAL_MODULE_NAME}" \
    && if [[ $DEPENDENCIES = "highest" ]]; then composer require drupal/${DRUPAL_MODULE_NAME}; composer require --prefer-lowest drupal/${DRUPAL_MODULE_NAME}; fi

RUN if [[ $DEPENDENCIES = "highest" ]]; then \
    composer update -o --with-dependencies; else \
    # Downgrade drupal-scaffold library separately, because otherwise it would cause run time errors.
    composer update drupal-composer/drupal-scaffold --prefer-lowest && composer update -o --with-dependencies --prefer-lowest; \
    fi

# Show the installed package versions for debugging purposes.
RUN composer show
