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
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;

/**
 * Provides an interface for Apigee Edge entity event deriver.
 */
interface EdgeEntityEventDeriverInterface extends ContainerDeriverInterface {

  /**
   * Returns the event's label. Example: 'After saving a new App'.
   *
   * @param \Drupal\apigee_edge\Entity\EdgeEntityTypeInterface $entity_type
   *   The Apigee Edge entity type.
   *
   * @return string
   *   The event's label.
   */
  public function getLabel(EdgeEntityTypeInterface $entity_type): string;

  /**
   * Returns an array of event context.
   *
   * @param \Drupal\apigee_edge\Entity\EdgeEntityTypeInterface $entity_type
   *   The Apigee Edge entity type.
   *
   * @return array
   *   An array of event context.
   */
  public function getContext(EdgeEntityTypeInterface $entity_type): array;

  /**
   * Returns an array of entity types that are compatible to this event.
   *
   * @return array
   *   An array of Edge entity types.
   */
  public function getEntityTypes(): array;

}
