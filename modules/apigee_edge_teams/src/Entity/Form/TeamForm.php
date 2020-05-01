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

namespace Drupal\apigee_edge_teams\Entity\Form;

use Apigee\Edge\Exception\ApiException;
use Drupal\apigee_edge\Entity\Form\EdgeEntityFormInterface;
use Drupal\apigee_edge\Entity\Form\FieldableEdgeEntityForm;
use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Error;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the team create/edit forms.
 */
class TeamForm extends FieldableEdgeEntityForm implements EdgeEntityFormInterface {

  /**
   * Admin email attribute name.
   */
  const ADMIN_EMAIL_ATTRIBUTE = 'ADMIN_EMAIL';

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  protected $teamMembershipManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * TeamForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TeamMembershipManagerInterface $team_membership_manager, AccountProxyInterface $current_user, LoggerChannelInterface $logger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->teamMembershipManager = $team_membership_manager;
    $this->currentUser = $current_user;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('apigee_edge_teams.team_membership_manager'),
      $container->get('current_user'),
      $container->get('logger.channel.apigee_edge_teams')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $team */
    $team = parent::buildEntity($form, $form_state);

    // ADMIN_EMAIL_ATTRIBUTE is a required field for monetization.
    // We add to any team to make sure team creation works for mint orgs even
    // if they do not enable the m10n teams module.
    $team->setAttribute(static::ADMIN_EMAIL_ATTRIBUTE, $this->currentUser->getEmail());

    return $team;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $team */
    $team = $this->entity;

    $form['name'] = [
      '#title' => $this->t('Internal name'),
      '#type' => 'machine_name',
      '#machine_name' => [
        'source' => ['displayName', 'widget', 0, 'value'],
        'label' => $this->t('Internal name'),
        'exists' => [$this, 'exists'],
      ],
      '#disabled' => !$team->isNew(),
      '#default_value' => $team->id(),
    ];

    return $form;
  }

  /**
   * Checks if a team already exists with the same name.
   *
   * @param string $name
   *   Team name.
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return bool
   *   TRUE if the team exists, else FALSE.
   */
  public function exists(string $name, array $element, FormStateInterface $form_state): bool {
    if ($name === '') {
      return FALSE;
    }

    $query = $this->entityTypeManager->getStorage('team')->getQuery()->condition('name', $name);

    return (bool) $query->count()->execute();
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $team */
    $team = $this->entity;
    $label = mb_strtolower($this->entityTypeManager->getDefinition('team')->getSingularLabel());
    $actions = parent::actions($form, $form_state);

    if ($team->isNew()) {
      $actions['submit']['#value'] = $this->t('Add @team', [
        '@team' => $label,
      ]);
    }
    else {
      $actions['submit']['#value'] = $this->t('Save @team', [
        '@team' => $label,
      ]);
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $team */
    $team = $this->entity;
    $was_new = $team->isNew();
    $result = parent::save($form, $form_state);

    if ($was_new) {
      try {
        $this->teamMembershipManager->addMembers($team->id(), [$this->currentUser->getEmail()]);

        try {
          /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorageInterface $team_member_role_storage */
          $team_member_role_storage = $this->entityTypeManager->getStorage('team_member_role');
          $team_member_role_storage->addTeamRoles($this->currentUser(), $team, [TeamRoleInterface::TEAM_ADMIN_ROLE]);
        }
        catch (\Exception $exception) {
          $admin_role = $this->entityTypeManager->getStorage('team_role')->load(TeamRoleInterface::TEAM_ADMIN_ROLE);
          $context = [
            '%email' => $this->currentUser->getEmail(),
            '%team_name' => $team->label(),
            '%admin_role' => $admin_role->label(),
            '@team' => mb_strtolower($this->entityTypeManager->getDefinition('team')->getSingularLabel()),
            'link' => $team->toLink()->toString(),
          ];
          $this->messenger()->addError($this->t('Failed to grant %admin_role team role in %team_name @team.', $context));
          $context += Error::decodeException($exception);
          $this->logger->error('Failed to add creator of the team (%email) as team administrator to the team. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
        }
      }
      catch (ApiException $exception) {
        $this->messenger()->addError($this->t('Failed to register team membership in %team_name @team.', [
          '%team_name' => $team->label(),
          '@team' => mb_strtolower($this->entityTypeManager->getDefinition('team')->getSingularLabel()),
        ]));
        $context = [
          '%email' => $this->currentUser->getEmail(),
          'link' => $team->toLink()->toString(),
        ];
        $context += Error::decodeException($exception);
        $this->logger->error('Unable to add creator of the team (%email) as member to the team. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
      }

    }
    $form_state->setRedirectUrl($team->toUrl('collection'));

    return $result;
  }

}
