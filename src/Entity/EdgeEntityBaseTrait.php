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

use Apigee\Edge\Entity\Property\DisplayNamePropertyInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Allows to use Apigee Edge entities from the SDK as Drupal entities.
 *
 * @see \Drupal\Core\Entity\Entity
 * @see \Drupal\Core\Entity\EntityInterface
 * @see \Drupal\apigee_edge\Entity\EdgeEntityInterface
 */
trait EdgeEntityBaseTrait {

  use RefinableCacheableDependencyTrait;
  use DependencySerializationTrait;

  /**
   * The entity type.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Boolean indicating whether the entity should be forced to be new.
   *
   * @var bool
   */
  protected $enforceIsNew;

  /**
   * A typed data object wrapping this entity.
   *
   * @var \Drupal\Core\TypedData\ComplexDataInterface
   */
  protected $typedData;

  /**
   * {@inheritdoc}
   */
  public function uuid() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function id(): ? string {
    return $this->uuid() === NULL ? parent::id() : $this->uuid();
  }

  /**
   * Gets the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityManagerInterface
   *   The entity manager.
   *
   * @deprecated in Drupal 8.0.0 and will be removed before Drupal 9.0.0.
   *   Use \Drupal::entityTypeManager() instead in most cases. If the needed
   *   method is not on \Drupal\Core\Entity\EntityTypeManagerInterface, see the
   *   deprecated \Drupal\Core\Entity\EntityManager to find the
   *   correct interface or service.
   */
  protected function entityManager() {
    return \Drupal::entityManager();
  }

  /**
   * Gets the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager() {
    return \Drupal::entityTypeManager();
  }

  /**
   * {@inheritdoc}
   */
  public function language() {
    // Our entities does not support translations as the way like content
    // entities do, but we had to find a way to make them work together with the
    // built-in translation features of Drupal 8 (ex.: language dependent
    // link generation). Returning the language of the current seemed to be
    // able to solve this problem.
    // (\Drupal\apigee_edge\Entity\EdgeEntityBaseTrait::toUrl() uses the
    // language of an entity to render an entity link.)
    return \Drupal::languageManager()->getCurrentLanguage();
  }

