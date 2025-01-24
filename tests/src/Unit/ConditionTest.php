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

namespace Drupal\Tests\apigee_edge\Unit;

use Apigee\Edge\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\apigee_edge\Entity\Query\Condition;

/**
 * Entity query condition tests.
 *
 * @group apigee_edge
 */
class ConditionTest extends UnitTestCase {

  /**
   * Entity data.
   *
   * @var array
   */
  protected $entityData = [];

  /**
   * Array of entities.
   *
   * @var \Apigee\Edge\Entity\EntityInterface[]
   */
  protected $entities = [];

  /**
   * Tests an empty condition (every result will be returned).
   */
  public function testEmptyCondition() {
    $this->assertFilters($this->mockCondition(), function () {
      return TRUE;
    });
  }

  /**
   * Tests a simple equality condition.
   */
  public function testSimpleEquality() {
    $value = $this->randomData()[0];
    $cond = $this->mockCondition();
    $cond->condition('id', $value);

    $this->assertFilters($cond, function (EntityInterface $item) use ($value) : bool {
      return $item->id() === $value;
    });
  }

  /**
   * Tests a boolean condition.
   */
  public function testBool() {
    $cond = $this->mockCondition();
    $cond->condition('foo_baz', TRUE);

    $this->assertFilters($cond, function (EntityInterface $item) : bool {
      return $item->isFooBaz();
    });
  }

  /**
   * Tests an array contains ("IN" keyword in SQL) condition.
   */
  public function testIn() {
    $values = [];
    for ($i = 0; $i < 16; $i++) {
      $values[] = $this->randomData()[1];
    }

    $cond = $this->mockCondition();
    $cond->condition('foo_bar', $values, 'IN');

    $this->assertFilters($cond, function (EntityInterface $item) use ($values) : bool {
      $item_value = $item->getFooBar();
      foreach ($values as $value) {
        if ($item_value === $value) {
          return TRUE;
        }
      }
      return FALSE;
    });
  }

  /**
   * Tests the "AND" conjunction.
   */
  public function testAnd() {
    $data = $this->randomData();
    $value0 = $data[0];
    $value1 = $data[1];

    $cond = $this->mockCondition();
    $cond->condition('id', $value0);
    $cond->condition('foo_bar', $value1);

    $this->assertFilters($cond, function (EntityInterface $item) use ($value0, $value1) : bool {
      return $item->id() == $value0 && $item->getFooBar() == $value1;
    });
  }

  /**
   * Tests the "OR" conjunction.
   */
  public function testOr() {
    $value0 = $this->randomData()[0];
    $value1 = $this->randomData()[1];

    $cond = $this->mockCondition('OR');
    $cond->condition('id', $value0);
    $cond->condition('id', $value1);

    $this->assertFilters($cond, function (EntityInterface $item) use ($value0, $value1) : bool {
      return $item->id() == $value0 || $item->id() == $value1;
    });
  }

  /**
   * Tests a complex condition.
   */
  public function testComplex() {
    $data0 = $this->randomData();
    $data1 = $this->randomData();
    $value00 = $data0[0];
    $value01 = $data0[1];
    $value10 = $data1[0];
    $value11 = $data1[1];

    $cond0 = $this->mockCondition();
    $cond0->condition('id', $value00);
    $cond0->condition('foo_bar', $value01);

    $cond1 = $this->mockCondition();
    $cond1->condition('id', $value10);
    $cond1->condition('foo_bar', $value11);

    $cond = $this->mockCondition('OR');
    $cond->condition($cond0);
    $cond->condition($cond1);

    $this->assertFilters($cond, function (EntityInterface $item) use ($value00, $value01, $value10, $value11) : bool {
      $id = $item->id();
      $foobar = $item->getFooBar();

      return ($id == $value00 && $foobar == $value01) || ($id == $value10 && $foobar == $value11);
    });
  }

  /**
   * Returns a random data row.
   *
   * @return array
   *   Random data row.
   */
  protected function randomData(): array {
    return $this->entityData[mt_rand(0, count($this->entityData) - 1)];
  }

