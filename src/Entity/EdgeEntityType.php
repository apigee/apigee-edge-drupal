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

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;

/**
 * Provides an implementation of an Edge entity type and its metadata.
 */
class EdgeEntityType extends EntityType implements EdgeEntityTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $definition) {
    parent::__construct($definition);
    // Some default settings for our entity types.
    $this->handlers += [
      'view_builder' => EntityViewBuilder::class,
      'list_builder' => EntityListBuilder::class,
      'route_provider' => [
        'html' => DefaultHtmlRouteProvider::class,
      ],
    ];

    $this->links += [
      'canonical' => "/{$this->id}/{{$this->id}}",
      'collection' => "/{$this->id}",
    ];
    // Add entity type id to the list cache tags to help easier cache
    // invalidation.
    $this->list_cache_tags[] = $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    if ($label = $this->getConfigWithEntityLabels()->get('entity_label_singular')) {
      return $label;
    }
    return parent::getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getSingularLabel() {
    return $this->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getPluralLabel() {
    if ($label = $this->getConfigWithEntityLabels()->get('entity_label_plural')) {
      return $label;
    }
    return parent::getPluralLabel();
  }

  /**
   * Returns the config object that contains the entity labels.
   *
   * Config object should define values for the following keys:
   *   - entity_label_singular
   *   - entity_label_plural.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   Config object.
   */
  protected function getConfigWithEntityLabels() : ImmutableConfig {
    return \Drupal::config("apigee_edge.{$this->id}_settings");
  }

}
