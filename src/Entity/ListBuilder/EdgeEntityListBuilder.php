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

namespace Drupal\apigee_edge\Entity\ListBuilder;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default entity list builder for Apigee Edge entities.
 */
class EdgeEntityListBuilder extends EntityListBuilder {

  /**
   * The default display type.
   */
  const DEFAULT_DISPLAY_TYPE = 'default';

  /**
   * The view mode display type.
   */
  const VIEW_MODE_DISPLAY_TYPE = 'view_mode';

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * EdgeEntityListBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory = NULL) {
    parent::__construct($entity_type, $entity_type_manager->getStorage($entity_type->id()));
    $this->entityTypeManager = $entity_type_manager;

    if (!$config_factory) {
      $config_factory = \Drupal::service('config.factory');
    }

    $this->configFactory = $config_factory;
    // Disable pager for now for all Apigee Edge entities.
    $this->limit = 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  final protected function getEntityIds() {
    $query = $this->buildEntityIdQuery();
    return $query->execute();
  }

  /**
   * Builds an entity query used by entity listing.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   */
  protected function buildEntityIdQuery(): QueryInterface {
    $headers = $this->buildHeader();
    $query = $this->getStorage()->getQuery()
      // Provide support for table sorting by default.
      ->tableSort($headers);

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $settings = $this->getDisplaySettings();
    if ($this->usingDisplayType(static::VIEW_MODE_DISPLAY_TYPE)) {
      $build = $this->renderUsingViewMode($settings['view_mode']);
    }
    else {
      $build = parent::render();
    }

    // Add cache contexts.
    $build['#cache'] = [
      'contexts' => $this->entityType->getListCacheContexts(),
      'tags' => $this->entityType->getListCacheTags(),
      'max-age' => $this->getCacheMaxAge(),
    ];

    return $build;
  }

  /**
   * Renders a list of entities using the provided view mode.
   *
   * @param string $view_mode
   *   The view mode.
   *
   * @return array
   *   A renderable array.
   */
  protected function renderUsingViewMode($view_mode): array {
    return [
      '#type' => 'apigee_entity_list',
      '#entities' => $this->load(),
      '#entity_type' => $this->entityType,
      '#view_mode' => $view_mode,
    ];
  }

  /**
   * Returns TRUE if entity type is configure to use provided display type.
   *
   * @param string $display_type
   *   The display type.
   *
   * @return bool
   *   TRUE if using provided display type. FALSE otherwise.
   */
  protected function usingDisplayType($display_type): bool {
    $settings = $this->getDisplaySettings();

    if (empty($settings['display_type'])) {
      return FALSE;
    }

    return $settings['display_type'] === $display_type;
  }

  /**
   * Returns the display settings.
   *
   * @return array
   *   An array of display settings.
   */
  protected function getDisplaySettings(): array {
    return $this->configFactory
      ->get("apigee_edge.display_settings.{$this->entityTypeId}")
      ->getRawData();
  }

  /**
   * Returns the cache max age.
   *
   * @return int
   *   The cache max age.
   */
  protected function getCacheMaxAge() {
    return 0;
  }

}
