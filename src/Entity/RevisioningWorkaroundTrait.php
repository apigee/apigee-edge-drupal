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

use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Some Drupal core & contrib modules assume all entities are revisionable.
 *
 * Because all entities are either a content or a contrib entity. Well, this is
 * not true. The Apigee Edge entities are the perfect counterexamples for this.
 *
 * This trait contains some workarounds to make all components happy until
 * this incorrect assumption hasn't been removed from core and contrib modules.
 *
 * @see \Drupal\Core\Entity\RevisionableInterface
 * @see https://www.drupal.org/project/drupal/issues/2942529
 * @see https://www.drupal.org/project/drupal/issues/2951487
 * @see https://www.drupal.org/project/drupal/issues/3045384
 *
 * @internal
 */
trait RevisioningWorkaroundTrait {

  /**
   * {@inheritdoc}
   */
  public function isNewRevision() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setNewRevision($value = TRUE) {
    throw new \LogicException('Entity does not support revisions');
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionId() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLoadedRevisionId() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function updateLoadedRevisionId() {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isDefaultRevision($new_value = NULL) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function wasDefaultRevision() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isLatestRevision() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record) {
  }

}
