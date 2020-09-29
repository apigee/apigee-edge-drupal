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
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the accept form for team_invitation.
 */
class TeamInvitationAcceptForm extends TeamInvitationFormBase {

  /**
   * {@inheritdoc}
   */
  protected $handleExpired = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to join the %label @team?', [
      '%label' => $this->entity->getTeam()->label(),
      '@team' => mb_strtolower($this->entity->getTeam()->getEntityType()->getSingularLabel()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Accept invitation');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface $invitation */
    $invitation = $this->entity;
    $invitation->setStatus(TeamInvitationInterface::STATUS_ACCEPTED)->save();

    $this->messenger()->addMessage($this->t('You have accepted the invitation to join the %label @team.', [
      '%label' => $this->entity->getTeam()->label(),
      '@team' => mb_strtolower($this->entity->getTeam()->getEntityType()->getSingularLabel()),
    ]));

    $form_state->setRedirect('entity.team.collection');
  }

}
