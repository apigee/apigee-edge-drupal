<?php

/**
 * @file
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

/**
 * @file
 * Hooks for apigee_edge_teams module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters the title of team listing page and its default menu link item.
 *
 * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
 *   The menu link/page title.
 */
function hook_apigee_edge_teams_team_listing_page_title_alter(\Drupal\Core\StringTranslation\TranslatableMarkup &$title) {
}

/**
 * Control API product entity operation access of a team (and its members).
 *
 * @param \Drupal\apigee_edge\Entity\ApiProductInterface $api_product
 *   The API Product entity for which to check access.
 * @param string $operation
 *   The entity operation. Usually one of 'view', 'update', 'create',
 *   'delete' or 'assign".
 * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
 *   The team for which to check access.
 *
 * @return \Drupal\Core\Access\AccessResultInterface
 *   The access result.
 *
 * @see \Drupal\apigee_edge_teams\TeamApiProductAccessManager
 */
function hook_apigee_edge_teams_team_api_product_access(\Drupal\apigee_edge\Entity\ApiProductInterface $api_product, string $operation, \Drupal\apigee_edge_teams\Entity\TeamInterface $team) {
  // Grant access if API product's name is prefixed wit the team's name.
  return \Drupal\Core\Access\AccessResult::allowedIf(strpos($api_product->id(), $team->id()) === 0);
}

/**
 * @} End of "addtogroup hooks".
 */
