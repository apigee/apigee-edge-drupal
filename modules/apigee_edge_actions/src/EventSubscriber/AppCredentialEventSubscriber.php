<?php

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

namespace Drupal\apigee_edge_actions\EventSubscriber;

use Drupal\apigee_edge_actions\Event\EdgeEntityEventEdge;
use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Event\AppCredentialAddApiProductEvent;
use Drupal\apigee_edge\Event\AppCredentialDeleteApiProductEvent;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Proxy for Apigee Edge app credential events.
 */
class AppCredentialEventSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * AppCredentialEventSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, AccountInterface $current_user, LoggerChannelInterface $logger) {
    $this->entityTypeManger = $entity_type_manager;
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
    $this->currentUser = $current_user;
  }

  /**
   * Responds to add product events.
   *
   * @param \Drupal\apigee_edge\Event\AppCredentialAddApiProductEvent $event
   *   The app credential add product event.
   */
  public function onAddProduct(AppCredentialAddApiProductEvent $event) {
    $this->dispatchRulesEvent('apigee_edge_actions_entity_add_product:developer_app', $event, $event->getNewProducts());
  }

  /**
   * Responds to remove product events.
   *
   * @param \Drupal\apigee_edge\Event\AppCredentialDeleteApiProductEvent $event
   *   The app credential remove product event.
   */
  public function onRemoveProduct(AppCredentialDeleteApiProductEvent $event) {
    $this->dispatchRulesEvent('apigee_edge_actions_entity_remove_product:developer_app', $event, [$event->getApiProduct()]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      AppCredentialAddApiProductEvent::EVENT_NAME => ['onAddProduct', 100],
      AppCredentialDeleteApiProductEvent::EVENT_NAME => ['onRemoveProduct', 100],
    ];
  }

  /**
   * Helper to dispatch a corresponding rules event for an api credential event.
   *
   * @param string $rules_event_name
   *   The name of the rules event.
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The api credential event.
   * @param array $api_products
   *   An array of api products.
   */
  protected function dispatchRulesEvent(string $rules_event_name, Event $event, array $api_products) {
    try {
      $app_type = "{$event->getAppType()}_app";
      $app = $this->getAppByName($event->getAppName(), $app_type);
      $developer = $this->entityTypeManger->getStorage('user')
        ->load($this->currentUser->id());

      foreach ($api_products as $product) {
        /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $api_product */
        $api_product = $this->entityTypeManger
          ->getStorage('api_product')
          ->load($product);
        $this->eventDispatcher->dispatch($rules_event_name, new EdgeEntityEventEdge($app, [
          $app_type => $app,
          'developer' => $developer,
          'api_product_name' => $api_product->getName(),
          'api_product_display_name' => $api_product->getDisplayName(),
        ]));
      }
    }
    catch (PluginException $exception) {
      $this->logger->error($exception->getMessage());
    }
  }

  /**
   * Helper to load an app by name.
   *
   * @param string $name
   *   The name of the app.
   * @param string $app_type
   *   The type of the app.
   *
   * @return \Drupal\apigee_edge\Entity\AppInterface|null
   *   The app with the provided name or null.
   */
  protected function getAppByName(string $name, string $app_type): ?AppInterface {
    try {
      $storage = $this->entityTypeManger->getStorage($app_type);
      $apps = $storage->loadMultiple();
      $app = array_filter($apps, function (AppInterface $app) use ($name) {
        return $app->getName() === $name;
      });

      return count($app) ? reset($app) : NULL;
    }
    catch (PluginException $exception) {
      $this->logger->error($exception);
    }

    return NULL;
  }

}
