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
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Displays the view page of a developer app on the UI.
 */
class DeveloperAppViewController extends EntityViewController implements DeveloperAppPageTitleInterface {

  use DeveloperStatusCheckTrait;

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $developer_app, $view_mode = 'full') {
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app */
    $this->checkDeveloperStatus($developer_app->getOwnerId());
    $build = parent::view($developer_app, $view_mode);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return t('@name @developer_app', [
      '@name' => $routeMatch->getParameter('developer_app')->getDisplayName(),
      '@developer_app' => $this->entityManager->getDefinition('developer_app')->getSingularLabel(),
    ]);
  }

}
