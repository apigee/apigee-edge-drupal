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

/**
 * Developer app entity specific view builder.
 */
class DeveloperAppViewBuilder extends AppViewBuilder {

  use DeveloperStatusCheckTrait;

  /**
   * {@inheritdoc}
   */
  public function build(array $build) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app */
    $developer_app = $build["#{$this->entityTypeId}"];
    // Display an error message on the top of the page if current developer is
    // not active.
    // TODO Should we add this error message to the render array instead
    // and with that allow end-users the reposition it on the page?
    // (Just like the callback url warning.)
    if ($build['#view_mode'] === 'full') {
      $this->checkDeveloperStatus($developer_app->getOwnerId());
    }
    return parent::build($build);
  }

}
