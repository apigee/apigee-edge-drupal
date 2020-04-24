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

use Drupal\apigee_edge\Entity\ListBuilder\EdgeEntityListBuilder;
use Drupal\apigee_edge\Exception\RuntimeException;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an implementation of an Apigee Edge entity type and its metadata.
 */
class EdgeEntityType extends EntityType implements EdgeEntityTypeInterface {

  /**
   * The FQCN of the query class used for this entity.
   *
   * @var string
   */
  protected $query_class = 'Drupal\apigee_edge\Entity\Query\Query';

  /**
   * Name of the config object that contains entity label overrides.
   *
   * @var string
   */
  protected $config_with_labels;

  /**
   * EdgeEntityType constructor.
   *
   * @param array $definition
   *   An array of values from the annotation.
   *
   * @throws \Drupal\Core\Entity\Exception\EntityTypeIdLengthException
   *   Thrown when attempting to instantiate an entity type with too long ID.
   */
  public function __construct(array $definition) {
    parent::__construct($definition);
    // Some default settings for our entity types.
    $this->handlers += [
      'view_builder' => EdgeEntityViewBuilder::class,
      'list_builder' => EdgeEntityListBuilder::class,
      'route_provider' => [
        'html' => EdgeEntityRouteProvider::class,
      ],
    ];

    // Add entity type id to the list cache tags to help easier cache
    // invalidation.
    $this->list_cache_tags[] = $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $label = $this->getEntityLabelFromConfig('entity_label_singular');
    return empty($label) ? parent::getSingularLabel() : $label;
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
    $label = $this->getEntityLabelFromConfig('entity_label_plural');
    return empty($label) ? parent::getPluralLabel() : $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionLabel() {
    // We do not want to display "XY entities" as default collection label
    // rather "XYs".
    $label = $this->getEntityLabelFromConfig('entity_label_plural');
    $label = $label ?: parent::getCollectionLabel();

    return new TranslatableMarkup('@label', ['@label' => $label], [], $this->getStringTranslation());
  }

  /**
   * Returns the config object that contains the entity labels.
   *
   * Config object should define values for the following keys:
   *   - entity_label_singular
   *   - entity_label_plural.
   *
   * @return \Drupal\Core\Config\ImmutableConfig|null
   *   Config object.
   *
   * @throws \Drupal\apigee_edge\Exception\RuntimeException
   *   If the provided config object does not exists.
   */
  private function getConfigWithEntityLabels(): ?ImmutableConfig {
    if (empty($this->config_with_labels)) {
      return NULL;
    }

    $config = \Drupal::config($this->config_with_labels);
    if ($config->isNew()) {
      throw new RuntimeException("Config object called {$this->config_with_labels} does not exists.");
    }

    return $config;
  }

  /**
   * Returns entity label from config if exists.
   *
   * @param string $key
   *   The config object key. It starts with "entity_label_".
   *
   * @return string|null
   *   The entity label if the config and config key exists, null otherwise.
   */
  protected function getEntityLabelFromConfig(string $key): ?string {
    $logger = \Drupal::logger('apigee_edge');
    try {
      $config = $this->getConfigWithEntityLabels();
      if ($config) {
        $label = $config->get($key);
        if ($label === NULL) {
          $logger->warning('@class: The "@key" has not been found in @config config object for "@entity_type" entity.', [
            '@class' => get_class($this),
            '@entity_type' => $this->id,
            '@key' => $key,
            '@config' => $this->config_with_labels ?? "apigee_edge.{$this->id}_settings",
          ]);
        }

        return $label;
      }
    }
    catch (RuntimeException $exception) {
      // Just catch it, do not log it, because this could generate invalid
      // log entries when the module is uninstalled.
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeys() {
    $keys = parent::getKeys();
    // If id definition is missing from the entity annotation try to set it up
    // automatically otherwise things gets broken, like entity reference fields.
    if (!isset($keys['id'])) {
      $rc = new \ReflectionClass($this->getClass());
      // SDK entities can tell their primary id property.
      $method = 'idProperty';
      if ($rc->hasMethod($method)) {
        $rm = $rc->getMethod($method);
        $keys['id'] = $rm->invoke(NULL, $method);
      }
    }
    return $keys;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryClass(): string {
    return $this->query_class;
  }

}
