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

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppController;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for developer apps.
 */
class DeveloperAppStorage extends FieldableEdgeEntityStorageBase implements DeveloperAppStorageInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Database\Connection*/
  protected $database;

  /**
   * DeveloperAppStorage constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(ContainerInterface $container, EntityTypeInterface $entity_type, LoggerInterface $logger) {
    $this->entityTypeManager = $container->get('entity_type.manager');
    $this->database = $container->get('database');
    parent::__construct($container, $entity_type, $logger);
  }

  /**
   * Gets a DeveloperAppController instance.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK Connector service.
   *
   * @return \Apigee\Edge\Controller\EntityCrudOperationsControllerInterface
   *   The DeveloperAppController instance.
   *
   * @method listByDeveloper
   */
  public function getController(SDKConnectorInterface $connector): EntityCrudOperationsControllerInterface {
    return new DeveloperAppController($connector->getOrganization(), $connector->getClient());
  }

  /**
   * {@inheritdoc}
   */
  public function loadByDeveloper(string $developerId): array {
    $ids = $this->getQuery()->condition('developerId', $developerId)
      ->execute();
    return $this->loadMultiple(array_values($ids));
  }

  /**
   * {@inheritdoc}
   *
   * Adds Drupal user information to loaded entities.
   */
  protected function postLoad(array &$entities) {
    $developerIds = [];
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    foreach ($entities as $entity) {
      $developerIds[] = $entity->getDeveloperId();
    }
    $developerIds = array_unique($developerIds);
    $developerId_mail_map = [];
    /** @var \Drupal\apigee_edge\Entity\Storage\DeveloperStorageInterface $developerStorage */
    $developerStorage = $this->entityTypeManager->getStorage('developer');
    foreach ($developerStorage->loadByProperties(['developerId' => $developerIds]) as $developer) {
      /** @var \Drupal\apigee_edge\Entity\Developer $developer */
      $developerId_mail_map[$developer->uuid()] = $developer->getEmail();
    }

    $query = $this->database->select('users_field_data', 'ufd');
    $query->fields('ufd', ['mail', 'uid'])
      ->condition('mail', $developerId_mail_map, 'IN');
    $mail_uid_map = $query->execute()->fetchAllKeyed();

    foreach ($entities as $entity) {
      // If developer id is not in this map it means the developer does
      // not exist in Drupal yet (developer syncing between Edge and Drupal is
      // required) or the developer id has not been stored in
      // related Drupal user yet.
      // This can be fixed with running developer sync too,
      // because it could happen that the user had been
      // created in Drupal before Edge connected was configured.
      // Although, this could be a result of a previous error
      // but there should be a log about that.
      if (isset($mail_uid_map[$developerId_mail_map[$entity->getDeveloperId()]])) {
        $entity->setOwnerId($mail_uid_map[$developerId_mail_map[$entity->getDeveloperId()]]);
      }
    }
    // Call parent post load and with that call hook_developer_app_load()
    // implementations.
    parent::postLoad($entities);
  }

}
