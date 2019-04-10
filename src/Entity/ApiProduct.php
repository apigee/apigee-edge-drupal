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

use Apigee\Edge\Api\Management\Entity\ApiProduct as EdgeApiProduct;
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Apigee\Edge\Structure\AttributesProperty;

/**
 * Defines the API product entity class.
 *
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "api_product",
 *   label = @Translation("API"),
 *   label_singular = @Translation("API"),
 *   label_plural = @Translation("APIs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count API",
 *     plural = "@count APIs",
 *   ),
 *   config_with_labels = "apigee_edge.api_product_settings",
 *   handlers = {
 *     "storage" = "\Drupal\apigee_edge\Entity\Storage\ApiProductStorage",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 * )
 */
class ApiProduct extends EdgeEntityBase implements ApiProductInterface {

  // The majority of Drupal core & contrib assumes that an entity to be
  // displayed is a content entity, and because it is a content entity it also
  // must support revisioning.
  // Having this trait addresses the following issue in the EntityViewBuilder.
  // https://www.drupal.org/node/2951487
  use RevisioningWorkaroundTrait;

  /**
   * ApiProduct constructor.
   *
   * @param array $values
   *   An array of values to set, keyed by property name.
   * @param null|string $entity_type
   *   Type of the entity. It is optional because constructor sets its default
   *   value.
   * @param \Apigee\Edge\Entity\EntityInterface|null $decorated
   *   The SDK entity that this Drupal entity decorates.
   */
  public function __construct(array $values, ?string $entity_type = NULL, ?EdgeEntityInterface $decorated = NULL) {
    $entity_type = $entity_type ?? 'api_product';
    parent::__construct($values, $entity_type, $decorated);
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
  protected function drupalEntityId(): ?string {
    return $this->getName();
  }

  /**
   * {@inheritdoc}
   */
  protected static function decoratedClass(): string {
    return EdgeApiProduct::class;
  }

  /**
   * {@inheritdoc}
   */
  public static function idProperty(): string {
    return EdgeApiProduct::idProperty();
  }

  /**
   * {@inheritdoc}
   */
  public function getProxies(): array {
    return $this->decorated->getProxies();
  }

  /**
   * {@inheritdoc}
   */
  public function setProxies(string ...$proxy): void {
    $this->decorated->setProxies($proxy);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuota(): ?string {
    return $this->decorated->getQuota();
  }

  /**
   * {@inheritdoc}
   */
  public function setQuota(string $quota) {
    $this->decorated->setQuota($quota);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuotaInterval(): ?string {
    return $this->decorated->getQuotaInterval();
  }

  /**
   * {@inheritdoc}
   */
  public function setQuotaInterval(string $quota_interval): void {
    $this->decorated->setQuotaInterval($quota_interval);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuotaTimeUnit(): ?string {
    return $this->decorated->getQuotaTimeUnit();
  }

  /**
   * {@inheritdoc}
   */
  public function setQuotaTimeUnit(string $quota_time_unit): void {
    $this->decorated->setQuotaTimeUnit($quota_time_unit);
  }

  /**
   * {@inheritdoc}
   */
  public function getApprovalType(): ?string {
    return $this->decorated->getApprovalType();
  }

  /**
   * {@inheritdoc}
   */
  public function setApprovalType(string $approval_type): void {
    $this->decorated->setApprovalType($approval_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getApiResources(): array {
    return $this->decorated->getApiResources();
  }

  /**
   * {@inheritdoc}
   */
  public function setApiResources(string ...$api_resources): void {
    $this->decorated->setApiResources($api_resources);
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
  public function getDescription(): ?string {
    return $this->decorated->getDescription();
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
  public function getDisplayName(): ?string {
    return $this->decorated->getDisplayName();
  }

  /**
   * {@inheritdoc}
   */
  public function setDisplayName(string $display_name): void {
    $this->decorated->setDisplayName($display_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironments(): array {
    return $this->decorated->getEnvironments();
  }

  /**
   * {@inheritdoc}
   */
  public function setEnvironments(string ...$environments): void {
    $this->decorated->setEnvironments(...$environments);
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
  public function setScopes(string ...$scopes): void {
    $this->decorated->setScopes(...$scopes);
  }

}
