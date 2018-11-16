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

use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Apigee\Edge\Structure\AttributesProperty;

/**
 * Base class for App Drupal entities.
 */
abstract class App extends AttributesAwareFieldableEdgeEntityBase implements AppInterface {

  /**
   * The decorated app entity from the SDK.
   *
   * @var \Apigee\Edge\Api\Management\Entity\AppInterface
   */
  protected $decorated;

  /**
   * App constructor.
   *
   * @param array $values
   *   An array of values to set, keyed by property name.
   * @param null|string $entity_type
   *   Type of the entity. It is optional because constructor sets its default
   *   value.
   * @param \Apigee\Edge\Entity\EntityInterface|null $decorated
   *   The SDK entity that this Drupal entity decorates.
   */
  public function __construct(array $values, string $entity_type, ?EdgeEntityInterface $decorated = NULL) {
    // Credentials should not be cached.
    unset($values['credentials']);
    /** @var \Apigee\Edge\Api\Management\Entity\App $decorated */
    if ($decorated) {
      $decorated = clone $decorated;
      $decorated->setCredentials();
    }
    parent::__construct($values, $entity_type, $decorated);
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
  public function getAppFamily(): string {
    return $this->decorated->getAppFamily();
  }

  /**
   * {@inheritdoc}
   */
  public function getAppId(): ?string {
    return $this->decorated->getAppId();
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
  public function getAttributes(): AttributesProperty {
    return $this->decorated->getAttributes();
  }

  /**
   * {@inheritdoc}
   */
  public function getCallbackUrl(): ?string {
    return $this->decorated->getCallbackUrl();
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
  public function getCredentials(): array {
    // Return an empty array for a new app.
    if (empty($this->getAppId())) {
      return [];
    }
    // Get app credentials from the shared app cache.
    /** @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache */
    $app_cache = \Drupal::service('apigee_edge.controller.cache.apps');
    $app = $app_cache->getAppFromCacheByAppId($this->getAppId());
    return $app ? $app->getCredentials() : [];
  }

  /**
   * Make sure that credentials never gets cached.
   *
   * They should not be saved on this entity or on the decorated
   * SDK entity object. This is just for extra alertness.
   *
   * @see \Drupal\apigee_edge\Entity\FieldableEdgeEntityBase::setPropertyValue()
   */
  public function setCredentials(): void {
  }

  /**
   * Returns the id of the app owner from the app entity.
   *
   * Return value could be either the developer id or the company name.
   *
   * @return string
   *   Id of the app owner.
   */
  abstract protected function getAppOwner(): string;

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->decorated->getDescription();
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName(): ?string {
    return $this->decorated->getDisplayName();
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
  public function getName(): ?string {
    return $this->decorated->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function getScopes(): array {
    return $this->decorated->getScopes();
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
  public function hasAttribute(string $name): bool {
    return $this->decorated->hasAttribute($name);
  }

  /**
   * {@inheritdoc}
   */
  public function setAppFamily(string $appFamily): void {
    $this->decorated->setAppFamily($appFamily);
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
  public function setAttributes(AttributesProperty $attributes): void {
    $this->decorated->setAttributes($attributes);
  }

  /**
   * {@inheritdoc}
   */
  public function setCallbackUrl(string $callbackUrl): void {
    $this->decorated->setCallbackUrl($callbackUrl);
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(string $description): void {
    $this->decorated->setDescription($description);
  }

  /**
   * {@inheritdoc}
   */
  public function setDisplayName(string $displayName): void {
    $this->decorated->setDisplayName($displayName);
  }

  /**
   * {@inheritdoc}
   */
  public function setScopes(string ...$scopes): void {
    $this->decorated->setScopes(...$scopes);
  }

  /**
   * {@inheritdoc}
   */
  protected function drupalEntityId(): ?string {
    return $this->decorated->getAppId();
  }

}
