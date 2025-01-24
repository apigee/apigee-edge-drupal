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
use Drupal\Component\Utility\Random;
use Drupal\Core\Utility\Error;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface;
use Drupal\apigee_edge\Event\AppCredentialGenerateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Developer app credential generate event subscriber.
 */
class OverrideAppKeysOnGenerate implements EventSubscriberInterface {

  /**
   * The developer app credential controller factory.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface
   */
  private $devAppCredentialControllerFactory;

  /**
   * OverrideAppKeysOnGenerate constructor.
   *
   * @param \Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface $dev_app_credential_controller_factory
   *   The developer app credential controller factory.
   */
  public function __construct(DeveloperAppCredentialControllerFactoryInterface $dev_app_credential_controller_factory) {
    $this->devAppCredentialControllerFactory = $dev_app_credential_controller_factory;
  }

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
    if ($event->getAppType() === AppCredentialGenerateEvent::APP_TYPE_DEVELOPER) {
      $credential_controller = $this->devAppCredentialControllerFactory->developerAppCredentialController($event->getOwnerId(), $event->getAppName());
    }
    else {
      // @todo Finish when Company apps gets supported.
    }

    $prefix = apigee_edge_test_app_keys_get_prefix();

    try {
      $credential_controller->delete($event->getCredential()->getConsumerKey());
      $new_consumer_key = "{$prefix}-{$random->name()}";
      try {
        $credential_controller->create($new_consumer_key, "{$prefix}-{$random->name()}");
        try {
          $products = array_map(function (CredentialProduct $item) {
            return $item->getApiproduct();
          }, $event->getCredential()->getApiProducts());
          $credential_controller->addProducts($new_consumer_key, $products);
        }
        catch (ApiException $e) {
          $logger = \Drupal::logger('apigee_edge');
          Error::logException($logger, $e, 'Unable to assign API products to the newly generated API key on Apigee Edge for @app app. !message', ['@app' => "{$event->getOwnerId()}:{$event->getAppName()}"]);
          try {
            $credential_controller->delete($new_consumer_key);
          }
          catch (ApiException $e) {
            $logger = \Drupal::logger('apigee_edge');
            Error::logException($logger, $e, 'Unable to delete newly generated API key after API product re-association has failed on Apigee Edge for @app app. !message', ['@app' => "{$event->getOwnerId()}:{$event->getAppName()}"]);
          }
        }
      }
      catch (ApiException $e) {
        $logger = \Drupal::logger('apigee_edge');
        Error::logException($logger, $e, 'Unable to create new API key on Apigee Edge for @app app. !message', ['@app' => "{$event->getOwnerId()}:{$event->getAppName()}"]);
      }
    }
    catch (ApiException $e) {
      $logger = \Drupal::logger('apigee_edge');
      Error::logException($logger, $e, 'Unable to delete auto-generated key of @app app on Apigee Edge. !message', ['@app' => "{$event->getOwnerId()}:{$event->getAppName()}"]);
    }
  }

}
