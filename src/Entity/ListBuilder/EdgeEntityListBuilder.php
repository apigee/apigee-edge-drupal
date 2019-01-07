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

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default entity list builder for Apigee Edge entities.
 */
class EdgeEntityListBuilder extends EntityListBuilder {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EdgeEntityListBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type, $entity_type_manager->getStorage($entity_type->id()));
    $this->entityTypeManager = $entity_type_manager;
    // Disable pager for now for all Apigee Edge entities.
    $this->limit = 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')
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
   * Returns a render array with a link to the entity type specific add form.
   *
   * @return array|null
   *   A render array with the add entity link if the user has access to that,
   *   null otherwise.
   */
  protected function getAddEntityLink(): ?array {
    /** @var \Symfony\Component\Routing\RouterInterface $router */
    $router = \Drupal::service('router');
    $definition = $this->entityTypeManager->getDefinition($this->entityTypeId);
    $route = "entity.{$this->entityTypeId}.add_form";
    // Is there a better way for this?
    if ($router->getRouteCollection()->get($route)) {
      $url = Url::fromRoute($route);
      $url->setOptions(['attributes' => ['class' => 'btn btn-primary']]);
      if ($url->access()) {
        $link = Link::fromTextAndUrl(
          $this->t('Add @entity_type', ['@entity_type' => $definition->getLowercaseLabel()]),
          $url
        );
        $link_array = $link->toRenderable();
        // Make sure it is in the top of the page by default.
        $link_array['#weight'] = -100;
        return $link_array;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    $add_entity_link = $this->getAddEntityLink();
    if ($add_entity_link) {
      $build['add_entity'] = $add_entity_link;
    }

    return $build;
  }

}
