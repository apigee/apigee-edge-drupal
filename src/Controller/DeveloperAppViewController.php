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

use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Displays the view page of a developer app on the UI.
 *
 * @package Drupal\apigee_edge\Controller
 */
class DeveloperAppViewController extends ControllerBase implements DeveloperAppPageTitleInterface {

  use DeveloperAppViewControllerTrait;

  /**
   * Renders the view page of a developer app for a given user.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   The developer app entity.
   *
   * @return array
   *   The render array.
   */
  public function render(DeveloperAppInterface $developer_app): array {
    return $this->getRenderArray($developer_app);
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return $this->pageTitle([
      '@name' => $routeMatch->getParameter('developer_app')->getDisplayName(),
      '@devAppLabel' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel(),
    ]);
  }

}