  /**
   * Creates a Condition object with a mock query parameter.
   *
   * @param string $conjunction
   *   The operator to use to combine conditions: 'AND' or 'OR'.
   *
   * @return \Drupal\apigee_edge\Entity\Query\Condition
   *   Mock conditional object.
   */
  protected function mockCondition($conjunction = 'AND'): Condition {
    return new Condition($conjunction, new class implements QueryInterface {

      /**
       * {@inheritdoc}
       */
      public function addTag($tag) {}

      /**
       * {@inheritdoc}
       */
      public function hasTag($tag) {}

      /**
       * {@inheritdoc}
       */
      public function hasAllTags() {}

      /**
       * {@inheritdoc}
       */
      public function hasAnyTag() {}

      /**
       * {@inheritdoc}
       */
      public function addMetaData($key, $object) {}

      /**
       * {@inheritdoc}
       */
      public function getMetaData($key) {}

      /**
       * {@inheritdoc}
       */
      public function getEntityTypeId() {}

      /**
       * {@inheritdoc}
       */
      public function condition($field, $value = NULL, $operator = NULL, $langcode = NULL) {}

      /**
       * {@inheritdoc}
       */
      public function exists($field, $langcode = NULL) {}

      /**
       * {@inheritdoc}
       */
      public function notExists($field, $langcode = NULL) {}

      /**
       * {@inheritdoc}
       */
      public function pager($limit = 10, $element = NULL) {}

      /**
       * {@inheritdoc}
       */
      public function range($start = NULL, $length = NULL) {}

      /**
       * {@inheritdoc}
       */
      public function sort($field, $direction = 'ASC', $langcode = NULL) {}

      /**
       * {@inheritdoc}
       */
      public function count() {}

      /**
       * {@inheritdoc}
       */
      public function tableSort(&$headers) {}

      /**
       * {@inheritdoc}
       */
      public function accessCheck($access_check = TRUE) {}

      /**
       * {@inheritdoc}
       */
      public function execute() {}

      /**
       * {@inheritdoc}
       */
      public function andConditionGroup() {}

      /**
       * {@inheritdoc}
       */
      public function orConditionGroup() {}

      /**
       * {@inheritdoc}
       */
      public function currentRevision() {}

      /**
       * {@inheritdoc}
       */
      public function allRevisions() {}

      /**
       * {@inheritdoc}
       */
      public function latestRevision() {}

    });
  }

  /**
   * Asserts that a Condition object.
   *
   * @param \Drupal\apigee_edge\Entity\Query\Condition $condition
   *   A Condition object to test.
   * @param callable $filter
   *   A filter to verify the results.
   */
  protected function assertFilters(Condition $condition, callable $filter): void {
    $expected = array_filter($this->entities, $filter);
    $actual = array_filter($this->entities, $condition->compile(NULL));
    $this->assertEquals($expected, $actual);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    for ($i = 0; $i < 1024; $i++) {
      $this->entityData[] = [
        $this->getRandomGenerator()->name(),
        (int) mt_rand(1, 1024 * 1024 * 1024),
        (bool) mt_rand(0, 1),
      ];
    }

    $this->entities = array_map(function ($data) {
      return new class($data[0], $data[1], $data[2]) implements EntityInterface {

        /**
         * @var string
         */
        protected $id;

        /**
         * @var int
         */
        protected $fooBar;

        /**
         * @var bool
         */
        protected $fooBaz;

        /**
         * Constructs a new dummy entity.
         *
         * @param string $id
         *   ID of the entity.
         * @param int $foo_bar
         *   Dummy property.
         * @param bool $foo_baz
         *   Dummy property.
         */
        public function __construct(string $id, int $foo_bar, bool $foo_baz) {
          $this->id = $id;
          $this->fooBar = $foo_bar;
          $this->fooBaz = $foo_baz;
        }

        /**
         * {@inheritdoc}
         */
        public static function idProperty(): string {
          return 'id';
        }

        /**
         * {@inheritdoc}
         */
        public function id(): ?string {
          return $this->id;
        }

        /**
         * @return int
         *   Returns an int.
         */
        public function getFooBar(): int {
          return $this->fooBar;
        }

        /**
         * @param int $foo_bar
         *   Value to set.
         */
        public function setFooBar(int $foo_bar): void {
          $this->fooBar = $foo_bar;
        }

        /**
         * @return bool
         *   Returns true of false.
         */
        public function isFooBaz(): bool {
          return $this->fooBaz;
        }

        /**
         * @param bool $foo_baz
         *   Value to set.
         */
        public function setFooBaz(bool $foo_baz): void {
          $this->fooBaz = $foo_baz;
        }

      };
    }, $this->entityData);
  }

}
