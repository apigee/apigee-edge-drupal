# API Docs for Apigee Edge

A module to render OpenAPI specs as documentation to your API developers.

## End of life

This sub-project has been deprecated in favor of the __[Apigee API Catalog](https://www.drupal.org/project/apigee_api_catalog)__
module. Uninstall `apigee_edge_apidocs` before enabling `apigee_api_catalog`. The Apigee API Catalog module has the same
planned features that were planned for Apigee Edge API Docs and is currently more complete. This module will be removed
before Apigee Edge version 8.x-1.0 is released.

## Overview

When you enable this module, it creates a new Drupal entity in your system named
"API Doc". You can add new API Docs under Structure > API Docs in the admin menu.

Once added, the API name and description for each API Doc will be displayed in the
"APIs" menu item on the site to all visitors.

The OpenAPI spec by default is shown on the API Doc detail page by default.
To render the OpenAPI spec using Swagger UI:

1. Install an enable the [Swagger UI Field Formatter](https://www.drupal.org/project/swagger_ui_formatter) module.
2. Install the Swagger UI JS library as documented [on the module page](https://www.drupal.org/project/swagger_ui_formatter).
3. Go to Structure > API Doc settings > Manage display in the admin menu.
4. Change "OpenAPI specification" field format to use the Swagger UI field formatter.

The API Doc is an entity, you can configure it at Structure > API Doc settings in the admin
menu.

The "APIs" menu link is a view, you can modify it by editing the "API Documentation" view
under Structure > Views in the admin menu.
