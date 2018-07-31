<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\apigee_edge\Entity\DeveloperStatusCheckTrait;
use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Displays the view page of a developer app on the UI.
 */
class DeveloperAppViewController extends EntityViewController implements DeveloperAppPageTitleInterface {

  use DeveloperStatusCheckTrait;
  use DeveloperAppCallbackUrlCheckTrait;

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $developer_app, $view_mode = 'full') {
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app */
    $this->checkDeveloperStatus($developer_app->getOwnerId());
    // Because we use this custom controller class to render the entity the
    // _entity_view parameter cannot be passed. Create a new route and
    // controller if another $view_mode should be used.
    // See \Drupal\Core\Entity\Enhancer\EntityRouteEnhancer.
    $this->checkCallbackUrl($developer_app, 'default');
    $build = parent::view($developer_app, $view_mode);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return t('@name @developer_app', [
      '@name' => Markup::create($routeMatch->getParameter('developer_app')->label()),
      '@developer_app' => $this->entityManager->getDefinition('developer_app')->getSingularLabel(),
    ]);
  }

}
