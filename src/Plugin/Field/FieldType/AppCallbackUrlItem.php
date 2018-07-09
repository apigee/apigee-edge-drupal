<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\UriItem;

/**
 * App callback url specific plugin implementation of a URI item.
 *
 * This field is hidden on the Field UI, because it should be used as a base
 * field only on company- and developer app entities.
 *
 * @FieldType(
 *   id = "app_callback_url",
 *   label = @Translation("App Callback URL"),
 *   description = @Translation("An entity field containing a callback url for an app."),
 *   no_ui = TRUE,
 *   default_formatter = "uri_link",
 *   default_widget = "app_callback_url",
 * )
 */
class AppCallbackUrlItem extends UriItem {

}
