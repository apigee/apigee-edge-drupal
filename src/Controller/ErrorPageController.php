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

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a controller for the configurable error page.
 */
class ErrorPageController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Builds the renderable array for page.html.twig using the module's config.
   */
  public function render() {
    $build['content'] = [
      '#type' => 'processed_text',
      '#format' => $this->configFactory->get('apigee_edge.error_page')->get('error_page_content.format'),
      '#text' => $this->configFactory->get('apigee_edge.error_page')->get('error_page_content.value'),
    ];
    return $build;
  }

  /**
   * Returns the error page title from the module's config.
   */
  public function getPageTitle() {
    return $this->configFactory->get('apigee_edge.error_page')->get('error_page_title');
  }

}
