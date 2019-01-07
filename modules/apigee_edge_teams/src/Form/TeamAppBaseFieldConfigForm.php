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

use Drupal\apigee_edge\Form\BaseFieldConfigFromBase;

/**
 * Provides a form for configuring base field settings on team apps.
 */
class TeamAppBaseFieldConfigForm extends BaseFieldConfigFromBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_teams_team_app_base_field_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function entityType(): string {
    return 'team_app';
  }

  /**
   * {@inheritdoc}
   */
  protected function getLockedBaseFields(): array {
    return $this->config('apigee_edge_teams.team_app_settings')->get('locked_base_fields');
  }

  /**
   * {@inheritdoc}
   */
  protected function saveRequiredBaseFields(array $required_base_fields): void {
    $this->configFactory()
      ->getEditable('apigee_edge_teams.team_app_settings')
      ->set('required_base_fields', $required_base_fields)
      ->save();
  }

}
