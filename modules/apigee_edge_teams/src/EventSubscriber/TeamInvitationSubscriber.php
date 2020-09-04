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

namespace Drupal\apigee_edge_teams\EventSubscriber;

use Drupal\apigee_edge_teams\Entity\TeamRole;
use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;
use Drupal\apigee_edge_teams\Event\TeamInvitationEventInterface;
use Drupal\apigee_edge_teams\Event\TeamInvitationEvents;
use Drupal\apigee_edge_teams\TeamInvitationNotifierInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for team invitation.
 */
class TeamInvitationSubscriber implements EventSubscriberInterface {

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The team membership manager.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  protected $teamMembershipManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The team_member_role storage handler.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorageInterface
   */
  protected $teamMemberRoleStorage;

  /**
   * The team_invitation notifier service.
   *
   * @var \Drupal\apigee_edge_teams\TeamInvitationNotifierInterface
   */
  protected $teamInvitationNotifier;

  /**
   * TeamInvitationSubscriber constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager.
   * @param \Drupal\apigee_edge_teams\TeamInvitationNotifierInterface $team_invitation_notifier
   *   The team_invitation notifier service.
   */
  public function __construct(LoggerChannelInterface $logger, EntityTypeManagerInterface $entity_type_manager, TeamMembershipManagerInterface $team_membership_manager, TeamInvitationNotifierInterface $team_invitation_notifier) {
    $this->logger = $logger;
    $this->teamMembershipManager = $team_membership_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->teamMemberRoleStorage = $this->entityTypeManager->getStorage('team_member_role');
    $this->teamInvitationNotifier = $team_invitation_notifier;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TeamInvitationEvents::CREATED][] = 'onCreated';
    $events[TeamInvitationEvents::ACCEPTED][] = 'onAccepted';
    return $events;
  }

  /**
   * Callback for on created event.
   *
   * @param \Drupal\apigee_edge_teams\Event\TeamInvitationEventInterface $event
   *   The event.
   */
  public function onCreated(TeamInvitationEventInterface $event) {
    $team_invitation = $event->getTeamInvitation();
    if (!$team_invitation->isPending()) {
      return;
    }

    if ($this->teamInvitationNotifier->sendNotificationsFor($team_invitation)) {
      $this->logger->notice('Successfully sent invitation email to %recipient.', ['%recipient' => $team_invitation->getRecipient()]);
    }
  }

  /**
   * Callback for on accepted event.
   *
   * @param \Drupal\apigee_edge_teams\Event\TeamInvitationEventInterface $event
   *   The event.
   */
  public function onAccepted(TeamInvitationEventInterface $event) {
    $team_invitation = $event->getTeamInvitation();
    if (!$team_invitation->isAccepted()) {
      return;
    }

    $team = $team_invitation->getTeam();

    $context = [
      '@developer' => $team_invitation->getRecipient(),
      '@team' => mb_strtolower($team->getEntityType()->getSingularLabel()),
      '%team_id' => $team->id(),
    ];

    $success = FALSE;
    try {
      $this->teamMembershipManager->addMembers($team->id(), [
        $team_invitation->getRecipient()
      ]);
      $success = TRUE;
    }
    catch (\Exception $exception) {
      $context += Error::decodeException($exception);
      $this->logger->error('Failed to add developer to %team_id team. Developer: @developer. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
    }

    if ($success) {
      $selected_roles = array_map(function (TeamRoleInterface $team_member_role) {
        return $team_member_role->id();
      }, $team_invitation->getTeamRoles());

      /** @var \Drupal\user\UserInterface $user */
      $user = user_load_by_mail($team_invitation->getRecipient());

      if (!$user) {
        $this->logger->error('Developer with email %email not found.', [
          '%email' => $team_invitation->getRecipient(),
        ]);
        return;
      }

      /** @var \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface $team_member_roles */
      $team_member_roles = $this->teamMemberRoleStorage->loadByDeveloperAndTeam($user, $team);
      if ($team_member_roles !== NULL) {
        // It could happen the a developer got removed from a team (company)
        // outside of Drupal therefore its team member role entity
        // has not been deleted.
        // @see \Drupal\apigee_edge_teams\TeamMembershipManager::removeMembers()
        try {
          $team_member_roles->delete();
        }
        catch (\Exception $exception) {
          $context += [
            '%developer' => $user->getEmail(),
            '%roles' => implode(', ', array_map(function (TeamRole $role) {
              return $role->label();
            }, $team_member_roles->getTeamRoles())),
            'link' => $team->toLink($this->t('Members'), 'members')->toString(),
          ];
          $context += Error::decodeException($exception);
          $this->logger->error('Integrity check: %developer developer had a team member role entity with "%roles" team roles for %team_id team when it was added to the team. These roles could not been deleted automatically. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
        }
      }

      try {
        $this->teamMemberRoleStorage->addTeamRoles($user, $team, $selected_roles);
      }
      catch (\Exception $exception) {
        $this->logger->error('Selected roles could not be saved for %user developer.', [
          '%user' => $user->label(),
        ]);
      }
    }
  }

}
