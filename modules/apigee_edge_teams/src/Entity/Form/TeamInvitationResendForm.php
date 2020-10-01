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

namespace Drupal\apigee_edge_teams\Entity\Form;

use Drupal\apigee_edge_teams\Entity\TeamInvitationInterface;
use Drupal\apigee_edge_teams\TeamInvitationNotifierInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the resend form for team_invitation.
 */
class TeamInvitationResendForm extends TeamInvitationFormBase {

  /**
   * The team_invitation notifier service.
   *
   * @var \Drupal\apigee_edge_teams\TeamInvitationNotifierInterface
   */
  protected $teamInvitationNotifier;

  /**
   * TeamInvitationResendForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\apigee_edge_teams\TeamInvitationNotifierInterface $team_invitation_notifier
   *   The team_invitation notifier service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, TeamInvitationNotifierInterface $team_invitation_notifier) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->teamInvitationNotifier = $team_invitation_notifier;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('apigee_edge_teams.team_invitation_notifier.email')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to resend the %label to %recipient?', [
      '%label' => $this->getEntity()->label(),
      '%recipient' => $this->getEntity()->getRecipient(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Resend invitation');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface $team_invitation */
    $team_invitation = $this->entity;

    // Reset the status and the expiry date.
    $team_invitation->setStatus(TeamInvitationInterface::STATUS_PENDING);
    $days = $this->config('apigee_edge_teams.team_settings')->get('team_invitation_expiry_days');
    $team_invitation->setExpiryTime($this->time->getCurrentTime() + (24 * 60 * 60 * (int) $days));
    $team_invitation->save();

    if ($this->teamInvitationNotifier->sendNotificationsFor($team_invitation)) {
      $this->messenger()->addMessage($this->t('The invitation to join the %team team has been resent to %recipient.', [
        '%team' => $team_invitation->getTeam()->label(),
        '%recipient' => $team_invitation->getRecipient(),
      ]));
    }

    $form_state->setRedirectUrl($this->team->toUrl('members'));
  }

}
