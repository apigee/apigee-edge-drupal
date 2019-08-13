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
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add team members form.
 */
class AddTeamMembersForm extends FormBase {

  /**
   * The team from the route.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $team;

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
   * Team role storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamRoleStorageInterface
   */
  protected $teamRoleStorage;

  /**
   * Team member role storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorage
   */
  protected $teamMemberRoleStorage;

  /**
   * AddTeamMemberForms constructor.
   *
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(TeamMembershipManagerInterface $team_membership_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->teamMembershipManager = $team_membership_manager;
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->teamRoleStorage = $entity_type_manager->getStorage('team_role');
    $this->teamMemberRoleStorage = $entity_type_manager->getStorage('team_member_role');
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

    $role_options = array_reduce($this->teamRoleStorage->loadMultiple(), function (array $carry, TeamRoleInterface $role) {
      if ($role->id() !== TeamRoleInterface::TEAM_MEMBER_ROLE) {
        $carry[$role->id()] = $role->label();
      }
      return $carry;
    }, []);

    $form['developers'] = [
      '#title' => $this->t('Developers'),
      '#description' => $this->t('Add one or more developers to the @team.', ['@team' => $this->team->getEntityType()->getLowercaseLabel()]),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#tags' => TRUE,
      '#required' => TRUE,
      '#selection_handler' => 'apigee_edge_teams:team_members',
      '#selection_settings' => [
        'match_operator' => 'STARTS_WITH',
        'filter' => ['team' => $this->team->id()],
      ],
    ];

    $form['team_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#options' => $role_options,
      '#multiple' => TRUE,
      '#required' => FALSE,
    ];
    $form['team_roles']['description'] = [
      '#markup' => $this->t('Assign one or more roles to <em>all developers</em> that you selected in %team_label @team.', ['%team_label' => $this->team->label(), '@team' => $this->team->getEntityType()->getLowercaseLabel()]),
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $logger = $this->logger('apigee_edge_teams');
    // Collect user ids from submitted values.
    $uids = array_map(function (array $item) {
      return $item['target_id'];
    }, $form_state->getValue('developers', []));

    // Collect email addresses.
    /** @var array $developer_emails */
    $developer_emails = array_reduce($this->userStorage->loadMultiple($uids), function ($carry, UserInterface $item) {
      $carry[$item->id()] = $item->getEmail();
      return $carry;
    }, []);

    $context = [
      '@developers' => implode('', $developer_emails),
      '@team' => $this->team->getEntityType()->getLowercaseLabel(),
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
        $this->t('Failed to add developer to the @team.', $context),
        $this->t('Failed to add developers to the @team.', $context
        )));
      $logger->error('Failed to add developers to %team_id team. Developers: @developers. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
    }

    if ($success) {
      $this->messenger()->addStatus($this->formatPlural(count($developer_emails),
        $this->t('Developer successfully added to the @team.', $context),
        $this->t('Developers successfully added to the @team.', $context
        )));
      $form_state->setRedirectUrl($this->team->toUrl('members'));

      if (($selected_roles = array_filter($form_state->getValue('team_roles', [])))) {
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
