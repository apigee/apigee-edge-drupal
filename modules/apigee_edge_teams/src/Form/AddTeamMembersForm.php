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
   * The team entity type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $teamEntityType;

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
    $this->teamEntityType = $entity_type_manager->getDefinition('team');
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

    $form['developers'] = [
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
    // Collect user ids from submitted values.
    $uids = array_map(function (array $item) {
      return $item['target_id'];
    }, $form_state->getValue('developers', []));

    // Collect email addresses.
    $developer_emails = array_reduce($this->userStorage->loadMultiple($uids), function ($carry, UserInterface $item) {
      $carry[$item->id()] = $item->getEmail();
      return $carry;
    }, []);

    $context = [
      '@developers' => implode($developer_emails),
      '@team' => $this->teamEntityType->getLowercaseLabel(),
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
        $this->t('Failed to add developer to the @team.', $context
        )));
      $this->logger('apigee_edge_teams')->error('Failed to add developers to %team_id @team. Developers: @developers. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
    }

    if ($success) {
      $this->messenger()->addStatus($this->formatPlural(count($developer_emails),
        $this->t('Developer successfully added to the @team.', $context),
        $this->t('Developers successfully added to the @team.', $context
        )));
      $form_state->setRedirectUrl($this->team->toUrl('members'));
    }
  }

}
