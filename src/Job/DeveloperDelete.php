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

namespace Drupal\apigee_edge\Job;

use Drupal\apigee_edge\Entity\Developer;

/**
 * A job to delete a developer from Edge.
 */
class DeveloperDelete extends EdgeJob {

  /**
   * The id of the developer.
   *
   * @var string
   */
  protected $developerId;

  /**
   * {@inheritdoc}
   */
  public function __construct(string $developer_id) {
    parent::__construct();
    $this->developerId = $developer_id;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    Developer::load($this->developerId)->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Deleting developer (@mail) from Edge', [
      '@mail' => $this->developerId,
    ])->render();
  }

}
