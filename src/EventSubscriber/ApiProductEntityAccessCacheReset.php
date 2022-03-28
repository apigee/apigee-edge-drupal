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

namespace Drupal\apigee_edge\EventSubscriber;

use Drupal\apigee_edge\Event\AppCredentialAddApiProductEvent;
use Drupal\apigee_edge\Event\AppCredentialCreateEvent;
use Drupal\apigee_edge\Event\AppCredentialDeleteApiProductEvent;
use Drupal\apigee_edge\Event\AppCredentialDeleteEvent;
use Drupal\apigee_edge\Event\AppCredentialGenerateEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Ensures that entity access cache gets cleared on API product entities.
 */
final class ApiProductEntityAccessCacheReset implements EventSubscriberInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * ApiProductEntityAccessCacheReset constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Reset API product access when a app credentials gets
      // updated because having an app with a product grants access
      // to the product.
      // @see _apigee_edge_user_has_an_app_with_product()
      AppCredentialCreateEvent::EVENT_NAME => 'clearApiProductCache',
      AppCredentialGenerateEvent::EVENT_NAME => 'clearApiProductCache',
      AppCredentialDeleteEvent::EVENT_NAME => 'clearApiProductCache',
      AppCredentialAddApiProductEvent::EVENT_NAME => 'clearApiProductCache',
      AppCredentialDeleteApiProductEvent::EVENT_NAME => 'clearApiProductCache',
    ];
  }

  /**
   * Clears API product entity access cache.
   *
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   Event that triggered this subscriber.
   */
  public function clearApiProductCache(Event $event): void {
    $this->entityTypeManager->getAccessControlHandler('api_product')->resetCache();
  }

}
