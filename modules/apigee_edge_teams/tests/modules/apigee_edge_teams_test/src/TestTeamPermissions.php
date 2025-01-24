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

namespace Drupal\apigee_edge_teams_test;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\apigee_edge_teams\DynamicTeamPermissionProviderInterface;
use Drupal\apigee_edge_teams\Structure\TeamPermission;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic team permissions for testing.
 */
final class TestTeamPermissions implements DynamicTeamPermissionProviderInterface, ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * TestTeamPermissions constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
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
    return [
      new TeamPermission('test team permission 3', $this->t('Team permission test 3'), $this->t('Team permission test'), $this->t('This is the 3rd team test permission.')),
      new TeamPermission('test team permission 4', $this->t('Team permission test 4'), $this->t('Team permission test')),
    ];
  }

}
