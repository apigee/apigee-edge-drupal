<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\Developer as EdgeDeveloper;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the Developer entity class.
 *
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "developer",
 *   label = @Translation("Developer"),
 *   handlers = {
 *     "storage" = "\Drupal\apigee_edge\Entity\Storage\DeveloperStorage",
 *   },
 *   query_class = "Drupal\apigee_edge\Entity\Query\DeveloperQuery",
 * )
 */
class Developer extends EdgeDeveloper implements DeveloperInterface {

  use EdgeEntityBaseTrait {
    id as private traitId;
  }

  /**
   * Developer already exists error code.
   */
  const APIGEE_EDGE_ERROR_CODE_DEVELOPER_ALREADY_EXISTS = 'developer.service.DeveloperAlreadyExists';

  /**
   * Developer does not exists error code.
   */
  const APIGEE_EDGE_ERROR_CODE_DEVELOPER_DOES_NOT_EXISTS = 'developer.service.DeveloperDoesNotExist';

  /**
   * The associated Drupal UID.
   *
   * @var null|int
   */
  protected $drupalUserId;

  /**
   * The original email address of the developer.
   *
   * @var null|string
   */
  protected $originalEmail;

  /**
   * Constructs a Developer object.
   *
   * @param array $values
   *   An array of values to set, keyed by property name. If the entity type
   *   has bundles, the bundle key has to be specified.
   */
  public function __construct(array $values = []) {
    // Callers expect that the status is always either 'active' or 'inactive',
    // never null.
    if (!isset($values['status'])) {
      $values['status'] = static::STATUS_ACTIVE;
    }
    parent::__construct($values);
    $this->entityTypeId = 'developer';
    $this->originalEmail = isset($this->originalEmail) ? $this->originalEmail : $this->email;
  }

  /**
   * {@inheritdoc}
   */
  public function uuid() {
    return parent::id();
  }

  /**
   * {@inheritdoc}
   */
  public function id(): ?string {
    return $this->originalEmail;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail(string $email): void {
    parent::setEmail($email);
    if ($this->originalEmail === NULL) {
      $this->originalEmail = $this->email;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @internal
   */
  public function setOriginalEmail(string $original_email) {
    $this->originalEmail = $original_email;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->drupalUserId === NULL ? NULL : User::load($this->drupalUserId);
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->drupalUserId = $account->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->drupalUserId;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->drupalUserId = $uid;
  }

}
