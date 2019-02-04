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

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Developer- and company (team) app specific title overrides and additions.
 */
class AppTitleProvider extends EdgeEntityTitleProvider {

  /**
   * Provides a title for the app analytics page.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityInterface $_entity
   *   (optional) An entity, passed in directly from the request attributes.
   *
   * @return string|null
   *   The title for the app analytics page, null if an entity was found.
   */
  public function analyticsTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      return $this->t('Analytics of %label @entity_type', ['%label' => $entity->label(), '@entity_type' => $this->entityTypeManager->getDefinition($entity->getEntityTypeId())->getLowercaseLabel()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doGetEntity(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    if ($_entity) {
      $entity = $_entity;
    }
    else {
      // Let's look up in the route object for apps only.
      foreach ($route_match->getParameters() as $parameter) {
        if ($parameter instanceof AppInterface) {
          $entity = $parameter;
          break;
        }
      }
    }
    if (isset($entity)) {
      return $this->entityRepository->getTranslationFromContext($entity);
    }
  }

}
