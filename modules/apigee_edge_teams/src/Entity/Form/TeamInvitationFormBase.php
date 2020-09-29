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

use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a base class for updating status for a team_invitation.
 */
abstract class TeamInvitationFormBase extends ContentEntityConfirmFormBase {

  /**
   * The team_invitaion entity.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface
   */
  protected $entity;

  /**
   * If set to TRUE an expired message is shown if team_invitation is expired.
   *
   * @var bool
   */
  protected $handleExpired = FALSE;

  /**
   * The team.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $team;

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->team->toUrl('members');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Not now');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, TeamInterface $team = NULL) {
    $this->team = $team;

    if ($this->handleExpired && $this->entity->isExpired()) {
      return [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [
            $this->t('This invitation to join %team @team has expired. Please request a new one.', [
              '%team' => $this->team->label(),
              '@team' => mb_strtolower($this->entity->getTeam()->getEntityType()->getSingularLabel()),
            ]),
          ],
        ],
      ];
    };

    return parent::buildForm($form, $form_state);
  }

}
