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

use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Entity\EdgeEntityTypeInterface;

/**
 * Base deriver for Edge entity product events.
 */
abstract class EdgeEntityProductEventDeriverBase extends EdgeEntityEventDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getEntityTypes(): array {
    // Filter out non app entity types.
    // API Credential is not an entity type so we use App instead.
    return array_filter(parent::getEntityTypes(), function (EdgeEntityTypeInterface $entity_type) {
      return $entity_type->entityClassImplements(AppInterface::class);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(EdgeEntityTypeInterface $entity_type): array {
    $context = parent::getContext($entity_type);

    // The api_product entity type is not fieldable hence does not support typed
    // data. We have to add the attributes individually here.
    $context['api_product_name'] = [
      'type' => 'string',
      'label' => $this->t('Name'),
    ];
    $context['api_product_display_name'] = [
      'type' => 'string',
      'label' => $this->t('Display name'),
    ];

    return $context;
  }

}
