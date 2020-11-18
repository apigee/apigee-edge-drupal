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

namespace Drupal\apigee_edge_teams\Controller;

use Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Team members list builder for a team.
 */
class TeamMembersList extends ControllerBase {

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  private $teamMembershipManager;

  /**
   * Default member roles.
   *
   * @var array
   */
  protected $defaultRoles = [];

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|null
   */
  protected $moduleHandler;

  /**
   * TeamMembersList constructor.
   *
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $module_handler
   *   The module handler.
   */
  public function __construct(TeamMembershipManagerInterface $team_membership_manager, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler = NULL) {
    if (!$module_handler) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $module_handler is deprecated in apigee_edge:8-x-1.19 and is required before apigee_edge:8.x-2.0. See https://github.com/apigee/apigee-edge-drupal/pull/518.', E_USER_DEPRECATED);
      $module_handler = \Drupal::moduleHandler();
    }

    $this->teamMembershipManager = $team_membership_manager;
    $this->entityTypeManager = $entity_type_manager;

    if ($role = $this->entityTypeManager()->getStorage('team_role')->load(TeamRoleInterface::TEAM_MEMBER_ROLE)) {
      $this->defaultRoles = [$role->id() => $role->label()];
    }

    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge_teams.team_membership_manager'),
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * Returns a list of team members.
   *
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team which members gets listed.
   *
   * @return array
   *   Render array.
   *
   * @see \Drupal\apigee_edge_teams\Entity\TeamRouteProvider::getListTeamMembersRoute()
   */
  public function overview(TeamInterface $team) {
    $entity_type = $this->entityTypeManager()->getDefinition('team');
    $members = $this->teamMembershipManager->getMembers($team->id());
    $users_by_mail = [];
    $team_member_roles_by_mail = [];

    if (!empty($members)) {
      $user_storage = $this->entityTypeManager()->getStorage('user');
      $uids = $user_storage->getQuery()
        ->condition('mail', $members, 'IN')
        ->execute();

      $users_by_mail = array_reduce($user_storage->loadMultiple($uids), function ($carry, UserInterface $item) {
        $carry[$item->getEmail()] = $item;
        return $carry;
      }, []);
      /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorageInterface $team_member_role_storage */
      $team_member_role_storage = $this->entityTypeManager()->getStorage('team_member_role');
      $team_member_roles_by_mail = array_reduce($team_member_role_storage->loadByTeam($team), function ($carry, TeamMemberRoleInterface $developer_role) {
        $carry[$developer_role->getDeveloper()->getEmail()] = $developer_role;

        return $carry;
      }, []);
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        'member' => $this->t('Member'),
        'roles' => $this->t('Roles'),
        'operations' => $this->t('Operations'),
      ],
      '#title' => $this->t('@team Members', ['@team' => $entity_type->getSingularLabel()]),
      '#rows' => [],
      '#empty' => $this->t('There are no members yet.'),
      '#cache' => [
        'contexts' => $team->getCacheContexts(),
        'tags' => $team->getCacheTags(),
      ],
    ];

    // The list is ordered in the same order as the API returns the members.
    foreach ($members as $member) {
      $build['table']['#rows'][$member] = $this->buildRow($member, $users_by_mail, $team_member_roles_by_mail, $team);
    }

    // Add invitations.
    if ($invitation_view = Views::getView('team_invitations')) {
      $build['invitations'] = $invitation_view->buildRenderable('team', [
        'team' => $team->id(),
      ]);
    }

    return $build;
  }

  /**
   * Builds a row for a team member.
   *
   * @param string $member
   *   The email address of a member (developer).
   * @param array $users_by_mail
   *   Associative array of Drupal users keyed by their email addresses. The
   *   list only contains those Drupal users who are member of the team.
   * @param \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface[] $team_member_roles_by_mail
   *   Associative array of team member roles keyed by email addresses.
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team that the member belongs.
   *
   * @return array
   *   Render array.
   */
  protected function buildRow(string $member, array $users_by_mail, array $team_member_roles_by_mail, TeamInterface $team): array {
    $row = [];
    $row['id'] = Html::getUniqueId($member);

    if (array_key_exists($member, $users_by_mail)) {
      // @see \Drupal\user\UserAccessControlHandler::checkAccess()
      $row['data']['member'] = $users_by_mail[$member]->access('view') ? $users_by_mail[$member]->toLink() : "{$users_by_mail[$member]->label()} ($member)";
    }
    else {
      // We only display the email address of the member in this case
      // because displaying its first name and last name would require API
      // call(s). We can not load only a set of developers from Apigee Edge
      // (like we loaded only the necessary amount of Drupal users), we can only
      // all developers which could unnecessarily slow down this page if the
      // developer entity cache is cold.
      $row['data']['member'] = $member;
    }

    if (array_key_exists($member, $team_member_roles_by_mail)) {
      $roles = array_reduce($team_member_roles_by_mail[$member]->getTeamRoles(), function ($carry, TeamRoleInterface $role) {
        $carry[$role->id()] = $role->label();
        return $carry;
      }, $this->defaultRoles);
      $row['data']['roles']['data'] = [
        '#theme' => 'item_list',
        '#items' => $roles,
        '#cache' => [
          'contexts' => $team_member_roles_by_mail[$member]->getCacheContexts(),
          'tags' => $team_member_roles_by_mail[$member]->getCacheTags(),
        ],
      ];
    }
    else {
      $row['data']['roles']['data'] = [
        '#theme' => 'item_list',
        '#items' => $this->defaultRoles,
      ];
    }

    $row['data']['operations']['data'] = $this->buildOperations($member, $team);

    return $row;
  }

  /**
   * Builds operations for a member.
   *
   * @param string $member
   *   The email address of a member (developer).
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team that the member belongs.
   *
   * @return array
   *   Render array.
   */
  protected function buildOperations(string $member, TeamInterface $team): array {
    return [
      '#type' => 'operations',
      '#links' => $this->getOperations($member, $team),
    ];
  }

  /**
   * Returns available operations for a member.
   *
   * @param string $member
   *   The email address of a member (developer).
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team that the member belongs.
   *
   * @return array
   *   Array of operation links.
   */
  protected function getOperations(string $member, TeamInterface $team) {
    $operations = [];

    $url = Url::fromRoute('entity.team.member.edit', ['team' => $team->id(), 'developer' => $member], ['query' => ['destination' => $team->toUrl('members')->toString()]]);
    if ($url->access()) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $url,
      ];
    }

    $url = Url::fromRoute('entity.team.member.remove', ['team' => $team->id(), 'developer' => $member], ['query' => ['destination' => $team->toUrl('members')->toString()]]);
    if ($url->access()) {
      $operations['remove'] = [
        'title' => $this->t('Remove'),
        'url' => $url,
      ];
    }

    // Allow modules to alter operations.
    $this->moduleHandler->alter('entity_operation', $operations, $team);

    return $operations;
  }

}
