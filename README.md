# Apigee Edge Drupal module

The Apigee Edge module enables you to integrate Drupal 9 with Apigee. 

* Users that register or are added to the Drupal site will be added as developers in Apigee.
* Click on "Apps" in user menu to get keys for the APIs exposed in Apigee.

This module includes the following submodules:
* __API product RBAC:__ enables administrators to configure access permissions to API products.
* __Debug:__ enables administrators to configure and manage Apigee debug logs.
* __Teams:__ enables developers to be organized into teams.

For more details read the [Apigee Edge module documentation](https://www.drupal.org/docs/contributed-modules/apigee-edge).

## Support for Apigee Hybrid Cloud: Beta Release

Support for [Apigee hybrid API](https://docs.apigee.com/hybrid/reference-overview) is now considered production ready.
If you run into any problems, add an issue to our [GitHub issue queue](https://github.com/apigee/apigee-edge-drupal/issues).
Please note that Team APIs and Monetization APIs are not currently supported on Apigee hybrid.

## Requirements

* The Apigee Edge module requires **Drupal 8.7.x** or higher and PHP 7.1 or higher, though Drupal 9.x is recommended due to [Drupal 8's EOL timeline](https://www.drupal.org/psa-2021-2021-06-29).
* Drupal's minimum requirement is phpdocumentor/reflection-docblock:2.0.4 but at least 3.0 is required by this module. If you get the error  "Your requirements could not be resolved to an installable set of packages" it may be because you are running reflection-docblock version 2. You can update `phpdocumentor/reflection-docblock` with the following command: `composer update phpdocumentor/reflection-docblock --with-dependencies`.
* **Check [composer.json](https://github.com/apigee/apigee-edge-drupal/blob/8.x-1.x/composer.json) for any required patches.** Patches prefixed with "(For testing)" are only required for running tests. Those are not necessary for using this module. Patches can be applied with the [cweagans/composer-patches](https://packagist.org/packages/cweagans/composer-patches) plugin automatically or manually. See [Applying Patches](#applying-patches) section below.
* (For developers) The locked commit from `behat/mink` library is required otherwise tests may fail. This caused by a Drupal core [bug](https://www.drupal.org/project/drupal/issues/2956279). See the related pull request for behat/mink [here](https://github.com/minkphp/Mink/pull/760).

## Installing

1. Install the Apigee Edge module using [Composer](https://getcomposer.org/).
  Composer will download the Apigee Edge module and all its dependencies.
  **Note**: Composer must be executed at the root of your Drupal installation.
  For example:
   ```
   cd /path/to/drupal/root
   composer require drupal/apigee_edge
   ```

    For more information about installing contributed modules using composer, see [the official documentation](https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies#managing-contributed).
2. Click **Extend** in the Drupal administration menu.
3. Select the **Apigee Edge** module.
4. Click **Install**.
5. Configure the [connection to your Apigee org](https://www.drupal.org/docs/contributed-modules/apigee-edge/configure-the-connection-to-apigee)

## Notes

* If you do not configure the connection between Drupal and Apigee, you will not be able to register developers on
  the site and may cause other issues with Drupal core functions. If you do not plan to configure the connection between
  Drupal and Apigee, you should uninstall the Apigee Edge module.
* When you first install the module, existing users in the Drupal site may not have a developer account in Apigee.
  You can run [developer synchronization](https://www.drupal.org/docs/contributed-modules/apigee-edge/synchronize-developers-with-apigee-edge)
  to make sure Drupal users and Apigee developers are synchronized.

## Applying Patches

The Apigee Edge module may require Drupal core or contributed module patches to be able to work properly. These patches
can be applied automatically when Apigee Edge module gets installed but for that your Drupal installation must fulfill
the following requirements:

1. [cweagans/composer-patches](https://packagist.org/packages/cweagans/composer-patches) >= 1.6.5 has to be installed.
2. ["Allowing patches to be applied from dependencies
"](https://github.com/cweagans/composer-patches/tree/1.6.5#allowing-patches-to-be-applied-from-dependencies)
has to be enabled in Drupal's composer.json.
3. Proper [patch level](https://github.com/cweagans/composer-patches/pull/101#issue-104810467)
for drupal/core has to be set in Drupal's composer.json.

You can find the currently required patches, if any, in the Apigee Edge module's [composer.json](https://github.com/apigee/apigee-edge-drupal/blob/8.x-1.x/composer.json)
and in the Apigee PHP API Client's [composer.json](https://github.com/apigee/apigee-client-php/blob/2.x/composer.json).

**If you do not have all required patches applied in your Drupal installation you may experience some problems with the
Apigee Edge module.**

## Troubleshooting

* **[File entity](https://www.drupal.org/project/file_entity) module.** If you installed the File entity module then you are going to need the latest patch from [this issue](https://www.drupal.org/project/file_entity/issues/2977747) otherwise you can run into some problems.

## Development

Development is coordinated in our [GitHub repository](https://github.com/apigee/apigee-edge-drupal). The drupal.org issue queue is disabled; we use the [GitHub issue queue](https://github.com/apigee/apigee-edge-drupal/issues) to coordinate development.

## Support

This project, which integrates Drupal with Apigee, is supported by Google.
