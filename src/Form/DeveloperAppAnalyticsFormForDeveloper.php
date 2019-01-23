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
 * Displays the analytics page of a developer app for a given user on the UI.
 */
class DeveloperAppAnalyticsFormForDeveloper extends DeveloperAppAnalyticsForm {

  // @codingStandardsIgnoreStart
  /**
   * {@inheritdoc}
   *
   * This override here is important because the name of the third parameter is
   * different. This way, Drupal's routing system can correctly identify it and
   * pass the parameter from the URL.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AppInterface $app = NULL) {
    // Pass the "app" (!= developer_app) from the route to the parent.
    return parent::buildForm($form, $form_state, $app);
  }
  // @codingStandardsIgnoreEnd

}
