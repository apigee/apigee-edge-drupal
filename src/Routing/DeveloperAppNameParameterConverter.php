<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Resolves "developer_app_by_name" type parameters in path.
 */
class DeveloperAppNameParameterConverter implements ParamConverterInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * DeveloperAppNameParameterConverter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (empty($defaults['user'])) {
      return NULL;
    }
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager->getStorage('user')->load($defaults['user']);
    if ($user) {
      $storedDeveloperId = $user->get('apigee_edge_developer_id')->target_id;
      if ($storedDeveloperId) {
        $ids = $this->entityTypeManager->getStorage('developer_app')->getQuery()
          ->condition('developerId', $storedDeveloperId)
          ->condition('name', $value)
          ->execute();
        if (!empty($ids)) {
          $id = reset($ids);
          $entity = $this->entityTypeManager->getStorage('developer_app')
            ->load($id);
          return $entity;
        }
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return (!empty($definition['type']) && $definition['type'] == 'developer_app_by_name');
  }

}
