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

namespace Drupal\apigee_edge_teams\Entity\Storage;

use Apigee\Edge\Exception\ApiException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Error;
use Drupal\apigee_edge\Entity\Controller\CachedManagementApiEdgeEntityControllerProxy;
use Drupal\apigee_edge\Entity\Controller\EdgeEntityControllerInterface;
use Drupal\apigee_edge\Entity\Controller\EntityCacheAwareControllerInterface;
use Drupal\apigee_edge\Entity\Controller\ManagementApiEdgeEntityControllerProxy;
use Drupal\apigee_edge\Entity\Storage\AttributesAwareFieldableEdgeEntityStorageBase;
use Drupal\apigee_edge_teams\Entity\Controller\TeamControllerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Entity storage implementation for teams.
 */
class TeamStorage extends AttributesAwareFieldableEdgeEntityStorageBase implements TeamStorageInterface {

  /**
   * The team controller service.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Controller\TeamControllerInterface
   */
  private $teamController;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs an TeamStorage instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to be used.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache.
   * @param \Drupal\Component\Datetime\TimeInterface $system_time
   *   The system time.
   * @param \Drupal\apigee_edge_teams\Entity\Controller\TeamControllerInterface $team_controller
   *   The team controller service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Configuration factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(EntityTypeInterface $entity_type, CacheBackendInterface $cache_backend, MemoryCacheInterface $memory_cache, TimeInterface $system_time, TeamControllerInterface $team_controller, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config, LoggerInterface $logger, StateInterface $state) {
    parent::__construct($entity_type, $cache_backend, $memory_cache, $system_time);
    $this->teamController = $team_controller;
    $config = $config->get('apigee_edge_teams.team_settings');
    $this->cacheExpiration = $config->get('cache_expiration');
    $this->cacheInsertChunkSize = $config->get('cache_insert_chunk_size') ?? static::DEFAULT_PERSISTENT_CACHE_INSERT_CHUNK_SIZE;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->state = $state;
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
      $container->get('apigee_edge_teams.controller.team'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('logger.channel.apigee_edge_teams'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function entityController(): EdgeEntityControllerInterface {
    if ($this->teamController instanceof EntityCacheAwareControllerInterface) {
      return new CachedManagementApiEdgeEntityControllerProxy($this->teamController);
    }
    return new ManagementApiEdgeEntityControllerProxy($this->teamController);
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $entity */
    $team_status = $entity->getStatus();
    $result = parent::doSave($id, $entity);

    // Change the status of the team (company) in Apigee Edge.
    // @todo Only change it if it has changed.
    try {
      $this->teamController->setStatus($entity->id(), $team_status);
    }
    catch (ApiException $exception) {
      throw new EntityStorageException($exception->getMessage(), $exception->getCode(), $exception);
    }
    // Apply status change in the entity object as well.
    $entity->setStatus($team_status);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function doPostSave(EntityInterface $entity, $update) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $entity */
    if (!$update) {
      /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorageInterface $team_member_role_storage */
      $team_member_role_storage = $this->entityTypeManager->getStorage('team_member_role');
      /** @var \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface[] $team_roles_by_teams */
      $team_roles_by_teams = $team_member_role_storage->loadByTeam($entity);
      if ($team_roles_by_teams) {
        // Teams (Companies) can be deleted outside of Drupal so it could
        // happen that remnant team member roles exist in database when
        // a new team gets created with a previously used team id.
        $context = [
          '%team' => "{$entity->label()} ({$entity->id()})",
          'link' => $entity->toLink($this->t('Members'), 'members')->toString(),
        ];
        $this->logger->warning('Integrity check: Remnant team member roles found for new %team team.', $context);
        $success = TRUE;
        foreach ($team_roles_by_teams as $team_member_role) {
          try {
            $team_member_role->delete();
          }
          catch (\Exception $exception) {
            $success = FALSE;
            $context += Error::decodeException($exception);
            $this->logger->warning('Failed to remove remnant developer role from new %team team. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
          }
        }

        if ($success) {
          $this->logger->info('Integrity check: Successfully removed all remnant team member roles in association with the new %team team.', $context);
        }
        else {
          $this->logger->critical('Integrity check: Failed to remove all remnant team member roles from the database for the new %team team.', $context);
        }
      }
    }
    parent::doPostSave($entity, $update);
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    parent::doDelete($entities);
    /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorageInterface $team_member_role_storage */
    $team_member_role_storage = $this->entityTypeManager->getStorage('team_member_role');
    /** @var \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface[] $dev_roles_by_teams */
    $dev_roles_by_teams = $team_member_role_storage->loadByProperties(['team' => array_keys($entities)]);
    // When a team gets deleted all team member roles related to the team
    // should be deleted from the database.
    foreach ($dev_roles_by_teams as $role) {
      try {
        $role->delete();
      }
      catch (\Exception $exception) {
        $context = [
          '%team' => "{$role->getTeam()->label()} ({$role->getTeam()->id()})",
          '%developer' => $role->getDeveloper()->getEmail(),
        ];
        $context += Error::decodeException($exception);
        $this->logger->critical("Integrity check: Failed to remove %developer team member's role(s) from %team team when team got deleted. @message %function (line %line of %file). <pre>@backtrace_string</pre>", $context);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getFromPersistentCache(?array &$ids = NULL) {

    if ($this->cacheExpiration === 0 || !$this->entityType->isPersistentlyCacheable()) {
      return [];
    }

    if ($ids === NULL) {
      // During tests, this state is set to TRUE (in parent::setUp()) to
      // force a cache miss and take data from the Mock API.
      // This prevents test isolation failures where
      // stale data from a previous test could cause the current test to fail.
      if ($this->state->get('apigee_teams_test_skip_cache', FALSE)) {
        return [];
      }
      $all_ids_cid = 'all_ids:' . $this->entityTypeId;
      // Try to load our "master ID list" from the cache.
      if ($cache = $this->cacheBackend->get($all_ids_cid)) {
        // We found the list! Set $ids to this list.
        $ids = $cache->data;
      }
      // If we did NOT find the list, $ids remains NULL. The code
      // will proceed as normal, hit the API, and our modified
      // setPersistentCache() will create the list for next time.
    }

    if (empty($ids)) {
      return [];
    }

    return parent::getFromPersistentCache($ids);
  }

  /**
   * {@inheritdoc}
   */
  protected function setPersistentCache(array $entities) {
    parent::setPersistentCache($entities);

    $entity_count = 0;
    if (!empty($entities)) {
      // Get all entity IDs.
      $all_entity_ids = array_keys($entities);
      $entity_count = count($all_entity_ids);
    }

    // After all chunks are saved, save the master ID list.
    // Only proceed if we have entities to process.
    if ($entity_count > 0) {
      $all_ids_cid = 'all_ids:' . $this->entityTypeId;
      // Use the main entity type tag so this item is cleared when
      // the rest of the entity cache is cleared.
      $all_ids_tags = [$this->entityTypeId . ':values'];

      // Try to load existing cache.
      // $this->cacheBackend->get() returns FALSE if the item does not exist.
      $cache_object = $this->cacheBackend->get($all_ids_cid);

      // If cache is empty AND count is 1, we DO NOT cache the entity.
      if ($cache_object || $entity_count > 1) {

        $existing_ids = $cache_object ? $cache_object->data : [];

        $final_all_team_ids = array_unique(array_merge($existing_ids, $all_entity_ids));

        $this->cacheBackend->set(
          $all_ids_cid,
          $final_all_team_ids,
          $this->getPersistentCacheExpiration(),
          $all_ids_tags
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(?array $ids = NULL) {

    $this->cacheBackend->delete('all_ids:' . $this->entityTypeId);

    parent::resetCache($ids);
  }

}
