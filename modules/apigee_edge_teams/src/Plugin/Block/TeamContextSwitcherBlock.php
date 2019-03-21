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

namespace Drupal\apigee_edge_teams\Plugin\Block;

use Drupal\apigee_edge_teams\TeamContextManagerInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for switching team context.
 *
 * @Block(
 *   id = "apigee_edge_teams_team_switcher",
 *   admin_label = @Translation("Team switcher"),
 *   category = @Translation("Apigee Edge")
 * )
 */
class TeamContextSwitcherBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Apigee team membership manager.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  protected $teamMembershipManager;

  /**
   * The Apigee team context manager.
   *
   * @var \Drupal\apigee_edge_teams\TeamContextManagerInterface
   */
  protected $teamContextManager;

  /**
   * TeamContextSwitcher constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The Apigee team membership manager.
   * @param \Drupal\apigee_edge_teams\TeamContextManagerInterface $team_context_manager
   *   The Apigee team context manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $account, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager, TeamMembershipManagerInterface $team_membership_manager, TeamContextManagerInterface $team_context_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->account = $account;
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->teamMembershipManager = $team_membership_manager;
    $this->teamContextManager = $team_context_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('apigee_edge_teams.team_membership_manager'),
      $container->get('apigee_edge_teams.context_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIf($account->isAuthenticated());
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Do not show a block if we do not have a corresponding route.
    if (!($current_context = $this->teamContextManager->getCurrentContextEntity())) {
      return [];
    }

    // Add a link for the developer account.
    $entities = [
      $this->entityTypeManager->getStorage('user')->load($this->account->id()),
    ];

    // Add links for teams.
    if ($team_ids = $this->teamMembershipManager->getTeams($this->account->getEmail())) {
      $entities = array_merge($entities, $this->entityTypeManager->getStorage('team')
        ->loadMultiple($team_ids));
    }

    $links = [];

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    foreach ($entities as $entity) {
      // No link if we are on the current context route.
      if ($current_context instanceof EntityInterface && $current_context->getEntityTypeId() === $entity->getEntityTypeId() && $current_context->id() === $entity->id()) {
        // Prepend link as the first link.
        array_unshift($links, [
          'title' => $entity->label(),
          'url' => Url::fromRoute('<nolink>'),
        ]);
        continue;
      }

      // Get destination link for entity.
      if (($url = $this->teamContextManager->getDestinationUrlForEntity($entity)) && $url->access($this->account)) {
        $links[] = [
          'title' => $entity->label(),
          'url' => $url,
        ];
      }
    }

    // Add additional links.
    foreach ($this->getAdditionalLinks() as $route_name => $title) {
      if (($url = Url::fromRoute($route_name)) && ($url->access($this->account))) {
        $links[] = [
          'title' => $title,
          'url' => $url,
        ];
      }
    }

    return count($links) ? [
      '#type' => 'dropbutton',
      '#links' => $links,
      '#attributes' => [
        'class' => [
          'team-switcher',
        ],
      ],
      '#attached' => [
        'library' => [
          'apigee_edge_teams/switcher',
        ],
      ],
    ] : [];
  }

  /**
   * Returns an array of additional links for the switcher.
   *
   * @return array
   *   An array of additional links keyed with the route name.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getAdditionalLinks() {
    return [
      'entity.team.collection' => $this->t('My @teams', [
        '@teams' => $this->entityTypeManager->getDefinition('team')
          ->getPluralLabel(),
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'user',
      'url.path',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), [
      'team_list',
      'user:' . $this->account->id(),
    ]);
  }

}
