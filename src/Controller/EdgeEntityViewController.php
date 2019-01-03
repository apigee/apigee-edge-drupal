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

namespace Drupal\apigee_edge\Controller;

use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Default entity view controller for Apigee Edge entities.
 */
class EdgeEntityViewController extends EntityViewController {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildTitle(array $page) {
    $page = parent::buildTitle($page);
    // Adds entity type to the page title.
    $page['#title'] = $this->t('@label @entity_type', [
      '@label' => $this->entityManager->getTranslationFromContext($page["#{$page['#entity_type']}"])->label(),
      '@entity_type' => $this->entityManager->getDefinition($page['#entity_type'])->getSingularLabel(),
    ]);
    return $page;
  }

}
