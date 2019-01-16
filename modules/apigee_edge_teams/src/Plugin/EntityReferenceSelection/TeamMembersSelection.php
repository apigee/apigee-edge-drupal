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

namespace Drupal\apigee_edge_teams\Plugin\EntityReferenceSelection;

use Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a team member entity selection plugin.
 *
 * This user entity selection plugin excludes users from the list
 * who do not have a developer account on Apigee Edge and who are already
 * member of a given team.
 *
 * @EntityReferenceSelection(
 *   id = "apigee_edge_teams:team_members",
 *   label = @Translation("Team member selection"),
 *   entity_types = {"user"},
 *   group = "apigee_edge_teams",
 *   weight = 1
 * )
 */
class TeamMembersSelection extends UserSelection {

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  private $teamMembershipManager;

  /**
   * The developer controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface
   */
  private $developerController;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * TeamMembersSelection constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager.
   * @param \Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface $developer_controller
   *   The developer controller service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, Connection $connection, TeamMembershipManagerInterface $team_membership_manager, DeveloperControllerInterface $developer_controller, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_manager, $module_handler, $current_user, $connection);
    $this->teamMembershipManager = $team_membership_manager;
    $this->developerController = $developer_controller;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('database'),
      $container->get('apigee_edge_teams.team_membership_manager'),
      $container->get('apigee_edge.controller.developer'),
      $container->get('logger.channel.apigee_edge_teams')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $conf = parent::defaultConfiguration();
    $conf['filter']['team'] = NULL;
    return $conf;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $conf = parent::getConfiguration();
    // Anonymous user should never be displayed because it can not be assigned
    // to a team as member.
    $conf['include_anonymous'] = FALSE;
    return $conf;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['include_anonymous']['#access'] = FALSE;

    $team = $config['filter']['team'];
    if ($team !== NULL) {
      // Default value should be an entity object if team is set.
      // (If the team could not be loaded then the value remains null.)
      $team = $this->entityManager->getStorage('team')->load($team);
    }

    $form['filter']['team'] = [
      '#title' => $this->t('Exclude team members of this team'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'team',
      '#description' => $this->t('Exclude users who are already member of the selected team.'),
      '#default_value' => $team,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $config = $this->getConfiguration();
    $team_name = $config['filter']['team'];

    // Exclude those users from the list who does not have a developer
    // account on Apigee Edge.
    // ("Add or Update Company Developers" API call would fail anyway if the
    // list of new members would contain any email address that does not
    // belong to an existing developer.)
    try {
      $existing_developers = $this->developerController->getEntityIds();
    }
    catch (\Exception $exception) {
      // If list of existing developers (email addresses) could not be
      // retrieved then return an empty list.
      $query->condition('mail', 0);
      $context = Error::decodeException($exception);
      $this->logger->error("Unable to retrieve list of developer email addresses from Apigee Edge. @message %function (line %line of %file). <pre>@backtrace_string</pre>", $context);
      return $query;
    }

    if (empty($existing_developers)) {
      // If list of existing developers is empty then return an empty list
      // too. (Developers should be synced.)
      $query->condition('mail', 0);
    }
    else {
      $query->condition('mail', $existing_developers, 'IN');
    }

    // Do not display users who are already member of the team.
    if ($team_name) {
      try {
        $team_members = $this->teamMembershipManager->getMembers($team_name);
        if (!empty($team_members)) {
          $query->condition('mail', $team_members, 'NOT IN');
        }
      }
      catch (\Exception $exception) {
        // If team members could not be retrieved return an empty list.
        $query->condition('mail', 0);
        $context = [
          '%team' => $team_name,
        ];
        $context += Error::decodeException($exception);
        $this->logger->error("Unable to retrieve list of %team team from Apigee Edge. @message %function (line %line of %file). <pre>@backtrace_string</pre>", $context);
      }

    }

    return $query;
  }

}
