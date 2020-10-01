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

namespace Drupal\apigee_edge_teams\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity\EntityPermissionProvider;

/**
 * Provides permission for team_invitation.
 */
class TeamInvitationPermissionProvider extends EntityPermissionProvider {

  /**
   * {@inheritdoc}
   */
  public function buildPermissions(EntityTypeInterface $entity_type) {
    $permissions['administer team_invitation'] = [
      'title' => $this->t('Administer team invitation settings'),
      'provider' => 'apigee_edge_teams',
      'restrict access' => TRUE,
    ];

    $permissions['accept own team invitation'] = [
      'title' => $this->t('Accept own team invitation'),
      'provider' => 'apigee_edge_teams',
    ];

    $permissions['accept any team invitation'] = [
      'title' => $this->t('Accept any team invitation'),
      'provider' => 'apigee_edge_teams',
      'restrict access' => TRUE,
    ];

    $permissions['decline own team invitation'] = [
      'title' => $this->t('Decline own team invitation'),
      'provider' => 'apigee_edge_teams',
    ];

    $permissions['decline any team invitation'] = [
      'title' => $this->t('Decline any team invitation'),
      'provider' => 'apigee_edge_teams',
      'restrict access' => TRUE,
    ];

    return $permissions;
  }

}
