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
   */
  protected $teamMembershipManager;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

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
      '#description' => $this->t('Enter the email of one or more developers to add them to the @team, separated by comma.', [
        '@team' => mb_strtolower($this->team->getEntityType()->getSingularLabel()),
      ]),
      '#type' => 'textfield',
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
        '#value' => $this->t('Add members'),
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
    $logger = $this->logger('apigee_edge_teams');

    // Collect user accounts from submitted values.
    list($developerAccounts, $notFound) = $this->getAccountsFromEmails($form_state->getValue('developers', ''));

    if ($notFound) {
      $this->messenger()->addWarning($this->t("Could not add developers to the @team because they don't yet have an account: @devs", [
        '@team' => mb_strtolower($this->team->getEntityType()->getSingularLabel()),
        '@devs' => implode(', ', $notFound),
      ]));
    }

    if (empty($developerAccounts)) {
      return;
    }

    // Collect email addresses.
    /** @var array $developer_emails */
    $developer_emails = array_reduce($developerAccounts, function ($carry, UserInterface $item) {
      $carry[$item->id()] = $item->getEmail();
      return $carry;
    }, []);

    $context = [
      '@developers' => implode(', ', $developer_emails),
      '@team' => mb_strtolower($this->team->getEntityType()->getSingularLabel()),
      '%team_id' => $this->team->id(),
    ];

    $success = FALSE;
    try {
      $this->teamMembershipManager->addMembers($this->team->id(), $developer_emails);
      $success = TRUE;
    }
    catch (\Exception $exception) {
      $context += Error::decodeException($exception);
      // The error message returned by Apigee Edge is not really useful if
      // multiple developers selected therefore we should not display that to
      // the user.
      $this->messenger()->addError($this->formatPlural(count($developer_emails),
        $this->t('Failed to add developer to the @team: @developers', $context),
        $this->t('Failed to add developers to the @team: @developers', $context
        )));
      $logger->error('Failed to add developers to %team_id team. Developers: @developers. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
    }

    if ($success) {
      $this->messenger()->addStatus($this->formatPlural(count($developer_emails),
        $this->t('Developer successfully added to the @team: @developers', $context),
        $this->t('Developers successfully added to the @team: @developers', $context
        )));
      $form_state->setRedirectUrl($this->team->toUrl('members'));

      if (($selected_roles = $this->filterSelectedRoles($form_state->getValue('team_roles', [])))) {
        /** @var \Drupal\user\UserInterface[] $users */
        $users = $this->userStorage->loadByProperties(['mail' => $developer_emails]);
        foreach ($users as $user) {
          $unsuccessful_message = $this->t('Selected roles could not be saved for %user developer.', [
            '%user' => $user->label(),
          ]);
          /** @var \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface $team_member_roles */
          $team_member_roles = $this->teamMemberRoleStorage->loadByDeveloperAndTeam($user, $this->team);
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
                'link' => $this->team->toLink($this->t('Members'), 'members')->toString(),
              ];
              $context += Error::decodeException($exception);
              $logger->error('Integrity check: %developer developer had a team member role entity with "%roles" team roles for %team_id team when it was added to the team. These roles could not been deleted automatically. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
              $this->messenger()->addWarning($unsuccessful_message);
              // Do not add new team roles to a developer existing team roles
              // that could not be deleted. Those must be manually reviewed.
              continue;
            }
          }

          try {
            $this->teamMemberRoleStorage->addTeamRoles($user, $this->team, $selected_roles);
          }
          catch (\Exception $exception) {
            $this->messenger()->addWarning($unsuccessful_message);
          }
        }
      }
    }
  }

}
