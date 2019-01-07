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
use Drupal\Core\Form\FormStateInterface;

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
  public function buildForm(array $form, FormStateInterface $form_state, ?AppInterface $team_app = NULL) {
    // TODO Check Team's status, just like in case we check developer's status
    // for developer apps.
    // Pass the "team_app" (!= app) from the route to the parent.
    return parent::buildForm($form, $form_state, $team_app);
  }

  /**
   * {@inheritdoc}
   */
  protected function getAnalyticsFilterCriteriaByAppOwner(AppInterface $app): string {
    return "developer eq '{$this->connector->getOrganization()}@@@{$app->getAppOwner()}'";
  }

}
