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

use Apigee\Edge\Api\Management\Entity\AppInterface;
use Apigee\Edge\Api\Management\Entity\CompanyAppInterface;
use Apigee\Edge\Api\Management\Entity\DeveloperAppInterface;
use Apigee\Edge\Entity\EntityInterface;
use Drupal\apigee_edge\Exception\RuntimeException;

/**
 * Default app cache implementation for app controllers.
 *
 * See interface definition for more details,
 *
 * This app cache implementation also keeps an internal list about apps by using
 * using their app owners and app names as an id.
 * The owner information is extracted from the app entity
 * so it is either developer id (UUID) or company name. Developer email address
 * is not acceptable here.
 */
final class AppCache extends EntityCache implements AppCacheInterface {

  /**
   * An associative array with app owners, app names and app ids.
   *
   * Parent keys are app owners (developer UUID or company name), second level
   * keys are app names and their values are app ids (UUIDs).
   *
   * This array can contain app ids that have been invalidated
   * in cache. It is not a problem because this information is only used
   * internally.
   *
   * @var array
   */
  private $appOwnerAppNameAppIdMap = [];

  /**
   * {@inheritdoc}
   */
  protected function prepareCacheItem(EntityInterface $entity): array {
    /** @var \Apigee\Edge\Api\Management\Entity\AppInterface $entity */
    $owner = $this->getAppOwner($entity);
    $item = [
      // We have to cache apps by their app ids here, $entity->id() returns
      // the name of the app.
      $entity->getAppId() => [
        'data' => $entity,
        'tags' => [
          $entity->getAppId(),
          $owner,
        ],
      ],
    ];

    $this->appOwnerAppNameAppIdMap[$owner][$entity->getName()] = $entity->getAppId();

    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function getAppOwner(AppInterface $app): string {
    if ($app instanceof DeveloperAppInterface) {
      return $app->getDeveloperId();
    }
    elseif ($app instanceof CompanyAppInterface) {
      return $app->getCompanyName();
    }

    throw new RuntimeException('Unable to identify app owner.');
  }

  /**
   * {@inheritdoc}
   */
  public function getAppsByOwner(string $owner): ?array {
    if (!empty($this->appOwnerAppNameAppIdMap[$owner])) {
      return $this->getEntities($this->appOwnerAppNameAppIdMap[$owner]);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function removeAppsByOwner(string $owner): void {
    if (!empty($this->appOwnerAppNameAppIdMap[$owner])) {
      $this->removeEntities($this->appOwnerAppNameAppIdMap[$owner]);
      unset($this->appOwnerAppNameAppIdMap[$owner]);
    }
  }

}
