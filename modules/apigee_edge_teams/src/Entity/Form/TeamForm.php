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
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Error;
use Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface;
use Drupal\apigee_edge\Entity\Form\EdgeEntityFormInterface;
use Drupal\apigee_edge\Entity\Form\FieldableEdgeEntityForm;
use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;
use Drupal\apigee_edge_teams\Form\TeamAliasForm;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * General form handler for the team create/edit forms.
 */
class TeamForm extends FieldableEdgeEntityForm implements EdgeEntityFormInterface {

  /**
   * Admin email attribute name.
   */
  const ADMIN_EMAIL_ATTRIBUTE = 'ADMIN_EMAIL';

  /**
   * Membership attribute name for admin email in appgroup.
   */
  const APPGROUP_ADMIN_EMAIL_ATTRIBUTE = '__apigee_reserved__developer_details';


  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  protected $teamMembershipManager;

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

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
   * The request service.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The organization controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface
   */
  protected $orgController;

  /**
   * The Team prefix value.
   *
   * @var string|null
   */
  private $team_prefix;

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
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TeamMembershipManagerInterface $team_membership_manager, AccountProxyInterface $current_user, LoggerChannelInterface $logger, OrganizationControllerInterface $org_controller, RequestStack $request_stack, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->teamMembershipManager = $team_membership_manager;
    $this->currentUser = $current_user;
    $this->logger = $logger;
    $this->orgController = $org_controller;
    $this->request = $request_stack->getCurrentRequest();
    $this->config = $config_factory->get('apigee_edge_teams.team_settings');
    // Prefixing the value to team name.
    // Configurable in team-settings.
    $this->team_prefix = !empty($this->config->get('team_prefix')) ? mb_strtolower($this->config->get('team_prefix')) : "";
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('apigee_edge_teams.team_membership_manager'),
      $container->get('current_user'),
      $container->get('logger.channel.apigee_edge_teams'),
      $container->get('apigee_edge.controller.organization'),
      $container->get('request_stack'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    if (str_contains($form_state->getValue('name'), $this->team_prefix) === FALSE) {
      // Update the team name with predefined prefix.
      $form_state->setValue('name', $this->team_prefix . $form_state->getValue('name'));
    }

    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $team */
    $team = parent::buildEntity($form, $form_state);

    // ADMIN_EMAIL_ATTRIBUTE is a required field for monetization.
    // We add to any team to make sure team creation works for mint orgs even
    // if they do not enable the m10n teams module.
    if ($this->orgController->isOrganizationApigeeX()) {
      global $base_url;
      // Channel url for a team.
      $team->setChannelUri($base_url . '/teams/' . $team->id());

      // ChannelId is configurable from Admin portal.
      $channelId = !empty($this->config->get('channelid')) ? $this->config->get('channelid') : TeamAliasForm::originalChannelId();
      $team->setChannelId(trim($channelId));
    }
    else {
      $team->setAttribute(static::ADMIN_EMAIL_ATTRIBUTE, $this->currentUser->getEmail());
    }
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
      '#title' => $this->t('Machine name'),
      '#type' => 'machine_name',
      '#machine_name' => [
        'source' => ['displayName', 'widget', 0, 'value'],
        'label' => $this->t('Machine name'),
        'exists' => [$this, 'exists'],
      ],
      '#field_prefix' => $team->isNew() ? $this->team_prefix : "",
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
    if (str_contains($name, $this->team_prefix) === FALSE) {
      // Update the team name with predefined prefix.
      $name = $this->team_prefix . $form_state->getValue('name');
    }
    // Only member with access can check if team exists.
    $query = $this->entityTypeManager->getStorage('team')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('name', $name);

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
        if (!$this->orgController->isOrganizationApigeeX()) {
          $this->teamMembershipManager->addMembers($team->id(), [
            $this->currentUser->getEmail(),
          ]);
        }

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
    $options = [];
    $query = $this->getRequest()->query;
    if ($query->has('destination')) {
      $options['query']['destination'] = $query->get('destination');
      $query->remove('destination');
    }
    // Redirecting user to team view page to manage the team members and apps.
    $form_state->setRedirectUrl($team->toUrl('canonical'));

    return $result;
  }

}
