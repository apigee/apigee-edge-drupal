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

use Apigee\Edge\Api\Management\Controller\DeveloperController;
use Apigee\Edge\Api\Management\Entity\Developer;

/**
 * A job that updates a developer's status in Edge.
 */
class DeveloperSetStatus extends EdgeJob {

  /**
   * The id of the developer.
   *
   * @var string
   */
  protected $developerId;

  /**
   * Status to set.
   *
   * @var string
   */
  protected $status;

  /**
   * {@inheritdoc}
   */
  public function __construct(string $developer_id, string $status) {
    parent::__construct();
    $this->developerId = $developer_id;
    $this->status = $status;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    $controller = new DeveloperController($this->getConnector()->getOrganization(), $this->getConnector()->getClient());
    $controller->setStatus($this->developerId, $this->status);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    $args = ['@mail' => $this->developerId];
    return ($this->status == Developer::STATUS_ACTIVE ?
      t('Enabling developer (@mail) on Edge.', $args) :
      t('Disabling developer (@mail) on Edge.', $args))
      ->render();
  }

}
