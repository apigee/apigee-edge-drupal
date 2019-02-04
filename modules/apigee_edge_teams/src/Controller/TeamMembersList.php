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

use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
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
   * TeamMembersList constructor.
   *
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   */
  public function __construct(TeamMembershipManagerInterface $team_membership_manager) {
    $this->teamMembershipManager = $team_membership_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge_teams.team_membership_manager')
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
    $entityType = $this->entityTypeManager()->getDefinition('team');
    $members = $this->teamMembershipManager->getMembers($team->id());
    $users_by_mail = [];

    if (!empty($members)) {
      $user_storage = $this->entityTypeManager()->getStorage('user');
      $uids = $user_storage->getQuery()
        ->condition('mail', $members, 'IN')
        ->execute();

      $users_by_mail = array_reduce($user_storage->loadMultiple($uids), function ($carry, UserInterface $item) {
        $carry[$item->getEmail()] = $item;
        return $carry;
      }, []);
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        'member' => $this->t('Member'),
        'operations' => $this->t('Operations'),
      ],
      '#title' => $this->t('@team members', ['@team' => $entityType->getSingularLabel()]),
      '#rows' => [],
      '#empty' => $this->t('There are no members yet.'),
      '#cache' => [
        'contexts' => $team->getCacheContexts(),
        'tags' => $team->getCacheTags(),
      ],
    ];

    // The list is ordered in the same order as the API returns the members.
    foreach ($members as $member) {
      $build['table']['#rows'][$member] = $this->buildRow($member, $users_by_mail, $team);
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
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team that the member belongs.
   *
   * @return array
   *   Render array.
   */
  protected function buildRow(string $member, array $users_by_mail, TeamInterface $team): array {
    $row = [];
    $can_view_user_profiles = $this->currentUser()->hasPermission('access user profiles');
    $row['id'] = Html::getUniqueId($member);

    if (array_key_exists($member, $users_by_mail)) {
      $row['data']['member'] = $can_view_user_profiles ? $users_by_mail[$member]->toLink() : "{$users_by_mail[$member]->label()} ($member)";
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
    $build = [
      '#type' => 'operations',
      '#links' => $this->getOperations($member, $team),
    ];

    return $build;
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
    $operations['edit'] = [
      'title' => $this->t('Remove'),
      'weight' => 10,
      'url' => Url::fromRoute('entity.team.members.remove', ['team' => $team->id(), 'developer' => $member], ['query' => ['destination' => $team->toUrl('members')->toString()]]),
    ];

    return $operations;
  }

}
