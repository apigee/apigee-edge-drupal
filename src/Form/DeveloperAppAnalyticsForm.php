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

namespace Drupal\apigee_edge\Form;

use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Displays the analytics page of a developer app on the UI.
 */
class DeveloperAppAnalyticsForm extends AppAnalyticsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_developer_app_analytics';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AppInterface $developer_app = NULL) {
    // Pass the "developer_app" (!= app) from the route to the parent.
    return parent::buildForm($form, $form_state, $developer_app);
  }

  /**
   * {@inheritdoc}
   */
  protected function getAnalyticsFilterCriteriaByAppOwner(AppInterface $app) : string {
    $developer = $this->entityTypeManager->getStorage('developer')->load($app->getAppOwner());
    return "developer_email eq '{$developer->id()}'";
  }

}
