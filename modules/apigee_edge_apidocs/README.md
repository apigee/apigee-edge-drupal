# API Docs for Apigee Edge

A module to render OpenAPI specs as documentation to your API developers.

## Overview

When you enable this module, it creates a new Drupal entity in your system named
"API Doc". You can add new API Docs under Structure > API Docs in the admin menu.

Once added, the API name and description for each API Doc will be displayed in the
"APIs" menu item on the site to all visitors.

The OpenAPI spec can be directly uploaded as a file, or associated to a source location
such as Apigee Edge or a URL. A "Re-import OpenAPI spec" operation is available per
API Doc to re-import the spec file when source location changes.

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

## Planned Features

- Create additional field formatters to use for rendering OpenAPI specs
- Integration with Apigee API Products
- Add visual notifications when source URL specs have changed on the API Doc admin screen

### Known issues

- none
