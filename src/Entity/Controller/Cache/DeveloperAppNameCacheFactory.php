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

namespace Drupal\apigee_edge\Entity\Controller\Cache;

use Drupal\apigee_edge\Exception\DeveloperDoesNotExistException;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Developer specific app name cache by app owner factory service.
 *
 * This service ensures that the same cache instance is being used for
 * the same developer's developer apps even if the developer is sometimes
 * referenced by its UUID and sometimes by its email address.
 */
final class DeveloperAppNameCacheFactory implements AppNameCacheByOwnerFactoryInterface {

  /**
   * The (general) app name cache by owner factory service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface
   */
  private $appNameCacheByOwnerFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The email validator service.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * DeveloperAppNameCacheFactory constructor.
   *
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner_factory
   *   The (general) app name cache by app owner factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Utility\EmailValidatorInterface $email_validator
   *   The email validator service.
   */
  public function __construct(AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner_factory, EntityTypeManagerInterface $entity_type_manager, EmailValidatorInterface $email_validator) {
    $this->appNameCacheByOwnerFactory = $app_name_cache_by_owner_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->emailValidator = $email_validator;
  }

  /**
   * {@inheritdoc}
   */
  public function getAppNameCache(string $owner): EntityIdCacheInterface {
    if ($this->emailValidator->isValid($owner)) {
      /** @var \Drupal\apigee_edge\Entity\Developer $developer */
      $developer = $this->entityTypeManager->getStorage('developer')->load($owner);
      if ($developer === NULL) {
        throw new DeveloperDoesNotExistException($owner);
      }
      $owner = $developer->getDeveloperId();
    }

    return $this->appNameCacheByOwnerFactory->getAppNameCache($owner);
  }

}
