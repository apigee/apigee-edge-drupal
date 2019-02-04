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

namespace Drupal\apigee_edge_teams\Form;

use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Form\AppAnalyticsFormBase;

/**
 * Displays the analytics page of a team app on the UI.
 */
class TeamAppAnalyticsForm extends AppAnalyticsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_teams_team_app_analytics';
  }

  /**
   * {@inheritdoc}
   */
  protected function getAnalyticsFilterCriteriaByAppOwner(AppInterface $app): string {
    return "developer eq '{$this->connector->getOrganization()}@@@{$app->getAppOwner()}'";
  }

}
