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

namespace Drupal\apigee_edge\ParamConverter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Param converter that loads the unchanged (non cached) entity if needed.
 *
 * EntityConverter always loads the cached version of an entity. In our case
 * this could cause inconsistency problems, especially on the entity edit forms.
 * For example, a developer could easily remove a previously added API product
 * (on Apigee Edge) from their app when modifies it on the Developer Portal.
 * This is why it is recommended to add "apigee_edge_load_unchanged_entity"
 * option to the entity routes where non-cached data should be displayed.
 *
 * @see \Drupal\Core\ParamConverter\EntityConverter
 */
class ApigeeEdgeLoadUnchangedEntity implements ParamConverterInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * DeveloperAppNameParameterConverter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    // $name is the entity type in this case.
    return $this->entityTypeManager->getStorage($name)->loadUnchanged($value);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    if (!empty($route->getOption('apigee_edge_load_unchanged_entity')) && !empty($definition['type']) && strpos($definition['type'], 'entity:') === 0) {
      $entity_type_id = substr($definition['type'], strlen('entity:'));
      if (strpos($definition['type'], '{') !== FALSE) {
        $entity_type_slug = substr($entity_type_id, 1, -1);
        return $name != $entity_type_slug && in_array($entity_type_slug, $route->compile()->getVariables(), TRUE);
      }
      return $this->entityTypeManager->hasDefinition($entity_type_id);
    }
    return FALSE;
  }

}