  /**
   * {@inheritdoc}
   */
  public function isNew() {
    return !empty($this->enforceIsNew) || !$this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function enforceIsNew($value = TRUE) {
    $this->enforceIsNew = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  public function bundle() {
    return $this->entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    if (in_array(DisplayNamePropertyInterface::class, class_implements($this)) && !empty($this->getDisplayName())) {
      return $this->getDisplayName();
    }
    return $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function urlInfo($rel = 'canonical', array $options = []) {
    return $this->toUrl($rel, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    if ($this->id() === NULL) {
      throw new EntityMalformedException(sprintf('The "%s" entity cannot have a URI as it does not have an ID', $this->getEntityTypeId()));
    }

    // The links array might contain URI templates set in annotations.
    $link_templates = $this->linkTemplates();

    // Links pointing to the current revision point to the actual entity. So
    // instead of using the 'revision' link, use the 'canonical' link.
    if ($rel === 'revision' && $this instanceof RevisionableInterface && $this->isDefaultRevision()) {
      $rel = 'canonical';
    }

    if (isset($link_templates[$rel])) {
      $route_parameters = $this->urlRouteParameters($rel);
      $route_name = "entity.{$this->entityTypeId}." . str_replace([
        '-',
        'drupal:',
      ], ['_', ''], $rel);
      $uri = new Url($route_name, $route_parameters);
    }
    else {
      $bundle = $this->bundle();
      // A bundle-specific callback takes precedence over the generic one for
      // the entity type.
      $bundles = $this->entityManager()
        ->getBundleInfo($this->getEntityTypeId());
      if (isset($bundles[$bundle]['uri_callback'])) {
        $uri_callback = $bundles[$bundle]['uri_callback'];
      }
      elseif ($entity_uri_callback = $this->getEntityType()->getUriCallback()) {
        $uri_callback = $entity_uri_callback;
      }

      // Invoke the callback to get the URI. If there is no callback, use the
      // default URI format.
      if (isset($uri_callback) && is_callable($uri_callback)) {
        $uri = call_user_func($uri_callback, $this);
      }
      else {
        throw new UndefinedLinkTemplateException("No link template '$rel' found for the '{$this->getEntityTypeId()}' entity type");
      }
    }

    // Pass the entity data through as options, so that alter functions do not
    // need to look up this entity again.
    $uri
      ->setOption('entity_type', $this->getEntityTypeId())
      ->setOption('entity', $this);

    // Display links by default based on the current language.
    // Link relations that do not require an existing entity should not be
    // affected by this entity's language, however.
    if (!in_array($rel, ['collection', 'add-page', 'add-form'], TRUE)) {
      $options += ['language' => $this->language()];
    }

    $uri_options = $uri->getOptions();
    $uri_options += $options;

    return $uri->setOptions($uri_options);
  }

  /**
   * Gets an array of placeholders for this entity.
   *
   * Individual entity classes may override this method to add additional
   * placeholders if desired. If so, they should be sure to replicate the
   * property caching logic.
   *
   * @param string $rel
   *   The link relationship type, for example: canonical or edit-form.
   *
   * @return array
   *   An array of URI placeholders.
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = [];

    if (!in_array($rel, ['collection', 'add-page', 'add-form'], TRUE)) {
      // The entity ID is needed as a route parameter.
      $uri_route_parameters[$this->getEntityTypeId()] = $this->id();
    }
    if ($rel === 'add-form' && ($this->getEntityType()->hasKey('bundle'))) {
      $parameter_name = $this->getEntityType()
        ->getBundleEntityType() ?: $this->getEntityType()->getKey('bundle');
      $uri_route_parameters[$parameter_name] = $this->bundle();
    }
    if ($rel === 'revision' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function url($rel = 'canonical', $options = []) {
    // While self::toUrl() will throw an exception if the entity has no id,
    // the expected result for a URL is always a string.
    if ($this->id() === NULL || !$this->hasLinkTemplate($rel)) {
      return '';
    }

    $uri = $this->toUrl($rel);
    $options += $uri->getOptions();
    $uri->setOptions($options);
    return $uri->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function link($text = NULL, $rel = 'canonical', array $options = []) {
    return $this->toLink($text, $rel, $options)->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function toLink($text = NULL, $rel = 'canonical', array $options = []) {
    if (!isset($text)) {
      $text = $this->label();
    }
    $url = $this->toUrl($rel);
    $options += $url->getOptions();
    $url->setOptions($options);
    return new Link($text, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function hasLinkTemplate($key) {
    $link_templates = $this->linkTemplates();
    return isset($link_templates[$key]);
  }

  /**
   * Gets an array link templates.
   *
   * @return array
   *   An array of link templates containing paths.
   */
  protected function linkTemplates() {
    return $this->getEntityType()->getLinkTemplates();
  }

  /**
   * {@inheritdoc}
   */
  public function uriRelationships() {
    return array_filter(array_keys($this->linkTemplates()), function ($link_relation_type) {
      // It's not guaranteed that every link relation type also has a
      // corresponding route. For some, additional modules or configuration may
      // be necessary. The interface demands that we only return supported URI
      // relationships.
      try {
        $this->toUrl($link_relation_type)->toString(TRUE)->getGeneratedUrl();
      }
      catch (RouteNotFoundException $e) {
        return FALSE;
      }
      return TRUE;
    });
  }

  /**
   * {@inheritdoc}
   */
  public static function load($id) {
    $entity_manager = \Drupal::entityManager();
    return $entity_manager->getStorage($entity_manager->getEntityTypeFromClass(get_called_class()))
      ->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public static function loadMultiple(array $ids = NULL) {
    $entity_manager = \Drupal::entityManager();
    return $entity_manager->getStorage($entity_manager->getEntityTypeFromClass(get_called_class()))
      ->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(array $values = []) {
    $entity_manager = \Drupal::entityManager();
    return $entity_manager->getStorage($entity_manager->getEntityTypeFromClass(get_called_class()))
      ->create($values);
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    return $this->entityTypeManager()
      ->getStorage($this->entityTypeId)
      ->save($this);
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    if (!$this->isNew()) {
      $this->entityTypeManager()
        ->getStorage($this->entityTypeId)
        ->delete([$this->id() => $this]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    $this->invalidateCacheTagsOnSave((bool) $update);
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageInterface $storage) {
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    static::invalidateCacheTagsOnDelete($storage->getEntityType(), $entities);
  }

  /**
   * {@inheritdoc}
   */
  public static function postLoad(EntityStorageInterface $storage, array &$entities) {
  }

  /**
   * {@inheritdoc}
   */
  public function createDuplicate() {
    // TODO Finish its implementation.
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return $this->entityTypeManager()->getDefinition($this->entityTypeId);
  }

  /**
   * {@inheritdoc}
   */
  public function referencedEntities() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalId() {
    // By default, entities do not support renames and do not have original IDs.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    if ($this->isNew()) {
      return [];
    }
    return [$this->entityTypeId . ':' . $this->id()];
  }

  /**
   * Invalidates an entity's cache tags upon save.
   *
   * @param bool $update
   *   TRUE if the entity has been updated, or FALSE if it has been inserted.
   */
  protected function invalidateCacheTagsOnSave(bool $update) {
    $tags = $this->getEntityType()->getListCacheTags();
    if ($this->hasLinkTemplate('canonical')) {
      // Creating or updating an entity may change a cached 403 or 404 response.
      $tags = Cache::mergeTags($tags, ['4xx-response']);
    }
    if ($update) {
      $tags = Cache::mergeTags($tags, $this->getCacheTagsToInvalidate());
    }
    Cache::invalidateTags($tags);
  }

  /**
   * Invalidates an entity's cache tags upon delete.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of entities.
   */
  protected static function invalidateCacheTagsOnDelete(EntityTypeInterface $entityType, array $entities) {
    $tags = $entityType->getListCacheTags();
    foreach ($entities as $entity) {
      $tags = Cache::mergeTags($tags, $entity->getCacheTagsToInvalidate());
    }
    Cache::invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalId($id) {
    // By default, entities do not support renames and do not have original IDs.
    // If the specified ID is anything except NULL, this should mark this entity
    // as no longer new.
    if ($id !== NULL) {
      $this->enforceIsNew(FALSE);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    $values = [];
    // The goal is to create an array that is 100% compatible with the
    // structure an SDK entity's constructor can accept that is why we
    // are not calling getter here.
    $ro = new \ReflectionObject($this);
    foreach ($ro->getProperties() as $property) {
      $value = NULL;
      $getter = 'get' . ucfirst($property->getName());
      $isser = 'is' . ucfirst($property->getName());
      if ($ro->hasMethod($getter)) {
        $value = $this->{$getter}();
      }
      elseif ($ro->hasMethod($isser)) {
        $value = $this->{$isser}();
      }
      if ($value !== NULL) {
        $values[$property->getName()] = $value;
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypedData() {
    if (!isset($this->typedData)) {
      $class = \Drupal::typedDataManager()->getDefinition('entity')['class'];
      $this->typedData = $class::createFromEntity($this);
    }
    return $this->typedData;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigDependencyKey() {
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigDependencyName() {
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigTarget() {
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($operation == 'create') {
      return $this->entityTypeManager()
        ->getAccessControlHandler($this->entityTypeId)
        ->createAccess($this->bundle(), $account, [], $return_as_object);
    }
    return $this->entityTypeManager()
      ->getAccessControlHandler($this->entityTypeId)
      ->access($this, $operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if ($this->cacheTags) {
      return Cache::mergeTags($this->getCacheTagsToInvalidate(), $this->cacheTags);
    }
    return $this->getCacheTagsToInvalidate();
  }

  /**
   * {@inheritdoc}
   */
  public function setPropertyValue(string $property, $value) : void {
    // Ignore NULL values, because those are not supported by setters of
    // the SDK entities.
    if ($value === NULL) {
      return;
    }
    // Properties of SDK entities are not public ones.
    // (Check number of parameters if it becomes necessary.)
    $setter = 'set' . ucfirst($property);
    if (method_exists($this, $setter)) {
      $this->{$setter}($value);
    }
  }

}
