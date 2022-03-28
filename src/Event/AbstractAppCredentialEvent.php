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

namespace Drupal\apigee_edge\Event;

use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for app credential create, generate and add products events.
 */
abstract class AbstractAppCredentialEvent extends Event {

  /**
   * Team app type.
   *
   * @var string
   */
  const APP_TYPE_TEAM = 'team';

  /**
   * Developer app type.
   *
   * @var string
   */
  const APP_TYPE_DEVELOPER = 'developer';

  /**
   * Type of the app.
   *
   * @var string
   */
  private $appType;

  /**
   * ID of the app owner.
   *
   * @var string
   */
  private $ownerId;

  /**
   * Name of the app.
   *
   * @var string
   */
  private $appName;

  /**
   * App credential.
   *
   * @var \Apigee\Edge\Api\Management\Entity\AppCredentialInterface
   */
  private $credential;

  /**
   * AppCredentialGenerateEvent constructor.
   *
   * @param string $app_type
   *   Either company or developer.
   * @param string $owner_id
   *   Company name or developer id (UUID by default) depending on the appType.
   * @param string $app_name
   *   Name of the app.
   * @param \Apigee\Edge\Api\Management\Entity\AppCredentialInterface $credential
   *   The app credential that has been created.
   */
  public function __construct(string $app_type, string $owner_id, string $app_name, AppCredentialInterface $credential) {
    if (!in_array($app_type, [self::APP_TYPE_DEVELOPER, self::APP_TYPE_TEAM])) {
      throw new \InvalidArgumentException('App type must be either team or developer.');
    }
    $this->appType = $app_type;
    $this->ownerId = $owner_id;
    $this->appName = $app_name;
    $this->credential = $credential;
  }

  /**
   * Returns the app type which is either "company" or "developer".
   *
   * @return string
   *   The app type.
   */
  public function getAppType(): string {
    return $this->appType;
  }

  /**
   * Returns owner id which is either a company name or a developer id (email).
   *
   * @return string
   *   The owner id.
   */
  public function getOwnerId(): string {
    return $this->ownerId;
  }

  /**
   * Returns the name of the app.
   *
   * @return string
   *   The app name.
   */
  public function getAppName(): string {
    return $this->appName;
  }

  /**
   * Returns the app credential.
   *
   * @return \Apigee\Edge\Api\Management\Entity\AppCredentialInterface
   *   The app credential.
   */
  public function getCredential(): AppCredentialInterface {
    return $this->credential;
  }

}
