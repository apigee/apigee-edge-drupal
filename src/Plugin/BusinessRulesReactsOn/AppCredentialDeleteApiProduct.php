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

namespace Drupal\apigee_edge\Plugin\BusinessRulesReactsOn;

use Drupal\business_rules\Plugin\BusinessRulesReactsOnPlugin;

/**
 * Class AppCredentialDeleteApiProduct.
 *
 * @package Drupal\apigee_edge\Plugin\BusinessRulesReactsOn
 *
 * @BusinessRulesReactsOn(
 *   id = "apigee_edge_app_credential_delete_api_product",
 *   label = @Translation("Delete API product from an app credential"),
 *   description = @Translation("Reacts after a an API product have been removed from an app credential."),
 *   group = @Translation("Apigee"),
 *   eventName = "\Drupal\apigee_edge\Event\AppCredentialDeleteApiProductEvent::EVENT_NAME",
 *   hasTargetEntity = FALSE,
 *   hasTargetBundle = FALSE,
 *   priority = 1000,
 * )
 */
class AppCredentialDeleteApiProduct extends BusinessRulesReactsOnPlugin {

}
