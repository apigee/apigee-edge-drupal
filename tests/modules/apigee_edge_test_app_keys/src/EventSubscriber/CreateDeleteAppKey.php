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

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\State\StateInterface;
use Drupal\apigee_edge\Event\AppCredentialCreateEvent;
use Drupal\apigee_edge\Event\AppCredentialDeleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Developer app credential create/delete event subscriber.
 */
class CreateDeleteAppKey implements EventSubscriberInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private $time;

  /**
   * DeleteAppKey constructor.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(TimeInterface $time, StateInterface $state) {
    $this->state = $state;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      AppCredentialCreateEvent::EVENT_NAME => 'onAppKeyCreateDelete',
      AppCredentialDeleteEvent::EVENT_NAME => 'onAppKeyCreateDelete',
    ];
  }

  /**
   * Creates a states entry when a dev. app credential is created or deleted.
   *
   * @param \Drupal\apigee_edge\Event\AppCredentialDeleteEvent|\Drupal\apigee_edge\Event\AppCredentialCreateEvent $event
   *   The developer app credential create or delete event.
   */
  public function onAppKeyCreateDelete($event) {
    $this->state->set(static::generateStateKey($event->getAppType(), $event->getOwnerId(), $event->getAppName(), $event->getCredential()->id()), $this->time->getCurrentTime());
  }

  /**
   * Generates a unique states key for an app credential.
   *
   * @param string $app_type
   *   Either "developer" or "company".
   * @param string $owner_id
   *   Developer id or company name.
   * @param string $app_name
   *   App name.
   * @param string $credential_key
   *   Credential key.
   *
   * @return string
   *   States key.
   */
  public static function generateStateKey(string $app_type, string $owner_id, string $app_name, string $credential_key): string {
    return "{$app_type}-{$owner_id}-{$app_name}-{$credential_key}";
  }

}
