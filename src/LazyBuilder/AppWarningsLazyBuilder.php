<?php

declare(strict_types = 1);

/**
 * Copyright 2023 Google Inc.
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

namespace Drupal\apigee_edge\LazyBuilder;

use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Lazy builder for app credential warnings.
 */
final class AppWarningsLazyBuilder implements TrustedCallbackInterface {

  /**
   * Lazy builds app credentials warnings.
   *
   * @param string $entity_type_id
   *   The type of the entity, either "developer_app" or "team_app".
   * @param string $id
   *   The entity id.
   *
   * @return array
   *   A render array with warnings.
   */
  public static function lazyBuilder(string $entity_type_id, string $id): array {
    if (!in_array($entity_type_id, ['developer_app', 'team_app'], TRUE)) {
      throw new \LogicException(sprintf('Unexpected entity type: %s.', $entity_type_id));
    }

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($id);
    if ($entity instanceof AppInterface) {
      /** @var \Drupal\apigee_edge\Entity\AppWarningsCheckerInterface $app_warnings_checker */
      $app_warnings_checker = \Drupal::service('apigee_edge.entity.app_warnings_checker');
      $warnings = array_filter($app_warnings_checker->getWarnings($entity));
      if (count($warnings) > 0) {
        return [
          '#theme' => 'apigee_app_credential_warnings',
          '#warnings' => $warnings,
        ];
      }
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['lazyBuilder'];
  }

}
