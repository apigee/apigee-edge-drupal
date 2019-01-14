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
use Drupal\Core\Form\FormStateInterface;

/**
 * General form handler for the team app create.
 */
class TeamAppCreateForm extends TeamAppCreateFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\apigee_edge_teams\Entity\TeamAppInterface $app */
    $app = $this->entity;

    $team_options = array_map(function (TeamInterface $team) {
      return $team->label();
    }, $this->entityTypeManager->getStorage('team')->loadMultiple());

    // Override the owner field to be a select list with all teams from
    // Apigee Edge.
    $form['owner'] = [
      '#title' => $this->t('Owner'),
      '#type' => 'select',
      '#weight' => $form['owner']['#weight'],
      '#default_value' => $app->getCompanyName(),
      '#options' => $team_options,
      '#required' => TRUE,
    ];

    // We do not know yet how existing API product access is going to be
    // applied on team (company) apps so we do not display a warning here.
    // @see \Drupal\apigee_edge\Entity\Form\DeveloperAppCreateForm::form()

    return $form;
  }

}
