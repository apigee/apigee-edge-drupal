<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\apigee_edge_teams\Plugin\views\access;

use Drupal\apigee_edge_teams\Structure\TeamPermission as TeamPermissionEntity;
use Drupal\apigee_edge_teams\TeamPermissionHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin for team-permission-based access control.
 *
 * @ViewsAccess(
 *   id = "team_permission",
 *   title = @Translation("Team permission"),
 * )
 */
class TeamPermission extends AccessPluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * The team permission handler.
   *
   * @var \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface
   */
  protected $teamPermissionHandler;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * TeamPermission constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface $team_permission_handler
   *   The team permission handler.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TeamPermissionHandlerInterface $team_permission_handler, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->teamPermissionHandler = $team_permission_handler;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('apigee_edge_teams.team_permissions'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    if ((int) $account->id() === 1) {
      return TRUE;
    }

    $team = $this->routeMatch->getParameter('team');
    if (!$team) {
      return FALSE;
    }

    return in_array($this->options['permission'], $this->teamPermissionHandler->getDeveloperPermissionsByTeam($team, $account));
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_team_permission', $this->options['permission']);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['permission'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $options = (array_reduce($this->teamPermissionHandler->getPermissions(), function (array $options, TeamPermissionEntity $permission) {
      $options[$permission->getName()] = $permission->getLabel();
      return $options;
    }, []));

    $form['permission'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Team permission'),
      '#default_value' => $this->options['permission'],
      '#description' => $this->t('Only users with the selected permission for the current team will be able to access this display.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    $permissions = $this->teamPermissionHandler->getPermissions();
    if (isset($permissions[$this->options['permission']])) {
      return $permissions[$this->options['permission']]->getLabel();
    }

    return $this->t($this->options['permission']);
  }

}
