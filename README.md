# Apigee Edge Drupal module

A Drupal 8 module that turns a site into a developer portal for Apigee's API
management product.

## Installing

```sh
$ composer require drupal/apigee_edge
```

### Requirements

* Drupal 8's minimum requirement is `phpdocumentor/reflection-docblock:2.0.4` but
at least 3.0 is required by this module. If you get a conflict because of this
then you can update it with the following command 
`composer update phpdocumentor/reflection-docblock --with-dependencies`.
* **Please check [composer.json](composer.json) for required patches.**
Patches prefixed with "(For testing)" are only required for running tests.
Those are not necessary for using this module.
Patches can be applied with the [cweagans/composer-patches](https://packagist.org/packages/cweagans/composer-patches)
the plugin automatically or manually.
* (For testing) From `behat/mink` library the locked commit is required
otherwise tests may fail. This caused by a Drupal core [bug](https://www.drupal.org/project/drupal/issues/2956279).
Please see the related pull request for behat/mink [here](https://github.com/minkphp/Mink/pull/760). 

## Testing

To run the tests, some environment variables are needed both for the script and
the server. These variables are:
* `APIGEE_EDGE_ENDPOINT`
* `APIGEE_EDGE_ORGANIZATION`
* `APIGEE_EDGE_USERNAME`
* `APIGEE_EDGE_PASSWORD`.

You can set these environment variables multiple ways, either by defining them
with `export` or `set` in the terminal or creating a copy of the `core/phpunit.xml.dist`
file as `core/phpunit.xml` and specifying them in that.

Run the following command to execute tests of this module (note that the
location of the `phpunit` executable might be different in your case):

```sh
./vendor/bin/phpunit -c core --verbose --color --group apigee_edge
```

If you have Docker and Docker Compose installed on your system you can also run
PHPUnit tests of this module with the following commands:

```sh
$ docker-compose up --build
$ docker-compose run php sh /opt/drupal-module/docker-run-tests.sh
```

You can read more about running Drupal 8 PHPUnit tests [here](https://www.drupal.org/docs/8/phpunit/running-phpunit-tests).

## Disclaimer

This is not an officially supported Google product.
