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

namespace Drupal\apigee_edge_teams\Form;

use Drupal\apigee_edge\Entity\DeveloperInterface;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;

/**
 * Edit team member form.
 */
class EditTeamMemberForm extends TeamMembersFormBase {

  /**
   * The developer from the route.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_teams_edit_team_member_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, TeamInterface $team = NULL, DeveloperInterface $developer = NULL) {
    $this->team = $team;
    $this->developer = $developer;
    $role_options = $this->getRoleOptions();

    $team_member_roles = $this->teamMemberRoleStorage->loadByDeveloperAndTeam($developer->getOwner(), $team);
    if ($team_member_roles) {
      $current_role_options = array_keys($team_member_roles->getTeamRoles());
    }
    else {
      $current_role_options = [];
    }
    // Add TEAM_MEMBER_ROLE to current role options so it's always displayed.
    $current_role_options[] = TeamRoleInterface::TEAM_MEMBER_ROLE;

    $form['team_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#options' => $role_options,
      '#default_value' => $current_role_options,
      '#multiple' => TRUE,
      '#required' => FALSE,
    ];

    // Special handling for the inevitable team member role.
    $form['team_roles'][TeamRoleInterface::TEAM_MEMBER_ROLE] = [
      '#default_value' => TRUE,
      '#disabled' => TRUE,
    ];

    $form['team_roles']['description'] = [
      '#markup' => $this->t('Modify roles of %developer in the %team_label @team.', [
        '%developer' => $this->developer->getOwner()->label(),
        '%team_label' => $this->team->label(),
        '@team' => mb_strtolower($this->team->getEntityType()->getSingularLabel()),
      ]),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
      ],
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#attributes' => ['class' => ['button']],
        '#url' => $this->team->toUrl('members'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $logger = $this->logger('apigee_edge_teams');

    $selected_roles = $this->filterSelectedRoles($form_state->getValue('team_roles', []));
    $new_roles = array_diff($selected_roles, $form['team_roles']['#default_value']);
    $removed_roles = array_diff($form['team_roles']['#default_value'], $selected_roles);
    $success = TRUE;

    try {
      if ($new_roles) {
        $this->teamMemberRoleStorage->addTeamRoles($this->developer->getOwner(), $this->team, $new_roles);
      }
      if ($removed_roles) {
        $this->teamMemberRoleStorage->removeTeamRoles($this->developer->getOwner(), $this->team, $removed_roles);
      }
    }
    catch (\Exception $exception) {
      $success = FALSE;

      $context = [
        '%developer' => $this->developer->getEmail(),
        '%team_id' => $this->team->id(),
      ];
      $context += Error::decodeException($exception);
      $logger->error('Failed to modify %developer developer roles in %team_id team. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
    }

    if ($success) {
      $this->messenger()->addStatus($this->t('Changes successfully saved.'));
    }
    else {
      $this->messenger()->addWarning($this->t('There was an error meanwhile saving the changes.'));
    }

  }

}
