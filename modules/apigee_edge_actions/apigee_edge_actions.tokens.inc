<?php

/**
 * @file
 * Implements tokens for Apigee Edge entities.
 */

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


use Drupal\apigee_edge\Entity\EdgeEntityInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info_alter().
 */
function apigee_edge_actions_token_info_alter(&$info) {
  /** @var \Drupal\Core\Entity\EntityTypeInterface[] $apigee_entity_types */
  $apigee_entity_types = Drupal::service('apigee_edge_actions.edge_entity_type_manager')->getEntityTypes();
  $type_info = Drupal::service('plugin.manager.field.field_type')->getDefinitions();

  foreach ($apigee_entity_types as $entity_type) {
    $token_type = $entity_type->get('token_type');

    if (!isset($info['types'][$token_type]) || !isset($info['tokens'][$token_type])) {
      $info['types'][$entity_type->id()] = [
        'name' => $entity_type->getLabel(),
        'needs-data' => $entity_type->id(),
        'description' => t('Tokens related to @name.', [
          '@name' => $entity_type->getPluralLabel(),
        ]),
        'module' => 'apigee_edge_actions',
      ];

      $fields = Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type->id());
      foreach ($fields as $field_name => $field) {
        /** @var \Drupal\field\FieldStorageConfigInterface $field */
        $params['@type'] = $type_info[$field->getType()]['label'];
        $description = t('@type field.', $params);

        $labels = _token_field_label($entity_type->id(), $field->getName());
        $label = array_shift($labels);
        if (!empty($labels)) {
          $params['%labels'] = implode(', ', $labels);
          $description = t('@type field. Also known as %labels.', $params);
        }

        $info['tokens'][$token_type][$field_name] = [
          'name' => Html::escape($label),
          'description' => $description,
          'module' => 'token',
        ];
      }
    }
  }
}

/**
 * Implements hook_tokens().
 */
function apigee_edge_actions_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type == 'entity' && !empty($data['entity_type']) && !empty($data['entity']) && !empty($data['token_type'])) {
    /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $data['entity'];

    if ($entity instanceof EdgeEntityInterface) {
      foreach ($tokens as $field_name => $original) {
        // Ensure entity has requested field and is not empty.
        if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
          continue;
        }

        $replacements[$original] = $entity->get($field_name)->value;
      }
    }
  }

  return $replacements;
}
