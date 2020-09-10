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

namespace Drupal\apigee_edge_teams\Entity\Storage;

use Drupal\apigee_edge_teams\Entity\TeamInvitationInterface;
use Drupal\apigee_edge_teams\Event\TeamInvitationEvent;
use Drupal\apigee_edge_teams\Event\TeamInvitationEvents;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Storage handler for team_invitation.
 */
class TeamInvitationStorage extends SqlContentEntityStorage implements TeamInvitationStorageInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * TeamInvitationStorage constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to be used.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface|null $memory_cache
   *   The memory cache.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface|null $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityTypeInterface $entity_type, Connection $database, EntityFieldManagerInterface $entity_field_manager, CacheBackendInterface $cache, LanguageManagerInterface $language_manager, MemoryCacheInterface $memory_cache, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, TimeInterface $time) {
    parent::__construct($entity_type, $database, $entity_field_manager, $cache, $language_manager, $memory_cache, $entity_type_bundle_info, $entity_type_manager);
    $this->eventDispatcher = $event_dispatcher;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('database'),
      $container->get('entity_field.manager'),
      $container->get('cache.entity'),
      $container->get('language_manager'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function invokeHook($hook, EntityInterface $entity) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface $entity */
    parent::invokeHook($hook, $entity);

    switch ($hook) {
      case 'insert':
        $this->eventDispatcher->dispatch(TeamInvitationEvents::CREATED, new TeamInvitationEvent($entity));
        break;

      case 'update':
        /** @var \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface $original */
        $original = $entity->original;
        if (!$original) {
          return;
        }

        // Do not persist original after events are fired.
        // @see https://www.drupal.org/project/drupal/issues/2140179.
        unset($entity->original);

        if (!$original->isDeclined() && $entity->isDeclined()) {
          $this->eventDispatcher->dispatch(TeamInvitationEvents::DECLINED, new TeamInvitationEvent($entity));
        }

        if (!$original->isAccepted() && $entity->isAccepted()) {
          $this->eventDispatcher->dispatch(TeamInvitationEvents::ACCEPTED, new TeamInvitationEvent($entity));
        }
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadByRecipient(string $email, ?string $team_id = NULL): array {
    $query = $this->getQuery()->condition('recipient', $email);

    if ($team_id) {
      $query->condition('team', $team_id);
    }

    $ids = $query->execute();
    return $this->loadMultiple(array_values($ids));
  }

  /**
   * {@inheritdoc}
   */
  public function getInvitationsToExpire(): array {
    $query = $this->getQuery()->condition('expiry', $this->time->getCurrentTime(), '<')
      ->condition('status', TeamInvitationInterface::STATUS_PENDING);

    $ids = $query->execute();
    return $this->loadMultiple(array_values($ids));
  }

}
