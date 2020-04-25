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

use Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\apigee_edge_teams\Exception\InvalidArgumentException;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity storage class for team member role entities.
 */
class TeamMemberRoleStorage extends SqlContentEntityStorage implements TeamMemberRoleStorageInterface {

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  protected $teamMembershipManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * TeamMemberRoleStorage constructor.
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
   *   The memory cache backend to be used.
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, Connection $database, EntityFieldManagerInterface $entity_field_manager, CacheBackendInterface $cache, LanguageManagerInterface $language_manager, MemoryCacheInterface $memory_cache, TeamMembershipManagerInterface $team_membership_manager, LoggerInterface $logger, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, EntityTypeManagerInterface $entity_type_manager = NULL) {
    parent::__construct($entity_type, $database, $entity_field_manager, $cache, $language_manager, $memory_cache, $entity_type_bundle_info, $entity_type_manager);
    $this->teamMembershipManager = $team_membership_manager;
    $this->logger = $logger;
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
      $container->get('apigee_edge_teams.team_membership_manager'),
      $container->get('logger.channel.apigee_edge_teams'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function loadByDeveloperAndTeam(AccountInterface $account, TeamInterface $team): ?TeamMemberRoleInterface {
    $result = $this->loadByProperties([
      'uid' => $account->id(),
      'team' => $team->id(),
    ]);

    $result = reset($result);

    return $result ? $result : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadByDeveloper(AccountInterface $account): array {
    return $this->loadByProperties([
      'uid' => $account->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByTeam(TeamInterface $team): array {
    return $this->loadByProperties([
      'team' => $team->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function addTeamRoles(AccountInterface $account, TeamInterface $team, array $roles): TeamMemberRoleInterface {
    if ($account->isAnonymous()) {
      throw new InvalidArgumentException('Anonymous user can not be member of a team.');
    }
    try {
      $developer_team_ids = $this->teamMembershipManager->getTeams($account->getEmail());
    }
    catch (\Exception $e) {
      $developer_team_ids = [];
    }
    if (!in_array($team->id(), $developer_team_ids)) {
      throw new InvalidArgumentException("{$account->getEmail()} is not member of {$team->id()} team.");
    }
    // Indicates whether a new team member role entity had to be created
    // or not.
    /** @var \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface $team_member_roles */
    $team_member_roles = $this->loadByDeveloperAndTeam($account, $team);
    if ($team_member_roles === NULL) {
      $team_member_roles = $this->create(['uid' => ['target_id' => $account->id()], 'team' => ['target_id' => $team->id()]]);
    }
    // Make sure we only store unique values in the field.
    $existing_roles = array_map(function ($item) {
      return $item['target_id'];
    }, $team_member_roles->roles->getValue());
    $unique_roles = array_diff(array_unique($roles), $existing_roles);

    foreach ($unique_roles as $role) {
      $team_member_roles->roles[] = ['target_id' => $role];
    }

    try {
      $team_member_roles->save();
    }
    catch (EntityStorageException $exception) {
      $context = [
        '%developer' => $account->getEmail(),
        '%team_id' => $team->id(),
        '%roles' => implode(',', $roles),
        'link' => $team->toLink($this->t('Members'), 'members')->toString(),
      ];
      $context += Error::decodeException($exception);
      $this->logger->warning('%developer team member roles in %team_id team could not be saved. Roles: %roles. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
      throw $exception;
    }

    return $team_member_roles;
  }

  /**
   * {@inheritdoc}
   */
  public function removeTeamRoles(AccountInterface $account, TeamInterface $team, array $roles): TeamMemberRoleInterface {
    if ($account->isAnonymous()) {
      throw new InvalidArgumentException('Anonymous user can not be member of a team.');
    }
    try {
      $developer_team_ids = $this->teamMembershipManager->getTeams($account->getEmail());
    }
    catch (\Exception $e) {
      $developer_team_ids = [];
    }
    if (!in_array($team->id(), $developer_team_ids)) {
      throw new InvalidArgumentException("{$account->getEmail()} is not member of {$team->id()} team.");
    }
    /** @var \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface $team_member_roles */
    $team_member_roles = $this->loadByDeveloperAndTeam($account, $team);
    if ($team_member_roles === NULL) {
      throw new InvalidArgumentException("{$account->getEmail()} does not have team roles in {$team->id()} team.");
    }

    $team_member_roles->roles = array_filter($team_member_roles->roles->getValue(), function (array $item) use ($roles) {
      return !in_array($item['target_id'], $roles);
    });

    try {
      // If the developer does not have any roles in the team anymore then
      // remove its team member role entity.
      if (empty($team_member_roles->roles->getValue())) {
        $team_member_roles->delete();
      }
      else {
        $team_member_roles->save();
      }
    }
    catch (EntityStorageException $exception) {
      $context = [
        '%developer' => $account->getEmail(),
        '%team_id' => $team->id(),
        '%roles' => implode(',', $roles),
        'link' => $team->toLink($this->t('Members'), 'members')->toString(),
      ];
      $context += Error::decodeException($exception);
      $this->logger->warning('%developer team member roles in %team_id team could not be removed. Roles: %roles. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
      throw $exception;
    }

    return $team_member_roles;
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface $entity */
    $return = parent::doSave($id, $entity);

    if ($return === SAVED_NEW) {
      // Invalidate team related caches - for example the render cache of
      // the team members list - if the developer did not have a developer
      // team role entity before.
      // @see \Drupal\apigee_edge_teams\Controller\TeamMembersList::buildRow()
      Cache::invalidateTags($entity->getTeam()->getCacheTags());
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface $entity */
    foreach ($entities as $entity) {
      // This sanity check allows uninstalling the module if there is no
      // connection to Apigee Edge.
      if (($team = $entity->getTeam()) !== NULL) {
        // See explanation in doSave().
        Cache::invalidateTags($team->getCacheTags());
      }
    }
    parent::doDelete($entities);
  }

}
