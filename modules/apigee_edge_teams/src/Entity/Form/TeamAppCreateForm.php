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

namespace Drupal\apigee_edge_teams\Entity\Form;

use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * General form handler for the team app create.
 */
class TeamAppCreateForm extends TeamAppCreateFormBase {

  /**
   * {@inheritdoc}
   */
  protected function alterFormBeforeApiProductElement(array &$form, FormStateInterface $form_state): void {
    // Do not recalculate team options when AJAX refreshes the form.
    $team_options = $form_state->get('team_options');
    if ($team_options === NULL) {
      $team_options = array_map(function (TeamInterface $team) {
        return $team->label();
      }, $this->entityTypeManager->getStorage('team')->loadMultiple());
      reset($team_options);
      $form_state->set('team_options', $team_options);
    }

    // Override the owner field to be a select list with all teams from
    // Apigee Edge.
    $form['owner'] = [
      '#title' => $this->t('Owner'),
      '#type' => 'select',
      '#weight' => $form['owner']['#weight'],
      '#default_value' => $form_state->get('owner') ?? key($team_options),
      '#options' => $team_options,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateApiProductList',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function alterFormWithApiProductElement(array &$form, FormStateInterface $form_state): void {
    parent::alterFormWithApiProductElement($form, $form_state);
    $form['api_products']['#prefix'] = '<div id="api-products-ajax-wrapper">';
    $form['api_products']['#suffix'] = '</div>';
  }

  /**
   * Ajax command that refreshes the API product list when owner changes.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public static function updateApiProductList(array $form, FormStateInterface $form_state) : AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#api-products-ajax-wrapper', \Drupal::service('renderer')->render($form['api_products'])));
    return $response;
  }

}
