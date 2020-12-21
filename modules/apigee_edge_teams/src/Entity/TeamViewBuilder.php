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

use Drupal\apigee_edge\Entity\EdgeEntityViewBuilder;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Theme\Registry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity view builder for team entities.
 */
class TeamViewBuilder extends EdgeEntityViewBuilder {

  /**
   * The 'apigee_edge_teams.team_settings' config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * TeamViewBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Config\Config $config
   *   The 'apigee_edge_teams.team_settings' config.
   * @param \Drupal\Core\Theme\Registry|null $theme_registry
   *   The theme registry.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface|null $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityRepositoryInterface $entity_repository, LanguageManagerInterface $language_manager, Config $config, Registry $theme_registry = NULL, EntityDisplayRepositoryInterface $entity_display_repository = NULL) {
    parent::__construct($entity_type, $entity_repository, $language_manager, $theme_registry, $entity_display_repository);
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('config.factory')->get('apigee_edge_teams.team_settings'),
      $container->get('theme.registry'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getBuildDefaults(EntityInterface $entity, $view_mode): array {
    $build = parent::getBuildDefaults($entity, $view_mode);

    // Use cache expiration defined in configuration.
    $build['#cache']['max-age'] = $this->config->get('cache_expiration');

    return $build;
  }

}
