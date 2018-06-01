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

namespace Drupal\apigee_edge;

use Drupal\apigee_edge\Annotation\ApigeeFieldStorageFormat;
use Drupal\apigee_edge\Plugin\ApigeeFieldStorageFormatInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Traversable;

class FieldStorageFormatManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(Traversable $namespaces, CacheBackendInterface $cache, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ApigeeFieldStorageFormat',
      $namespaces,
      $module_handler,
      ApigeeFieldStorageFormatInterface::class,
      ApigeeFieldStorageFormat::class
    );
    $this->alterInfo('apigee_field_storage_format_info');
    $this->setCacheBackend($cache, 'apigee_field_storage_format_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions(): array {
    $definitions = parent::findDefinitions();
    uasort($definitions, function (array $def0, array $def1) {
      return ($def0['weight'] ?? 0) <=> ($def1['weight'] ?? 0);
    });

    return $definitions;
  }

  /**
   * Finds a plugin to format a given field type.
   *
   * @param string $field_type
   *   Field type.
   *
   * @return \Drupal\apigee_edge\Plugin\ApigeeFieldStorageFormatInterface|null
   *   Storage formatter if found. Null if not.
   */
  public function lookupPluginForFieldType(string $field_type): ?ApigeeFieldStorageFormatInterface {
    $definitions = $this->getDefinitions();

    foreach ($definitions as $name => $definition) {
      $fields = $definition['fields'] ?? [];

      if (in_array($field_type, $fields) || in_array('*', $fields)) {
        /** @var \Drupal\apigee_edge\Plugin\ApigeeFieldStorageFormatInterface $instance */
        $instance = $this->createInstance($name);
        return $instance;
      }
    }

    return NULL;
  }

}
