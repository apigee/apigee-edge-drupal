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

use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\apigee_edge_teams\Entity\TeamInvitationInterface;
use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add team members form.
 */
class AddTeamMembersForm extends TeamMembersFormBase {

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  protected $teamMembershipManager;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The team invitation storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamInvitationStorageInterface
   */
  protected $teamInvitationStorage;

  /**
   * AddTeamMemberForms constructor.
   *
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(TeamMembershipManagerInterface $team_membership_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type_manager);

    $this->teamMembershipManager = $team_membership_manager;
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->teamInvitationStorage = $entity_type_manager->getStorage('team_invitation');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge_teams.team_membership_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_teams_add_team_member_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, TeamInterface $team = NULL) {
    $this->team = $team;
    $role_options = $this->getRoleOptions();

    $form['developers'] = [
      '#title' => $this->t('Developers'),
      '#description' => $this->t('Enter the email of one or more developers to invite them to the @team, separated by comma.', [
        '@team' => mb_strtolower($this->team->getEntityType()->getSingularLabel()),
      ]),
      '#type' => 'textarea',
      '#required' => TRUE,
    ];

    $form['team_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#options' => $role_options,
      '#multiple' => TRUE,
      '#required' => FALSE,
    ];

    // Special handling for the inevitable team member role.
    $form['team_roles'][TeamRoleInterface::TEAM_MEMBER_ROLE] = [
      '#default_value' => TRUE,
      '#disabled' => TRUE,
    ];

    $form['team_roles']['description'] = [
      '#markup' => $this->t('Assign one or more roles to <em>all developers</em> that you selected in %team_label @team.', [
        '%team_label' => $this->team->label(),
        '@team' => mb_strtolower($this->team->getEntityType()->getSingularLabel()),
      ]),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Invite members'),
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $emails = array_map('trim', explode(',', $form_state->getValue('developers', '')));
    $members = $this->teamMembershipManager->getMembers($this->team->id());
    $already_members = array_unique(array_intersect($emails, $members));

    // Validate existing members.
    if (count($already_members)) {
      $form_state->setErrorByName('developers', $this->formatPlural(count($already_members), 'The following developer is already a member of the @team: %developers.', 'The following developers are already members of the @team: %developers.', [
        '%developers' => implode(', ', $already_members),
        '@team' => mb_strtolower($this->team->getEntityType()->getSingularLabel()),
      ]));
    }

    // Validate pending invitations.
    $invites = array_diff($emails, $members);
    $has_invitation = [];
    foreach ($invites as $invite) {
      $pending_invitations = array_filter($this->teamInvitationStorage->loadByRecipient($invite, $this->team->id()), function (TeamInvitationInterface $team_invitation) {
        return $team_invitation->isPending();
      });

      if (count($pending_invitations)) {
        $has_invitation[] = $invite;
      }
    }

    $has_invitation = array_unique($has_invitation);
    if (count($has_invitation)) {
      $form_state->setErrorByName('developers', $this->formatPlural(count($has_invitation), 'The following developer has already been invited to the @team: %developers.', 'The following developers have already been invited to the @team: %developers.', [
        '%developers' => implode(', ', $has_invitation),
        '@team' => mb_strtolower($this->team->getEntityType()->getSingularLabel()),
      ]));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $emails = array_map('trim', explode(',', $form_state->getValue('developers', '')));
    $selected_roles = $this->filterSelectedRoles($form_state->getValue('team_roles', []));

    // Add default member role.
    $selected_roles = [TeamRoleInterface::TEAM_MEMBER_ROLE => TeamRoleInterface::TEAM_MEMBER_ROLE] + $selected_roles;

    // Create an invitation for each email.
    foreach ($emails as $email) {
      $this->teamInvitationStorage->create([
        'team' => ['target_id' => $this->team->id()],
        'team_roles' => array_values(array_map(function (string $role) {
          return ['target_id' => $role];
        }, $selected_roles)),
        'recipient' => $email,
      ])->save();
    }

    $context = [
      '@developers' => implode(', ', $emails),
      '@team' => $this->team->label(),
      '@team_label' => mb_strtolower($this->team->getEntityType()->getSingularLabel()),
    ];

    $this->messenger()->addStatus($this->formatPlural(count($emails),
      $this->t('The following developer has been invited to the @team @team_label: @developers.', $context),
      $this->t('The following developers have been invited to the @team @team_label: @developers.', $context
    )));

    $form_state->setRedirectUrl($this->team->toUrl('members'));
  }

}
