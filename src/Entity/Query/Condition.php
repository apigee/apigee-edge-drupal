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

namespace Drupal\apigee_edge\Entity\Query;

use Drupal\Core\Entity\Query\ConditionBase;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\QueryException;

/**
 * Defines the condition class for the Apigee Edge entity query.
 */
class Condition extends ConditionBase implements ConditionInterface {

  /**
   * {@inheritdoc}
   */
  public function exists($field, $langcode = NULL) {
    return $this->condition($field, NULL, 'IS NOT NULL', $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function notExists($field, $langcode = NULL) {
    return $this->condition($field, NULL, 'IS NULL', $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function compile($query) {
    if (empty($this->conditions)) {
      return function () {
        return TRUE;
      };
    }

    // This closure will fold the conditions into a single closure if there are
    // more than one, depending on the conjunction.
    $fold = strtoupper($this->conjunction) === 'AND' ?
      function (array $filters) : callable {
        return function ($item) use ($filters) : bool {
          foreach ($filters as $filter) {
            if (!$filter($item)) {
              return FALSE;
            }
          }
          return TRUE;
        };
      } :
      function (array $filters) : callable {
        return function ($item) use ($filters) : bool {
          foreach ($filters as $filter) {
            if ($filter($item)) {
              return TRUE;
            }
          }

          return FALSE;
        };
      };

    $filters = [];
    foreach ($this->conditions as $condition) {
      // If the field is a condition object, compile it and add it to the
      // filters.
      if ($condition['field'] instanceof ConditionInterface) {
        $filters[] = $condition['field']->compile($query);
      }
      else {
        // Set the default operator if it is not set.
        if (!isset($condition['operator'])) {
          $condition['operator'] = is_array($condition['value']) ? 'IN' : '=';
        }

        // Normalize the value to lower case.
        if (is_array($condition['value'])) {
          $condition['value'] = array_map('mb_strtolower', $condition['value']);
        }
        elseif (is_string($condition['value'])) {
          $condition['value'] = mb_strtolower($condition['value']);
        }

        $filters[] = static::matchProperty($condition);
      }
    }

    // Only fold in case of multiple filters.
    return count($filters) > 1 ? $fold($filters) : reset($filters);
  }

  /**
   * Creates a filter closure that matches a property.
   *
   * @param array $condition
   *   Condition structure.
   *
   * @return callable
   *   Filter function.
   */
  protected static function matchProperty(array $condition): callable {
    return function ($item) use ($condition) : bool {
      $value = static::getProperty($item, $condition['field']);

      // Ignore object property values.
      if (is_object($value)) {
        return FALSE;
      }

      // Exit early in case of IS NULL or IS NOT NULL, because they can also
      // deal with array values.
      if (in_array($condition['operator'], ['IS NULL', 'IS NOT NULL'], TRUE)) {
        $should_be_set = $condition['operator'] === 'IS NOT NULL';
        return $should_be_set === isset($value);
      }

      if (isset($value)) {
        if (!is_bool($value)) {
          $value = mb_strtolower($value);
        }

        switch ($condition['operator']) {
          case '=':
            return $value == $condition['value'];

          case '>':
            return $value > $condition['value'];

          case '<':
            return $value < $condition['value'];

          case '>=':
            return $value >= $condition['value'];

          case '<=':
            return $value <= $condition['value'];

          case '<>':
            return $value != $condition['value'];

          case 'IN':
            return in_array($value, $condition['value'], TRUE);

          case 'NOT IN':
            return !in_array($value, $condition['value'], TRUE);

          case 'STARTS_WITH':
            return mb_strpos($value, $condition['value']) === 0;

          case 'CONTAINS':
            return mb_strpos($value, $condition['value']) !== FALSE;

          case 'ENDS_WITH':
            return mb_substr($value, -mb_strlen($condition['value'])) === (string) $condition['value'];

          default:
            throw new QueryException('Invalid condition operator.');
        }
      }

      return FALSE;
    };
  }

  /**
   * Gets a property from an object.
   *
   * To do this, the function tries to guess the name of the getter.
   *
   * @param object $item
   *   Source object.
   * @param string $property
   *   Property name.
   *
   * @return mixed|null
   *   Property value or NULL if not found.
   */
  public static function getProperty($item, string $property) {
    $normalized = ucfirst(implode('', array_map('ucfirst', explode('_', $property))));
    $getter_candidates = [
      "is{$normalized}",
      "get{$normalized}",
      $normalized,
    ];

    foreach ($getter_candidates as $getter) {
      if (method_exists($item, $getter)) {
        return call_user_func([$item, $getter]);
      }
    }

    return NULL;
  }

}
