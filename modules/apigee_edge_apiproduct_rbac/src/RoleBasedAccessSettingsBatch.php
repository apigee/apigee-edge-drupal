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

namespace Drupal\apigee_edge_apiproduct_rbac;

use Apigee\Edge\Exception\ApiException;
use Apigee\Edge\Exception\ApiResponseException;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Session\AccountInterface;

/**
 * Utility function for API product RBAC settings save batch.
 */
final class RoleBasedAccessSettingsBatch {

  /**
   * Batch operation callback.
   *
   * @param array $productNameDisplayNameMap
   *   Associative array where keys are the names (ids) of API Products and
   *   values are their display names.
   * @param array $productNameRidsMap
   *   Associative array where keys are the API product names (ids) and values
   *   are array with roles ids that should have access to an API product.
   *   Rids (roles) with bypass permission should be excluded from values!
   * @param string|null $attributeName
   *   Name of the attribute that stores the assigned roles in an API product.
   *   Default is the currently saved configuration.
   * @param string|null $originalAttributeName
   *   Name of the attribute that originally stored the role assignments.
   * @param array $context
   *   Batch context.
   *
   * @see callback_batch_operation()
   */
  public static function batchOperation(array $productNameDisplayNameMap, array $productNameRidsMap, string $attributeName, string $originalAttributeName, array &$context) : void {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($productNameDisplayNameMap);
    }

    // Process API Products by groups of 5.
    /** @var \Drupal\apigee_edge\Entity\Storage\ApiProductStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('api_product');
    /** @var \Apigee\Edge\Api\Management\Controller\ApiProductControllerInterface $controller */
    $controller = $storage->getController(\Drupal::service('apigee_edge.sdk_connector'));

    foreach (array_slice($productNameDisplayNameMap, $context['sandbox']['progress'], 5) as $productName => $productDisplayName) {
      $context['message'] = t('Updating %d API Product...', ['%d' => $productDisplayName]);
      $rids = $productNameRidsMap[$productName] ?? [];
      try {
        $attributes = $controller->getAttributes($productName);
        // Ensure that we do not leave remnants.
        // Even if $attributeName === $originalAttributeName it is better to
        // always clear its value.
        $attributes->delete($originalAttributeName);
        if ($rids) {
          $normalizedRids = [];
          // Do not save redundant (authenticated) roles if "authenticated user"
          // role is present in rids.
          if (in_array(AccountInterface::AUTHENTICATED_ROLE, $normalizedRids)) {
            $normalizedRids[] = AccountInterface::AUTHENTICATED_ROLE;
            if (in_array(AccountInterface::ANONYMOUS_ROLE, $rids)) {
              $normalizedRids[] = AccountInterface::ANONYMOUS_ROLE;
            }
          }
          else {
            $normalizedRids = $rids;
          }
          $attributes->add($attributeName, implode(APIGEE_EDGE_APIPRODUCT_RBAC_ATTRIBUTE_VALUE_DELIMITER, $normalizedRids));
        }
        $controller->updateAttributes($productName, $attributes);
        $context['results']['success'][$productName] = Xss::filter($productDisplayName);
      }
      catch (ApiException $e) {
        $message = Xss::filter($productDisplayName);
        if ($e instanceof ApiResponseException) {
          $message = t('@product (Reason: @reason.)', ['@product' => $productDisplayName, '@reason' => $e->getMessage()]);
        }
        $context['results']['failed'][$productName] = $message;
      }
      finally {
        $context['sandbox']['progress']++;
      }
    }

    // Inform the batch engine that we are not finished,
    // and provide an estimation of the completion level we reached.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Batch finished callback.
   *
   * @see callback_batch_finished()
   */
  public static function batchFinishedCallback(bool $success, array $results, array $operations) {
    $updated = $results['success'] ?? [];
    $failed = $results['failed'] ?? [];

    if ($success && !empty($updated) && empty($failed)) {
      \Drupal::messenger()->addStatus(t('All API product attributes have been updated successfully'));
    }
    elseif (!empty($updated) || !empty($failed)) {
      if (!empty($updated)) {
        $items = [
          '#theme' => 'item_list',
          '#items' => $updated,
        ];
        $message = \Drupal::translation()->formatPlural(count($updated), '@product API product successfully updated.', '@count API Products successfully updated: @products',
          [
            '@product' => reset($updated),
            '@products' => \Drupal::service('renderer')->render($items),
          ]
        );
        \Drupal::messenger()->addStatus($message);
      }

      if (!empty($failed)) {
        $items = [
          '#theme' => 'item_list',
          '#items' => $failed,
        ];
        $message = \Drupal::translation()->formatPlural(count($failed), 'An API product failed failed: @product.', '@count API Products could not be updated: @products',
          [
            '@product' => reset($failed),
            '@products' => \Drupal::service('renderer')->render($items),
          ]);
        \Drupal::messenger()->addError($message);
      }
    }
    else {
      \Drupal::messenger()->addStatus(t('No operation performed.'));
    }
  }

}
