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
use Drupal\apigee_edge_teams\Entity\TeamRole;
use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add team members form.
 */
class AddTeamMembersForm extends TeamMembersFormBase {

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   *
   * @deprecated in apigee_edge_teams:8.x-1.12 and is removed from apigee_edge_teams:2.x
   */
  protected $teamMembershipManager;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   *
   * @deprecated in apigee_edge_teams:8.x-1.12 and is removed from apigee_edge_teams:2.x.
   */
  protected $userStorage;

  /**
   * The team invitation storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $invitationStorage;

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
    $this->invitationStorage = $entity_type_manager->getStorage('team_invitation');
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
   * Return an array of user UIDs given a list of emails.
   *
   * @param string $emails
   *   The emails, comma separated.
   *
   * @return array
   *   An array containing a first array of user accounts, and a second array of
   *   emails that have no account on the system.
   *
   * @deprecated in apigee_edge_teams:8.x-1.12 and is removed from apigee_edge_teams:2.x
   */
  protected function getAccountsFromEmails(string $emails): array {
    $developerEmails = [];
    $notFound = [];

    $emails = array_map('trim', explode(',', $emails));

    foreach ($emails as $email) {
      if ($account = user_load_by_mail($email)) {
        $developerEmails[$email] = $account;
      }
      else {
        $notFound[] = $email;
      }
    }

    return [$developerEmails, $notFound];
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
      $this->invitationStorage->create([
        'team' => ['target_id' => $this->team->id()],
        'team_roles' => array_values(array_map(function (string $role) {
          return ['target_id' => $role];
        }, $selected_roles)),
        'recipient' => $email,
      ])->save();
    }

    $context = [
      '@developers' => implode(', ', $emails),
      '@team' => mb_strtolower($this->team->getEntityType()->getSingularLabel()),
      '%team_id' => $this->team->id(),
    ];

    $this->messenger()->addStatus($this->formatPlural(count($emails),
      $this->t('The following developer has been invited to @team: @developers', $context),
      $this->t('The following developers have been invited to @team: @developers', $context
    )));

    $form_state->setRedirectUrl($this->team->toUrl('members'));
  }

}
