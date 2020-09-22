<?php

/**
 * Copyright 2020 Google Inc.
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
 * Provides settings form for team_invitation..
 */
class TeamInvitationForm extends ConfigFormBase {

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
  public function getFormId() {
    return 'apigee_edge_teams_team_invitation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('apigee_edge_teams.team_settings');

    $form['team_invitation_expiry_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Expiry days'),
      '#description' => $this->t('Number of days before team invitations are expired.'),
      '#required' => TRUE,
      '#default_value' => $config->get('team_invitation_expiry_days'),
    ];

    $form['email_for_existing_users'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Invitation email for existing users'),
      '#collapsible' => FALSE,
    ];

    $form['email_for_existing_users']['team_invitation_email_existing_subject'] = [
      '#title' => $this->t('Subject'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('team_invitation_email_existing.subject'),
    ];

    $form['email_for_existing_users']['team_invitation_email_existing_body'] = [
      '#title' => $this->t('Body'),
      '#type' => 'textarea',
      '#rows' => 10,
      '#required' => TRUE,
      '#default_value' => $config->get('team_invitation_email_existing.body'),
      '#description' => $this->t('Available tokens: [user:display-name], [site:name], [site:url], [team_invitation:team_name], [team_invitation:url_accept], [team_invitation:url_decline], [team_invitation:uid:entity:display-name] (the display name of the user who sent the invitation) and [team_invitation:expiry_days]'),
    ];

    $form['email_for_new_users'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Invitation email for new users'),
      '#collapsible' => FALSE,
    ];

    $form['email_for_new_users']['team_invitation_email_new_subject'] = [
      '#title' => $this->t('Subject'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('team_invitation_email_new.subject'),
    ];

    $form['email_for_new_users']['team_invitation_email_new_body'] = [
      '#title' => $this->t('Body'),
      '#type' => 'textarea',
      '#rows' => 10,
      '#required' => TRUE,
      '#default_value' => $config->get('team_invitation_email_new.body'),
      '#description' => $this->t('Available tokens: [site:name], [site:url], [team_invitation:team_name], [team_invitation:url_register], [team_invitation:url_accept], [team_invitation:url_decline], [team_invitation:uid:entity:display-name] (the display name of the user who sent the invitation) and [team_invitation:expiry_days].'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ((int) $form_state->getValue('team_invitation_expiry_days') < 1) {
      $form_state->setErrorByName('team_invitation_expiry_days', $this->t('Expiry days must be 1 or more days.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('apigee_edge_teams.team_settings')
      ->set('team_invitation_expiry_days', $form_state->getValue(['team_invitation_expiry_days']))
      ->set('team_invitation_email_existing.subject', $form_state->getValue(['team_invitation_email_existing_subject']))
      ->set('team_invitation_email_existing.body', $form_state->getValue(['team_invitation_email_existing_body']))
      ->set('team_invitation_email_new.subject', $form_state->getValue(['team_invitation_email_new_subject']))
      ->set('team_invitation_email_new.body', $form_state->getValue(['team_invitation_email_new_body']))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
