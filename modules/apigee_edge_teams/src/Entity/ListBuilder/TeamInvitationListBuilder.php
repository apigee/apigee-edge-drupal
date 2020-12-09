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

namespace Drupal\apigee_edge_teams\Entity\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder handler for team_invitation.
 */
class TeamInvitationListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface $entity */
    $operations = parent::getDefaultOperations($entity);

    if ($entity->isPending() && $entity->access('accept') && $entity->hasLinkTemplate('accept-form')) {
      $operations['accept'] = [
        'title' => $this->t('Accept'),
        'weight' => 100,
        'url' => $this->ensureDestination($entity->toUrl('accept-form'))->setRouteParameter('team', $entity->getTeam()->id()),
      ];
    }

    if ($entity->isPending() && $entity->access('decline') && $entity->hasLinkTemplate('decline-form')) {
      $operations['decline'] = [
        'title' => $this->t('Decline'),
        'weight' => 100,
        'url' => $this->ensureDestination($entity->toUrl('decline-form'))->setRouteParameter('team', $entity->getTeam()->id()),
      ];
    }

    if ($entity->access('resend') && $entity->hasLinkTemplate('resend-form')) {
      $operations['resend'] = [
        'title' => $this->t('Resend'),
        'weight' => 100,
        'url' => $this->ensureDestination($entity->toUrl('resend-form'))->setRouteParameter('team', $entity->getTeam()->id()),
      ];
    }

    /** @var \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface $entity */
    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        'title' => $this->t('Revoke'),
        'weight' => 100,
        'url' => $this->ensureDestination($entity->toUrl('delete-form'))->setRouteParameter('team', $entity->getTeam()->id()),
      ];
    }

    return $operations;
  }

}
