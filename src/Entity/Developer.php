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

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\Developer as EdgeDeveloper;
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Apigee\Edge\Structure\AttributesProperty;
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
class Developer extends EdgeEntityBase implements DeveloperInterface {

  /**
   * Developer already exists error code.
   */
  const APIGEE_EDGE_ERROR_CODE_DEVELOPER_ALREADY_EXISTS = 'developer.service.DeveloperAlreadyExists';

  /**
   * Developer does not exists error code.
   */
  const APIGEE_EDGE_ERROR_CODE_DEVELOPER_DOES_NOT_EXISTS = 'developer.service.DeveloperDoesNotExist';

  /**
   * The cached Drupal UID.
   *
   * Use getOwnerId() to return the correct value.
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
   * Developer constructor.
   *
   * @param array $values
   *   An array of values to set, keyed by property name.
   * @param null|string $entity_type
   *   Type of the entity. It is optional because constructor sets its default
   *   value.
   * @param \Apigee\Edge\Entity\EntityInterface|null $decorated
   *   The SDK entity that this Drupal entity decorates.
   */
  public function __construct(array $values, ?string $entity_type = NULL, EdgeEntityInterface $decorated = NULL) {
    $entity_type = $entity_type ?? 'developer';
    // Callers expect that the status is always either 'active' or 'inactive',
    // never null.
    if (!isset($values['status'])) {
      $values['status'] = static::STATUS_ACTIVE;
    }
    parent::__construct($values, $entity_type, $decorated);
    // Property must be initialized here because it is used as entity's
    // primary id in Drupal.
    // @see static::idProperties()
    // @see static::drupalEntityId()
    $this->originalEmail = $this->originalEmail ?? $this->decorated->getEmail();
  }

  /**
   * {@inheritdoc}
   *
   * We have to override this to make it compatible with the SDK's
   * entity interface that has return type hint.
   */
  public function id(): ?string {
    return parent::id();
  }

  /**
   * {@inheritdoc}
   */
  protected static function decoratedClass(): string {
    return EdgeDeveloper::class;
  }

  /**
   * {@inheritdoc}
   */
  public static function uniqueIdProperties(): array {
    // Parent returns the original email.
    // @see static::idProperty()
    return array_merge(parent::uniqueIdProperties(), [
      // UUID.
      EdgeDeveloper::idProperty(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function drupalEntityId(): ?string {
    return $this->originalEmail;
  }

  /**
   * {@inheritdoc}
   */
  public function uuid() {
    return $this->decorated->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes(): AttributesProperty {
    return $this->decorated->getAttributes();
  }

  /**
   * {@inheritdoc}
   */
  public function setAttributes(AttributesProperty $attributes): void {
    $this->decorated->setAttributes($attributes);
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributeValue(string $attribute): ?string {
    return $this->decorated->getAttributeValue($attribute);
  }

  /**
   * {@inheritdoc}
   */
  public function setAttribute(string $name, string $value): void {
    $this->decorated->setAttribute($name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function hasAttribute(string $name): bool {
    return $this->decorated->hasAttribute($name);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAttribute(string $name): void {
    $this->decorated->deleteAttribute($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedAt(): ?\DateTimeImmutable {
    return $this->decorated->getCreatedAt();
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedBy(): ?string {
    return $this->decorated->getCreatedBy();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastModifiedAt(): ?\DateTimeImmutable {
    return $this->decorated->getLastModifiedAt();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastModifiedBy(): ?string {
    return $this->decorated->getLastModifiedBy();
  }

  /**
   * {@inheritdoc}
   */
  public function getApps(): array {
    return $this->decorated->getApps();
  }

  /**
   * {@inheritdoc}
   */
  public function hasApp(string $appName): bool {
    return $this->decorated->hasApp($appName);
  }

  /**
   * {@inheritdoc}
   */
  public function getCompanies(): array {
    return $this->decorated->getCompanies();
  }

  /**
   * {@inheritdoc}
   */
  public function hasCompany(string $companyName): bool {
    return $this->decorated->hasCompany($companyName);
  }

  /**
   * {@inheritdoc}
   */
  public function getDeveloperId(): ?string {
    return $this->decorated->getDeveloperId();
  }

  /**
   * {@inheritdoc}
   */
  public function getUserName(): ?string {
    return $this->decorated->getUserName();
  }

  /**
   * {@inheritdoc}
   */
  public function setUserName(string $userName): void {
    $this->decorated->setUserName($userName);
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): ?string {
    return $this->decorated->getEmail();
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail(string $email): void {
    $this->decorated->setEmail($email);
    if ($this->originalEmail === NULL) {
      $this->originalEmail = $email;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstName(): ?string {
    return $this->decorated->getFirstName();
  }

  /**
   * {@inheritdoc}
   */
  public function setFirstName(string $firstName): void {
    $this->decorated->setFirstName($firstName);
  }

  /**
   * {@inheritdoc}
   */
  public function getLastName(): ?string {
    return $this->decorated->getLastName();
  }

  /**
   * {@inheritdoc}
   */
  public function setLastName(string $lastName): void {
    $this->decorated->setLastName($lastName);
  }

  /**
   * {@inheritdoc}
   */
  public static function idProperty(): string {
    return 'originalEmail';
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    $ownerId = $this->getOwnerId();
    return $ownerId === NULL ? NULL : User::load($ownerId);
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->setOwnerId($account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    if ($this->drupalUserId === NULL) {
      if ($this->getEmail()) {
        /** @var \Drupal\user\Entity\UserInterface $account */
        $account = user_load_by_mail($this->getEmail());
        if ($account) {
          $this->drupalUserId = $account->id();
        }
      }
      // Username is not unique on Apigee Edge so we do not use that here.
    }

    return $this->drupalUserId;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->drupalUserId = $uid;
    // When a new user is created uid is null.
    if ($uid !== NULL) {
      $account = User::load($uid);
      if ($account !== NULL && $this->getEmail() !== $account->getEmail()) {
        $this->setEmail($account->getEmail());
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function resetOriginalEmail(): void {
    $this->originalEmail = $this->decorated->getEmail();
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $status): void {
    $this->decorated->setStatus($status);
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganizationName(): ?string {
    return $this->decorated->getOrganizationName();
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): ?string {
    return $this->decorated->getStatus();
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalEmail(): ?string {
    return $this->originalEmail;
  }

}
