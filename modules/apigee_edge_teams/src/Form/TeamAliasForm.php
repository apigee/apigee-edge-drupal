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

use Drupal\Core\Form\FormStateInterface;
use Drupal\apigee_edge\Form\EdgeEntityAliasConfigFormBase;

/**
 * Provides a form for changing Team aliases.
 */
class TeamAliasForm extends EdgeEntityAliasConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_teams_team_alias_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this->getConfigNameWithLabels());

    $form['team_label'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Team settings'),
      '#collapsible' => FALSE,
    ];

    $form['team_label']['team_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix to add for the Team name'),
      '#default_value' => $config->get('team_prefix'),
      '#description' => $this->t('<i>Example: "<strong>int-</strong>"</i> or leave empty for no prefix.'),
    ];

    $org_controller = \Drupal::service('apigee_edge.controller.organization');
    if ($org_controller->isOrganizationApigeeX()) {
      $form['channel_label'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Channel settings'),
        '#collapsible' => FALSE,
      ];

      $form['channel_label']['channelid'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Channel ID'),
        '#default_value' => $config->get('channelid'),
        '#description' => $this->t('Leave empty to use the default "@channelid" as channel ID.', ['@channelid' => $this->originalChannelId()]),
      ];

      $form['channel_label']['enablefilter'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Filter by Channel ID'),
        '#default_value' => $config->get('enablefilter'),
        '#description' => $this->t('Enforce the filtering of AppGroups based on Channel ID specified in the field above.'),
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $pattern = '/^[a-z0-9_-]+$/';
    $team_prefix = $form_state->getValue('team_prefix');
    $channelid = $form_state->getValue('channelid');

    if (!empty($team_prefix) && !preg_match($pattern, $team_prefix)) {
      $form_state->setError($form['team_label']['team_prefix'], $this->t('Team prefix name must contain only lowercase letters, numbers, hyphen or the underscore character.'));
      return;
    }
    if (!empty($channelid) && !preg_match($pattern, $channelid)) {
      $form_state->setError($form['channel_label']['channelid'], $this->t('Channel ID must contain only lowercase letters, numbers, hyphen or the underscore character.'));
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config($this->getConfigNameWithLabels());

    if ($config->get('team_prefix') !== $form_state->getValue('team_prefix') || $config->get('channelid') !== $form_state->getValue('channelid') || $config->get('enablefilter') !== $form_state->getValue('enablefilter')) {
      $config->set('team_prefix', $form_state->getValue('team_prefix'))
        ->set('channelid', $form_state->getValue('channelid'))
        ->set('enablefilter', $form_state->getValue('enablefilter'))
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Returns the default value for Channel ID for ApigeeX.
   *
   * @return string
   *   default channel ID value.
   */
  public static function originalChannelId(): string {
    return t('devportal');
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge_teams.team_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function entityTypeName(): string {
    return $this->t('Team');
  }

  /**
   * {@inheritdoc}
   */
  protected function originalSingularLabel(): string {
    return $this->t('Team');
  }

  /**
   * {@inheritdoc}
   */
  protected function originalPluralLabel(): string {
    return $this->t('Teams');
  }

}
