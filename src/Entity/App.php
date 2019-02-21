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
use Apigee\Edge\Exception\ApiException;
use Apigee\Edge\Structure\AttributesProperty;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;

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
    // Get app credentials from the shared app cache if available.
    /** @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache */
    $app_cache = \Drupal::service('apigee_edge.controller.cache.apps');
    $app = $app_cache->getEntity($this->getAppId());
    if ($app === NULL) {
      // App has not found in cache, we have to load it from Apigee Edge.
      /** @var \Drupal\apigee_edge\Entity\Controller\AppControllerInterface $app_controller */
      $app_controller = \Drupal::service('apigee_edge.controller.app');
      try {
        $app = $app_controller->loadApp($this->getAppId());
      }
      catch (ApiException $e) {
        // Just catch it and leave app to be NULL.
        // It should never happen that we have an app id here that does not
        // belong to an actually existing app in Apigee Edge.
      }
    }
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
  public function setAppFamily(string $app_family): void {
    $this->decorated->setAppFamily($app_family);
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
  public function setCallbackUrl(string $callback_url): void {
    $this->decorated->setCallbackUrl($callback_url);
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
  public function setDisplayName(string $display_name): void {
    $this->decorated->setDisplayName($display_name);
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

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $definitions */
    $definitions = parent::baseFieldDefinitions($entity_type);

    $definitions['name']->setRequired(TRUE);

    $definitions['displayName']
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'weight' => 0,
      ]);

    $definitions['callbackUrl'] = BaseFieldDefinition::create('app_callback_url')
      ->setDisplayOptions('form', [
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setLabel(t('Callback URL'));

    // Do not limit the length of the description because the API does not
    // limit that either.
    $definitions['description'] = static::getBaseFieldDefinition('description', 'string_long')
      ->setSetting('case_sensitive', TRUE)
      ->setDisplayOptions('form', [
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => 4,
      ]);

    $definitions['status']
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'status_property',
        'weight' => 1,
      ]);

    $definitions['createdAt']
      ->setDisplayOptions('view', [
        'type' => 'timestamp_ago',
        'label' => 'inline',
        'weight' => 3,
      ])
      ->setLabel(t('Created'));

    $definitions['lastModifiedAt']
      ->setDisplayOptions('view', [
        'type' => 'timestamp_ago',
        'label' => 'inline',
        'weight' => 5,
      ])
      ->setLabel(t('Last updated'));

    // Hide readonly properties from Manage form display list.
    $read_only_fields = [
      'appId',
      'appFamily',
      'createdAt',
      'lastModifiedAt',
      'name',
      'scopes',
      'status',
    ];
    foreach ($read_only_fields as $field) {
      $definitions[$field]->setDisplayConfigurable('form', FALSE);
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  protected static function propertyToBaseFieldTypeMap(): array {
    return parent::propertyToBaseFieldBlackList() + [
      // UUIDs (developerId, appId) managed on Apigee Edge so we do not
      // want to expose them as UUID fields. Same applies for createdAt and
      // lastModifiedAt. We do not want that Drupal apply default values
      // on them if they are empty therefore their field type is a simple
      // "timestamp" instead of "created" or "changed".
      'createdAt' => 'timestamp',
      'lastModifiedAt' => 'timestamp',
      'scopes' => 'list_string',
      'status' => 'string',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function propertyToBaseFieldBlackList(): array {
    return array_merge(parent::propertyToBaseFieldBlackList(), [
      // We expose credentials as a pseudo field.
      'credentials',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function get($field_name) {
    $value = parent::get($field_name);

    // Make sure that returned callback url field values are actually valid
    // URLs. Apigee Edge allows to set anything as callbackUrl value but
    // Drupal can only accept valid URIs.
    if ($field_name === 'callbackUrl') {
      if (!$value->isEmpty()) {
        foreach ($value->getValue() as $id => $item) {
          try {
            Url::fromUri($item['value']);
          }
          catch (\Exception $exception) {
            $value->removeItem($id);
          }
        }
      }
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function set($field_name, $value, $notify = TRUE) {
    // If the callback URL value is not a valid URL then save an empty string
    // as the field value and set the callbackUrl property to the original
    // value. (So we can display the original (invalid URL) on the edit form.)
    // This trick is not necessary if the value's type is array because in this
    // case the field value is set on the developer app edit form.
    if ($field_name === 'callbackUrl' && !is_array($value)) {
      try {
        Url::fromUri($value);
      }
      catch (\Exception $exception) {
        /** @var \Drupal\apigee_edge\Entity\App $app */
        $app = parent::set($field_name, '', $notify);
        $app->setCallbackUrl($value);
        return $app;
      }
    }
    return parent::set($field_name, $value, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $label = parent::label();
    // Return app name instead of app id if display name is missing.
    if ($label === $this->id()) {
      $label = $this->getName();
    }
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function uuid() {
    // Or $this->id().
    return $this->decorated->getAppId();
  }

  /**
   * {@inheritdoc}
   */
  public static function uniqueIdProperties(): array {
    return array_merge(parent::uniqueIdProperties(), ['appId']);
  }

}
