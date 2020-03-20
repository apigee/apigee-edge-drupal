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

namespace Drupal\apigee_edge_actions;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Overrides apigee_edge services.
 */
class ApigeeEdgeActionsServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // Decorate the apigee_edge_teams.team_membership_manager service.
    // This cannot be done from "apigee_edge_actions.services.yml" because the
    // "apigee_edge_teams" module might not be enabled.
    if ($container->has('apigee_edge_teams.team_membership_manager')) {
      $container->register('apigee_edge_actions.team_membership_manager', TeamMembershipManager::class)
        ->setDecoratedService('apigee_edge_teams.team_membership_manager')
        ->setArguments([
          new Reference('apigee_edge_actions.team_membership_manager.inner'),
          new Reference('entity_type.manager'),
          new Reference('apigee_edge_teams.company_members_controller_factory'),
          new Reference('apigee_edge.controller.developer'),
          new Reference('apigee_edge.controller.cache.developer_companies'),
          new Reference('cache_tags.invalidator'),
          new Reference('logger.channel.apigee_edge_teams'),
          new Reference('event_dispatcher'),
        ]);
    }
  }

}
