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

namespace Drupal\apigee_edge_test_app_keys\EventSubscriber;

use Apigee\Edge\Exception\ApiException;
use Apigee\Edge\Structure\CredentialProduct;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialController;
use Drupal\apigee_edge\Event\AppCredentialGenerateEvent;
use Drupal\Component\Utility\Random;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OverrideAppKeysOnGenerate implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      AppCredentialGenerateEvent::EVENT_NAME => 'overrideAppKeyOnGenerate',
    ];
  }

  /**
   * Overrides auto-generated key for an app.
   *
   * @param \Drupal\apigee_edge\Event\AppCredentialGenerateEvent $event
   *   App credential generation event.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function overrideAppKeyOnGenerate(AppCredentialGenerateEvent $event) {
    $random = new Random();
    $sdk_connector = \Drupal::service('apigee_edge.sdk_connector');
    if ($event->getAppType() === AppCredentialGenerateEvent::APP_TYPE_DEVELOPER) {
      $credential_controller = new DeveloperAppCredentialController(
        $sdk_connector->getOrganization(),
        $event->getOwnerId(),
        $event->getAppName(),
        $sdk_connector->getClient()
      );
    }
    else {
      // TODO Finish when Company apps gets supported.
    }

    try {
      $credential_controller->delete($event->getCredential()->getConsumerKey());
      $new_consumer_key = $random->name();
      try {
        $credential_controller->create($new_consumer_key, $random->name());
        try {
          $products = array_map(function (CredentialProduct $item) {
            return $item->getApiproduct();
          }, $event->getCredential()->getApiProducts());
          $credential_controller->addProducts($new_consumer_key, $products);
        }
        catch (ApiException $e) {
          watchdog_exception('apigee_edge', $e, 'Unable to assign API products to the newly generated API key on Apigee Edge for @app app. !message', ['@app' => "{$event->getOwnerId()}:{$event->getAppName()}"]);
          try {
            $credential_controller->delete($new_consumer_key);
          }
          catch (ApiException $e) {
            watchdog_exception('apigee_edge', $e, 'Unable to delete newly generated API key after API product re-association has failed on Apigee Edge for @app app. !message', ['@app' => "{$event->getOwnerId()}:{$event->getAppName()}"]);
          }
        }
      }
      catch (ApiException $e) {
        watchdog_exception('apigee_edge', $e, 'Unable to create new API key on Apigee Edge for @app app. !message', ['@app' => "{$event->getOwnerId()}:{$event->getAppName()}"]);
      }
    }
    catch (ApiException $e) {
      watchdog_exception('apigee_edge', $e, 'Unable to delete auto-generated key of @app app on Apigee Edge. !message', ['@app' => "{$event->getOwnerId()}:{$event->getAppName()}"]);
    }
  }

}
