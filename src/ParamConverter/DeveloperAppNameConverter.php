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

use Drupal\apigee_edge\Exception\DeveloperDoesNotExistException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Resolves "developer_app_by_name" type parameters in routes.
 *
 * @see \Drupal\apigee_edge\Entity\DeveloperApp::urlRouteParameters()
 * @see \Drupal\apigee_edge\Routing\DeveloperAppByNameRouteAlterSubscriber
 */
final class DeveloperAppNameConverter implements ParamConverterInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * Constructs a DeveloperAppNameParameterConverter.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelInterface $logger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (empty($defaults['user'])) {
      return NULL;
    }
    $entity = NULL;
    /** @var \Drupal\user\UserInterface $user */
    // If {user} parameter is before the {app} in the route then
    // entity parameter converter should have already up-casted it to
    // a user object if not then let's try to up-cast it here.
    $user = is_object($defaults['user']) ? $defaults['user'] : $this->entityTypeManager->getStorage('user')->load($defaults['user']);
    if ($user) {
      $developer_id = $user->get('apigee_edge_developer_id')->value;
      if ($developer_id) {
        $app_storage = $this->entityTypeManager->getStorage('developer_app');
        $app_ids = $app_storage->getQuery()
          ->condition('developerId', $developer_id)
          ->condition('name', $value)
          ->execute();
        if (!empty($app_ids)) {
          $app_id = reset($app_ids);
          // Load the entity directly from Apigee Edge if needed.
          // @see \Drupal\apigee_edge\ParamConverter\ApigeeEdgeLoadUnchangedEntity
          if (!empty($defaults['_route_object']->getOption('apigee_edge_load_unchanged_entity'))) {
            $entity = $app_storage->loadUnchanged($app_id);
          }
          else {
            $entity = $app_storage->load($app_id);
          }
        }

        if ($entity === NULL) {
          // App may have been deleted on Apigee Edge, that is a smaller
          // problem.
          $this->logger->error('%class: Unable to load developer app with %name name owned by %email.', [
            '%class' => get_called_class(),
            '%name' => $value,
            '%email' => $user->getEmail(),
          ]);
        }
      }
      else {
        // Developer does not exists (anymore) on Apigee Edge however it seems
        // it has existed before because someone knows the URL of the view
        // app page of one of its app.
        $this->logger->critical('%class: Unable to find developer id for %user user.', ['%class' => get_called_class(), '%user' => $user->getDisplayName()]);
        throw new DeveloperDoesNotExistException($user->getEmail());
      }
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return (!empty($definition['type']) && $definition['type'] == 'developer_app_by_name');
  }

}
