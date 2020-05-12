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
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Remove team members from.
 */
class RemoveTeamMemberForm extends ConfirmFormBase {

  /**
   * The team from the route.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $team;

  /**
   * The developer from the route.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * The team entity type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $teamEntityType;

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
   * RemoveTeamMemberForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TeamMembershipManagerInterface $team_membership_manager) {
    $this->teamEntityType = $entity_type_manager->getDefinition('team');
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->teamMembershipManager = $team_membership_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('apigee_edge_teams.team_membership_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_teams_remove_team_member_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, TeamInterface $team = NULL, DeveloperInterface $developer = NULL) {
    $this->team = $team;
    $this->developer = $developer;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure that you would like to remove %developer from the @team?', [
      '%developer' => $this->getDeveloperLabel(),
      '@team' => mb_strtolower($this->teamEntityType->getSingularLabel()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->team->toUrl('members');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!in_array($this->developer->getEmail(), $this->teamMembershipManager->getMembers($this->team->id()))) {
      $form_state->setError($form, $this->t('%developer developer is not member of the %team_name @team.', [
        '%developer' => $this->developer->label(),
        '%team_name' => $this->team->getDisplayName(),
        '@team' => mb_strtolower($this->teamEntityType->getSingularLabel()),
      ]));
      $form_state->setRedirectUrl($this->getCancelUrl());
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $context = [
      '%developer' => $this->getDeveloperLabel(),
      '%developer_mail' => $this->developer->getEmail(),
      '@team' => mb_strtolower($this->teamEntityType->getSingularLabel()),
      '%team_id' => $this->team->id(),
    ];

    $success = FALSE;
    try {
      $this->teamMembershipManager->removeMembers($this->team->id(), [$this->developer->getEmail()]);
      $success = TRUE;
    }
    catch (\Exception $exception) {
      $context += Error::decodeException($exception);
      $this->messenger()->addError($this->t('Failed to remove %developer developer from the @team. Please try again.', $context));
      $this->logger('apigee_edge_teams')->error('Failed to remove %developer_mail developer from %team_id @team. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
    }

    if ($success) {
      $this->messenger()->addStatus($this->t('%developer successfully removed from the @team.', $context));
    }
  }

  /**
   * Returns the label for a developer.
   *
   * @return string
   *   The label for a developer.
   */
  protected function getDeveloperLabel() {
    $developer_label = $this->developer->getEmail();
    // The developer that we would like to remove from the team may not have
    // a Drupal user yet (the two system is out of sync). To resolve this
    // possible problem we always try to display the label of the user first
    // and we fallback to the developer's email if necessary. We only display
    // the email here because this is what a user could see on the team member
    // list as well.
    // @see \Drupal\apigee_edge_teams\Controller\TeamMembersList::buildRow()
    $users = $this->userStorage->loadByProperties(['mail' => $this->developer->getEmail()]);
    if (!empty($users)) {
      /** @var \Drupal\user\UserInterface $user */
      $user = reset($users);
      $developer_label = $user->label();
    }
    return $developer_label;
  }

}
