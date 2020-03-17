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

namespace Drupal\apigee_edge_actions;

use Drupal\apigee_edge_actions\Event\EdgeEntityEventEdge;
use Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface;
use Drupal\apigee_edge\Entity\DeveloperCompaniesCacheInterface;
use Drupal\apigee_edge_teams\CompanyMembersControllerFactoryInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Decorates the apigee_edge_teams.team_membership_manager service.
 */
class TeamMembershipManager implements TeamMembershipManagerInterface {

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  private $inner;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * The company members controller factory service.
   *
   * @var \Drupal\apigee_edge_teams\CompanyMembersControllerFactoryInterface
   */
  private $companyMembersControllerFactory;

  /**
   * The developer companies cache.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperCompaniesCacheInterface
   */
  private $developerCompaniesCache;

  /**
   * The developer controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface
   */
  private $developerController;

  /**
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  private $cacheTagsInvalidator;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * TeamMembershipManager constructor.
   *
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $inner
   *   The Apigee Edge team manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\apigee_edge_teams\CompanyMembersControllerFactoryInterface $company_members_controller_factory
   *   The company members controller factory service.
   * @param \Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface $developer_controller
   *   The developer controller service.
   * @param \Drupal\apigee_edge\Entity\DeveloperCompaniesCacheInterface $developer_companies_cache
   *   The developer companies cache.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(TeamMembershipManagerInterface $inner, EntityTypeManagerInterface $entity_type_manager, CompanyMembersControllerFactoryInterface $company_members_controller_factory, DeveloperControllerInterface $developer_controller, DeveloperCompaniesCacheInterface $developer_companies_cache, CacheTagsInvalidatorInterface $cache_tags_invalidator, LoggerInterface $logger, EventDispatcherInterface $event_dispatcher) {
    $this->inner = $inner;
    $this->entityTypeManager = $entity_type_manager;
    $this->companyMembersControllerFactory = $company_members_controller_factory;
    $this->developerController = $developer_controller;
    $this->developerCompaniesCache = $developer_companies_cache;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getMembers(string $team): array {
    return $this->inner->getMembers($team);
  }

  /**
   * {@inheritdoc}
   */
  public function addMembers(string $team, array $developers): void {
    $this->inner->addMembers($team, $developers);

    $this->dispatchEvent('apigee_edge_actions_entity_add_member:team', $team, $developers);
  }

  /**
   * {@inheritdoc}
   */
  public function removeMembers(string $team, array $developers): void {
    $this->inner->removeMembers($team, $developers);

    $this->dispatchEvent('apigee_edge_actions_entity_remove_member:team', $team, $developers);
  }

  /**
   * {@inheritdoc}
   */
  public function getTeams(string $developer): array {
    return $this->inner->getTeams($developer);
  }

  /**
   * Helper to dispatch event.
   *
   * @param string $event
   *   The event name.
   * @param string $team
   *   The team id.
   * @param array $developers
   *   An array of developers.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function dispatchEvent(string $event, string $team, array $developers) {
    $team = $this->entityTypeManager->getStorage('team')->load($team);
    $users_by_mail = array_reduce($this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $developers]), function (array $carry, UserInterface $user) {
      $carry[$user->getEmail()] = $user;
      return $carry;
    }, []);

    // Dispatch an event for each developer.
    foreach ($developers as $developer) {
      $this->eventDispatcher->dispatch($event, new EdgeEntityEventEdge($team, [
        'team' => $team,
        'member' => $users_by_mail[$developer],
      ]));
    }
  }

}
