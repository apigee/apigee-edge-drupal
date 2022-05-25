<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\apigee_edge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for the developer app credentials.
 */
class DeveloperAppKeysController extends ControllerBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * DeveloperAppKeysController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns app credentials.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The app credentials.
   */
  public function developerAppKeys($user, $app): JsonResponse {
    $payload = [];
    if ($user) {
      if ($developer_id = $user->get('apigee_edge_developer_id')->value) {
        $app_storage = $this->entityTypeManager->getStorage('developer_app');
        $app_ids = $app_storage->getQuery()
          ->condition('developerId', $developer_id)
          ->condition('name', $app->getName())
          ->execute();
        if (!empty($app_ids)) {
          $app_id = reset($app_ids);
          $payload = $this->getAppKeys($app_storage->load($app_id));
        }
      }
    }
    return new JsonResponse($payload, 200, ['Cache-Control' => 'must-understand, no-store']);
  }

  /**
   * Get app credentials by app object.
   */
  protected function getAppKeys($app) {
    $keys = [];
    if ($credentials = $app->getCredentials()) {
      foreach ($credentials as $item) {
        $keys[] = [
          $item->getConsumerKey(),
          $item->getConsumerSecret(),
        ];
      }
    }
    return $keys;
  }

}
