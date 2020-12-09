<?php

/*
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

namespace Drupal\apigee_mock_api_client\TwigExtension;

use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Loads templates from the response-templates folder of a module.
 */
class ResponseTemplateLoader extends \Twig_Loader_Filesystem {

  /**
   * Constructs a new FilesystemLoader object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $modules = $module_handler->getModuleList();

    $paths = array_map(function ($module) {
      return $module->getPath() . '/tests/response-templates';
    }, $modules);

    // Filter out core paths & those without a response-templates directory.
    $paths = array_filter($paths, function ($path) {
      return (strpos($path, 'core/') !== 0) && is_dir(DRUPAL_ROOT . "/{$path}");
    });

    parent::__construct($paths);

  }

  /**
   * {@inheritdoc}
   */
  protected function findTemplate($name, $throw = TRUE) {
    $name = str_replace('_', '-', $name);

    if (strpos($name, '.twig') === FALSE) {
      $name = "{$name}.json.twig";
    }

    return parent::findTemplate($name, $throw);
  }

}
