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
 * Team app create form for a team.
 */
class TeamAppCreateFormForTeam extends TeamAppCreateFormBase {

  /**
   * The team from the route.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $team;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, TeamInterface $team = NULL) {
    // This is the only place where we can grab additional route parameters.
    // See implementation in parent.
    $this->team = $team;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function alterFormBeforeApiProductElement(array &$form, FormStateInterface $form_state): void {
    // The user from the route is the owner.
    $form['owner'] = [
      '#type' => 'value',
      '#value' => $this->team->id(),
    ];
  }

}
