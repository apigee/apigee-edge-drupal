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

namespace Drupal\apigee_edge\Entity\Storage;

use Drupal\apigee_edge\Entity\Controller\AppControllerInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppControllerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppEdgeEntityControllerProxy;
use Drupal\apigee_edge\Entity\Controller\EdgeEntityControllerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Egulias\EmailValidator\EmailValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity storage class for Developer app entities.
 */
class DeveloperAppStorage extends AppStorage implements DeveloperAppStorageInterface {

  /**
   * The app entity controller for unified CRUDL operations.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\EdgeEntityControllerInterface
   */
  private $appEntityController;

  /**
   * The developer app controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppControllerFactoryInterface
   */
  private $developerAppControllerFactory;

  /**
   * The email validator service.
   *
   * @var \Egulias\EmailValidator\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * DeveloperAppStorage constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to be used.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache.
   * @param \Drupal\Component\Datetime\TimeInterface $system_time
   *   The system time.
   * @param \Drupal\apigee_edge\Entity\Controller\DeveloperAppControllerFactoryInterface $developer_app_controller_factory
   *   The developer app controller factory service.
   * @param \Drupal\apigee_edge\Entity\Controller\AppControllerInterface $app_controller
   *   The app controller service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Configuration factory.
   * @param \Egulias\EmailValidator\EmailValidatorInterface $email_validator
   *   The email validator service.
   */
  public function __construct(EntityTypeInterface $entity_type, CacheBackendInterface $cache_backend, MemoryCacheInterface $memory_cache, TimeInterface $system_time, DeveloperAppControllerFactoryInterface $developer_app_controller_factory, AppControllerInterface $app_controller, ConfigFactoryInterface $config, EmailValidatorInterface $email_validator) {
    parent::__construct($entity_type, $cache_backend, $memory_cache, $system_time, $app_controller);
    $this->developerAppControllerFactory = $developer_app_controller_factory;
    $this->appEntityController = new DeveloperAppEdgeEntityControllerProxy($developer_app_controller_factory, $app_controller);
    $this->cacheExpiration = $config->get('apigee_edge.developer_app_settings')->get('cache_expiration');
    $this->emailValidator = $email_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('cache.apigee_edge_entity'),
      $container->get('entity.memory_cache'),
      $container->get('datetime.time'),
      $container->get('apigee_edge.controller.developer_app_controller_factory'),
      $container->get('apigee_edge.controller.app'),
      $container->get('config.factory'),
      $container->get('email.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function entityController(): EdgeEntityControllerInterface {
    return $this->appEntityController;
  }

  /**
   * {@inheritdoc}
   */
  public function loadByDeveloper(string $developer_id): array {
    $query = $this->getQuery();
    // We have to figure out whether this is an email or a UUID to call the
    // best API endpoint that is possible.
    if ($this->emailValidator->isValid($developer_id)) {
      $query->condition('email', $developer_id);
    }
    else {
      $query->condition('developerId', $developer_id);
    }
    $ids = $query->execute();
    return $this->loadMultiple(array_values($ids));
  }

  /**
   * {@inheritdoc}
   */
  protected function getPersistentCacheTags(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $cacheTags = parent::getPersistentCacheTags($entity);
    // Add Drupal user id to ensure that when the owner of the app (Drupal user)
    // is deleted then the cached developer app data is also purged.
    // (This also invalidates cached app data when a user is updated which
    // might be even good for us. Create a custom solution if this default
    // behavior becomes a bottleneck.)
    if ($entity->getOwnerId()) {
      $cacheTags[] = "user:{$entity->getOwnerId()}";
    }
    return $cacheTags;
  }

}
