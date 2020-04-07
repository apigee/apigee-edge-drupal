<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\apigee_edge_actions;

use Drupal\apigee_edge\Entity\FieldableEdgeEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines the apigee_edge_actions.edge_entity_type_manager service.
 */
class ApigeeActionsEntityTypeHelper implements ApigeeActionsEntityTypeHelperInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ApigeeAppEntityTypeManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypes(): array {
    return array_filter($this->entityTypeManager->getDefinitions(), function (EntityTypeInterface $entity_type) {
      return $this->isFieldableEdgeEntityType($entity_type);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function isFieldableEdgeEntityType(EntityTypeInterface $entity_type): bool {
    return $entity_type->entityClassImplements(FieldableEdgeEntityInterface::class);
  }

}
