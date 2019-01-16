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

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for configuring permissions of team members.
 *
 * @internal This configuration UI may change if team-level permissions gets
 * introduced.
 */
class TeamMemberPermissionsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge_teams.team_permissions',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_team_member_permissions_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    $config = $this->config($this->getConfigName());
    $operations = [
      'team' => [
        'label' => $this->t('Team'),
        'permissions' => [
          'manage_members' => [
            'label' => $this->t('Manage team members'),
            'description' => $this->t('Manage team members.'),
          ],
        ],
      ],
      'app' => [
        'label' => $this->t('Team apps'),
        'permissions' => [
          'create' => $this->t('Create Team Apps'),
          'update' => $this->t('Edit any Team Apps'),
          'delete' => $this->t('Delete any Team Apps'),
          'analytics' => $this->t('View analytics of any Team Apps'),
        ],
      ],
    ];

    $form['permissions'] = [
      '#type' => 'container',
    ];
    $form['permissions']['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Configure permissions of team members.'),
      '#attributes' => ['class' => ['description']],
    ];

    foreach ($operations as $group => $group_def) {
      $form['permissions'][$group] = [
        '#type' => 'details',
        '#title' => $group_def['label'],
        '#open' => TRUE,
      ];
      foreach ($group_def['permissions'] as $operation => $label) {
        $config_key = "{$group}_{$operation}";
        $default_value = $config->get($config_key);
        if ($default_value === NULL) {
          $this->logger('apigee_edge')->debug('Missing config object for %operation permission.', [
            '%operation' => $operation,
          ]);
          continue;
        }
        $form['permissions'][$group][$operation] = [
          '#type' => 'checkbox',
          '#title' => is_array($label) ? $label['label'] : $label,
          '#default_value' => $default_value,
        ];
        if (is_array($label) && isset($label['description'])) {
          $form['permissions'][$group][$operation]['#description'] = $label['description'];
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config($this->getConfigName());

    foreach ($form_state->getValue(['permissions'], []) as $group => $permissions) {
      foreach ($permissions as $permission => $value) {
        $config_key = "{$group}_{$permission}";
        $config->set($config_key, $value);
      }
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Returns the name of the config that contains the settings.
   *
   * @return string
   *   The name of the config.
   */
  protected function getConfigName(): string {
    $configs = $this->getEditableConfigNames();
    return reset($configs);
  }

}
