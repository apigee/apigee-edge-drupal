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

namespace Drupal\apigee_edge_teams\Plugin\Menu;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a local task that list Team Apps shared by a team.
 */
final class TeamAppsLocalTask extends LocalTaskDefault implements ContainerFactoryPluginInterface {

  /**
   * The Team App entity type definition.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamAppStorageInterface|\Drupal\Core\Entity\EntityTypeInterface
   */
  private $teamAppDefinition;

  /**
   * TeamAppsLocalTask constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeInterface $team_app_definition
   *   The Team App entity definition.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeInterface $team_app_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->teamAppDefinition = $team_app_definition;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getDefinition('team_app')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    // Display the current plural label of the Team app entity.
    return $this->teamAppDefinition->getPluralLabel();
  }

}
