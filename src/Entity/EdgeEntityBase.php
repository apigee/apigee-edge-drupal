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

use Apigee\Edge\Entity\EntityInterface;
use Apigee\Edge\Entity\Property\DisplayNamePropertyInterface;
use Drupal\apigee_edge\Exception\InvalidArgumentException;
use Drupal\Core\Entity\Entity;

/**
 * Base class for Apigee Edge entities in Drupal.
 */
abstract class EdgeEntityBase extends Entity implements EdgeEntityInterface {

  /**
   * The decorated SDK entity.
   *
   * @var \Apigee\Edge\Entity\EntityInterface
   */
  protected $decorated;

  /**
   * EdgeEntityBase constructor.
   *
   * @param array $values
   *   An array of values to set, keyed by property name.
   * @param null|string $entity_type
   *   Type of the entity.
   * @param \Apigee\Edge\Entity\EntityInterface|null $decorated
   *   The SDK entity that this Drupal entity decorates.
   *
   * @throws \ReflectionException
   */
  public function __construct(array $values, string $entity_type, ?EntityInterface $decorated = NULL) {
    parent::__construct([], $entity_type);
    if ($decorated) {
      $this->decorated = $decorated;
    }
    else {
      $rc = new \ReflectionClass($this->decoratedClass());
      if (!$rc->implementsInterface(EntityInterface::class)) {
        throw new InvalidArgumentException(sprintf('"%s" interface must be implemented by the decorated class.', $rc->getName()));
      }
      // Get rid of useless but also problematic null values.
      // (SDK entity classes do not like them.)
      $values = array_filter($values, function ($value) {
        return !is_null($value);
      });
      $this->decorated = $rc->newInstance($values);
    }
  }

  /**
   * The FQCN of the decorated class from the PHP API Client.
   *
   * @return string
   *   FQCN of the entity class.
   */
  abstract protected static function decoratedClass() : string;

  /**
   * {@inheritdoc}
   *
   * We have to override this to make it compatible with the SDK's
   * entity interface that enforces the return type.
   */
  public function id(): ?string {
    return $this->drupalEntityId();
  }

  /**
   * Return the entity id used in Drupal.
   *
   * @return null|string
   *   Null if the entity is new.
   */
  abstract protected function drupalEntityId(): ?string;

  /**
   * {@inheritdoc}
   */
  public static function uniqueIdProperties(): array {
    return [
      static::idProperty(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function uniqueIds(): array {
    $ids = [];
    $ro = new \ReflectionObject($this);
    foreach (static::uniqueIdProperties() as $property) {
      $getter = 'get' . ucfirst($property);
      $ids[] = $ro->getMethod($getter)->invoke($this);
    }

    return $ids;
  }

  /**
   * Creates a Drupal entity from an SDK Entity.
   *
   * @param \Apigee\Edge\Entity\EntityInterface $entity
   *   An SDK entity.
   *
   * @return \Drupal\apigee_edge\Entity\EdgeEntityInterface
   *   The Drupal entity that decorates the SDK entity.
   */
  public static function createFrom(EntityInterface $entity): EdgeEntityInterface {
    return new static([], NULL, $entity);
  }

  /**
   * {@inheritdoc}
   */
  public function decorated(): EntityInterface {
    return $this->decorated;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $label = parent::label();
    if ($label === NULL) {
      if (in_array(DisplayNamePropertyInterface::class, class_implements($this)) && !empty($this->getDisplayName())) {
        $label = $this->getDisplayName();
      }
      else {
        $label = $this->id();
      }
    }

    return $label;
  }

}
