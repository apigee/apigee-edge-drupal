<?php

/**
 * Copyright 2025 Google Inc.
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

namespace Drupal\apigee_edge\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a search filter form for the entity list.
 */
class EdgeEntitySearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_enitity_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL) {

    if (empty($entity_type_id)) {
      throw new \InvalidArgumentException('The entity_type_id argument is required for EdgeEntitySearchForm.');
    }

    $request = $this->getRequest();

    $form_state->set('entity_type_id', $entity_type_id);

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline', 'apigee-edge-search-form']],
    ];

    $form['filters'][$entity_type_id] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter by @lable', ['@lable' => $entity_type_id]),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Search by @lable name', ['@lable' => $entity_type_id]),
      '#default_value' => $request->query->get($entity_type_id, ''),
      '#size' => 20,
    ];

    $form['filters']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#attributes' => [
        'class' => ['button-action'],
      ],
    ];

    // Only show the Reset button if a search bar have query.
    if ($request->query->has($entity_type_id)) {
      $form['filters']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#submit' => ['::resetForm'],
        '#attributes' => [
          'class' => ['button-action', 'reset'],
        ],
      ];
    }

    $form['#attached']['library'][] = 'apigee_edge/entity_search_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $query_string = [];

    $entity_type_id = $form_state->get('entity_type_id');
    $search_query_value = $form_state->getValue($entity_type_id);

    if ($search_query_value) {
      $query_string[$entity_type_id] = $search_query_value;
    }

    $route_name = "entity.{$entity_type_id}.collection";
    $form_state->setRedirect($route_name, $query_string);
  }

  /**
   * {@inheritdoc}
   */
  public function resetForm(array $form, FormStateInterface &$form_state) {
    $entity_type_id = $form_state->get('entity_type_id');
    $route_name = "entity.{$entity_type_id}.collection";
    $form_state->setRedirect($route_name);
  }

}
