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

use Apigee\Edge\Entity\EntityFactory;
use Apigee\Edge\Entity\EntityFactoryInterface;
use Apigee\Edge\Entity\EntityInterface;

/**
 * Custom entity factory that creates Drupal entities from Edge responses.
 *
 * TODO Find a better way than this copy pasted one.
 */
class DrupalEntityFactory implements EntityFactoryInterface {

  /**
   * The original SDK entity factory.
   *
   * @var \Apigee\Edge\Entity\EntityFactoryInterface
   */
  private $originalFactory;

  /**
   * Constructs a new DrupalEntityFactory.
   */
  public function __construct() {
    $this->originalFactory = new EntityFactory();
  }

  /**
   * Stores mapping of entity classes by controllers.
   *
   * @var string[]
   */
  private static $classMappingCache = [];

  /**
   * Entity object cache.
   *
   * @var \Apigee\Edge\Entity\EntityInterface[]
   */
  private static $objectCache = [];

  /**
   * @inheritdoc
   */
  public function getEntityTypeByController($entityController): string {
    $className = $this->getClassName($entityController);
    // Try to find it in the static cache first.
    if (isset(self::$classMappingCache[$className])) {
      return self::$classMappingCache[$className];
    }
    $fqcn_parts = explode('\\', $className);
    $entityControllerClass = array_pop($fqcn_parts);
    // Special handling of DeveloperAppCredentialController and
    // CompanyAppCredentialController entity controllers,
    // because those uses the same entity, AppCredential.
    $appCredentialController = 'AppCredentialController';
    $isAppCredentialController = (0 === substr_compare(
        $entityControllerClass,
        $appCredentialController,
        strlen($entityControllerClass) - strlen($appCredentialController),
        strlen($appCredentialController)
      ));
    if ($isAppCredentialController) {
      $entityControllerClass = $appCredentialController;
    }
    // Get rid of "Controller" from the namespace.
    array_pop($fqcn_parts);
    $entityControllerClassNameParts = preg_split('/(?=[A-Z])/', $entityControllerClass);
    // First index is an empty string, the last one is "Controller". Let's get
    // rid of those.
    array_shift($entityControllerClassNameParts);
    array_pop($entityControllerClassNameParts);
    $fqcn_parts[] = implode('', $entityControllerClassNameParts);
    $fqcn = implode('\\', $fqcn_parts);
    if (!class_exists($fqcn)) {
      // Try to determine the class with the original entity factory.
      return static::$classMappingCache[$className] = $this->originalFactory->getEntityTypeByController($entityController);
    }
    // Add it to to object cache.
    static::$classMappingCache[$className] = $fqcn;

    return static::$classMappingCache[$className];
  }

  /**
   * @inheritdoc
   */
  public function getEntityByController($entityController): EntityInterface {
    $className = $this->getClassName($entityController);
    $fqcn = $this->getEntityTypeByController($entityController);
    // Add it to to object cache.
    static::$objectCache[$className] = new $fqcn();

    return clone static::$objectCache[$className];
  }

  /**
   * Helper function that returns the FQCN of a class.
   *
   * @param string|\Apigee\Edge\Controller\AbstractEntityController $entityController
   *   Fully qualified class name or an object.
   *
   * @return string
   *   Fully qualified class name.
   */
  private function getClassName($entityController): string {
    return is_object($entityController) ? get_class($entityController) : $entityController;
  }

}
