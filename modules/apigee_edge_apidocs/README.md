# API Docs for Apigee Edge

A module to render OpenAPI specs as documentation to your API developers.

## Overview

When you enable this module, it creates a new Drupal entity in your system named
"API Doc". You can add new API Docs under Structure > API Docs in the admin menu.

Once added, the API name and description for each API Doc will be displayed in the
"APIs" menu item on the site to all visitors.

The OpenAPI spec by default is shown on the API Doc detail page by default.
To render the OpenAPI spec using Swagger UI:

1. Download an enable the [Swagger UI Field Formatter](https://www.drupal.org/project/swagger_ui_formatter) module
2. Go to Structure > API Doc settings > Manage display in the admin menu
3. Change "spec" field format to use the Swagger UI field formatter

The API Doc is an entity, you can configure it at Structure > API Doc settings in the admin
menu.

The "APIs" menu link is a view, you can modify it by editing the "API Documentation" view
under Structure > Views in the admin menu.

## Planned Features

- Create additional field formatters to use for rendering OpenAPI specs
- Integration with Apigee API Products
- Allow OpenAPI specs to be associated to a source location such as Apigee Edge or
  a URL
- Add visual notifications when source URL specs have changed on the API Doc admin screen
- Ability to update API Docs when source location changes

### Known issues

- none
