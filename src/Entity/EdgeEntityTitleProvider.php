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

use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides default page titles for Apigee Edge entities.
 */
class EdgeEntityTitleProvider extends EntityController {

  /**
   * {@inheritdoc}
   */
  public function title(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      return $this->t('@label @entity_type', [
        '@label' => $entity->label(),
        '@entity_type' => mb_strtolower($this->entityTypeManager->getDefinition($entity->getEntityTypeId())->getSingularLabel()),
      ]);
    }
    return parent::title($route_match, $_entity);
  }

  /**
   * {@inheritdoc}
   */
  public function editTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      return $this->t('Edit %label @entity_type', [
        '%label' => $entity->label(),
        '@entity_type' => mb_strtolower($this->entityTypeManager->getDefinition($entity->getEntityTypeId())->getSingularLabel()),
      ]);
    }
    return parent::editTitle($route_match, $_entity);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      // TODO Investigate why entity delete forms (confirm forms) does not
      // display this as a page title. This issue also existed before.
      return $this->t('Delete %label @entity_type', [
        '%label' => $entity->label(),
        '@entity_type' => mb_strtolower($this->entityTypeManager->getDefinition($entity->getEntityTypeId())->getSingularLabel()),
      ]);
    }
    return parent::deleteTitle($route_match, $_entity);
  }

}
