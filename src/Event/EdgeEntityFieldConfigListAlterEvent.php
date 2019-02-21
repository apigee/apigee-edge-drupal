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

namespace Drupal\apigee_edge\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Triggered when an Apigee Edge entity's field config UI gets built.
 *
 * @see \Drupal\apigee_edge\Controller\EdgeEntityFieldConfigListController
 * @see \Drupal\apigee_edge\Routing\EdgeEntityFieldConfigListRouteSubscriber
 */
class EdgeEntityFieldConfigListAlterEvent extends Event {

  /**
   * Event id.
   *
   * @var string
   */
  public const EVENT_NAME = 'apigee_edge.field_config_list_alter';

  /**
   * The entity type id.
   *
   * @var string
   */
  private $entityType;

  /**
   * Page render array.
   *
   * @var array
   */
  private $page;

  /**
   * AppFieldConfigListAlterEvent constructor.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param array $page
   *   The page render array.
   */
  public function __construct(string $entity_type, array &$page) {
    $this->entityType = $entity_type;
    $this->page = $page;
  }

  /**
   * Returns the entity type.
   *
   * @return string
   *   The entity type.
   */
  public function getEntityType(): string {
    return $this->entityType;
  }

  /**
   * Returns the page render array by reference.
   *
   * @return array
   *   The page render array.
   */
  public function &getPage(): array {
    return $this->page;
  }

}
