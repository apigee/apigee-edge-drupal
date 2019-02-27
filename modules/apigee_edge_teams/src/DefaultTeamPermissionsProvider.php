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

namespace Drupal\apigee_edge_teams;

use Drupal\apigee_edge_teams\Structure\TeamPermission;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the default team permissions.
 */
final class DefaultTeamPermissionsProvider implements DynamicTeamPermissionProviderInterface, ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * DefaultTeamPermissionsProvider constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   String translation.
   */
  public function __construct(TranslationInterface $string_translation) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('string_translation'));
  }

  /**
   * {@inheritdoc}
   */
  public function permissions(): array {
    $permissions = [];

    $operations = [
      'team' => [
        'label' => $this->t('Team'),
        'permissions' => [
          'manage_members' => [
            'label' => $this->t('Manage team members'),
            'description' => $this->t('Add/remove team members.'),
          ],
        ],
      ],
      'team_app' => [
        'label' => $this->t('Team apps'),
        'permissions' => [
          'view' => $this->t('View Team Apps'),
          'create' => $this->t('Create Team Apps'),
          'update' => $this->t('Edit any Team Apps'),
          'delete' => $this->t('Delete any Team Apps'),
          'analytics' => $this->t('View analytics of any Team Apps'),
        ],
      ],
      'api_product' => [
        'label' => $this->t('API products'),
        'permissions' => [
          'access_public' => $this->t('View and assign public API products to team apps'),
          'access_private' => $this->t('View and assign private API products to team apps'),
          'access_internal' => $this->t('View and assign internal API products to team apps'),
        ],
      ],
    ];

    foreach ($operations as $group => $group_def) {
      foreach ($group_def['permissions'] as $operation => $operation_def) {
        $description = NULL;
        if (is_array($operation_def)) {
          $label = $operation_def['label'];
          $description = $operation_def['description'] ?? NULL;
        }
        else {
          $label = $operation_def;
        }
        $name = "{$group}_{$operation}";
        $permissions[$name] = new TeamPermission($name, $label, $group_def['label'], $description);
      }
    }

    return $permissions;
  }

}
