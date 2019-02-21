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

namespace Drupal\apigee_edge\Event;

/**
 * Triggered when a new app credential has been created for an app.
 *
 * When app key has been _created_ then you can not access to API products
 * on the credential yet because are going to be assigned later.
 */
class AppCredentialCreateEvent extends AbstractAppCredentialEvent {

  /**
   * Event id.
   *
   * @var string
   */
  const EVENT_NAME = 'apigee_edge.app_credential.create';

}
