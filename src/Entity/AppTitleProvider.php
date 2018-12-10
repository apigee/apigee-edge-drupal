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
   * {@inheritdoc}
   */
  public function analyticsTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      return $this->t('Analytics of %label @entity_type', ['%label' => $entity->label(), '@entity_type' => $this->entityTypeManager->getDefinition($entity->getEntityTypeId())->getLowercaseLabel()]);
    }
    return parent::editTitle($route_match, $_entity);
  }

}
