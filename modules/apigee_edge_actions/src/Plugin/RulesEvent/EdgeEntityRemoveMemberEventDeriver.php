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

namespace Drupal\apigee_edge_actions\Plugin\RulesEvent;

use Drupal\apigee_edge\Entity\EdgeEntityTypeInterface;
use Drupal\apigee_edge_teams\Entity\TeamInterface;

/**
 * Deriver for Edge entity remove_member events.
 */
class EdgeEntityRemoveMemberEventDeriver extends EdgeEntityEventDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getLabel(EdgeEntityTypeInterface $entity_type): string {
    return $this->t('After removing a team member');
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypes(): array {
    // Filter out non team entity types.
    return array_filter(parent::getEntityTypes(), function (EdgeEntityTypeInterface $entity_type) {
      return $entity_type->entityClassImplements(TeamInterface::class);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(EdgeEntityTypeInterface $entity_type): array {
    $context = parent::getContext($entity_type);

    // Add the team member to the context.
    $context['member'] = [
      'type' => 'entity:user',
      'label' => $this->t('Member'),
    ];

    return $context;
  }

}
