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

use Drupal\apigee_edge\Entity\ListBuilder\AppListBuilder;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lists team apps of a team on the UI.
 */
class TeamAppListByTeam extends AppListBuilder implements ContainerInjectionInterface {

  /**
   * Route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * TeamAppListByTeam constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $render
   *   The render.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, RendererInterface $render, RequestStack $request_stack, RouteMatchInterface $route_match) {
    parent::__construct($entity_type, $entity_type_manager, $render, $request_stack);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('request_stack'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return static::createInstance($container, $container->get('entity_type.manager')->getDefinition('team_app'));
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityIdQuery(): QueryInterface {
    $query = parent::buildEntityIdQuery();

    $team = $this->routeMatch->getParameter('team');
    $query->condition('companyName', $team->id());

    return $query;
  }

  /**
   * Returns the title of the "team app list by team" page.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title of the page.
   */
  public function pageTitle(): TranslatableMarkup {
    return $this->t('@team_apps', ['@team_apps' => $this->entityType->getPluralLabel()]);
  }

}
