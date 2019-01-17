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

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Dedicated form handler that allows a developer to create an developer app.
 */
class DeveloperAppCreateFormForDeveloper extends DeveloperAppCreateEditFormForDeveloper {

  use AppCreateFormTrait;
  use DeveloperAppFormTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // The user from the route is the owner.
    $form['owner'] = [
      '#type' => 'value',
      '#value' => $this->getUser()->get('apigee_edge_developer_id')->value,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl(): Url {
    $entity = $this->getEntity();
    return $entity->toUrl('collection-by-developer');
  }

  /**
   * {@inheritdoc}
   */
  protected function apiProductList(): array {
    // Call apiProductList() from parent instead of AppCreateFormTrait.
    return parent::apiProductList();
  }

}
