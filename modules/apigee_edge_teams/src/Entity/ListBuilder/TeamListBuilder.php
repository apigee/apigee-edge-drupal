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

namespace Drupal\apigee_edge_teams\Entity\ListBuilder;

use Drupal\apigee_edge\Element\StatusPropertyElement;
use Drupal\apigee_edge\Entity\ListBuilder\EdgeEntityListBuilder;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * General entity listing builder for teams.
 */
class TeamListBuilder extends EdgeEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $headers = [];

    $headers['name'] = [
      'data' => $this->t('@entity_type name', [
        '@entity_type' => ucfirst($this->entityType->getSingularLabel()),
      ]),
      'specifier' => 'displayName',
      'field' => 'displayName',
      'sort' => 'desc',
    ];
    $headers['status'] = [
      'data' => $this->t('Status'),
      'specifier' => 'status',
      'field' => 'status',
    ];

    return $headers + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $entity */
    $row['name']['data'] = $entity->toLink()->toRenderable();
    $row['status']['data'] = [
      '#type' => 'status_property',
      '#value' => $entity->getStatus(),
      '#indicator_status' => $entity->getStatus() === TeamInterface::STATUS_ACTIVE ? StatusPropertyElement::INDICATOR_STATUS_OK : StatusPropertyElement::INDICATOR_STATUS_ERROR,
    ];
    return $row + parent::buildRow($entity);
  }

}
