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

use Apigee\Edge\Api\Management\Controller\EnvironmentController;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for app analytics related configuration.
 */
class AppAnalyticsSettingsForm extends ConfigFormBase {

  /**
   * Environment controller object.
   *
   * @var \Apigee\Edge\Api\Management\Controller\EnvironmentController
   */
  protected $environmentController;

  /**
   * Constructs a new DeveloperAppAnalyticsSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   The SDK connector service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, SDKConnectorInterface $sdk_connector) {
    parent::__construct($config_factory);
    $this->environmentController = new EnvironmentController($sdk_connector->getOrganization(), $sdk_connector->getClient());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('apigee_edge.sdk_connector')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.common_app_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_app_analytics_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Portal environment is for internal use with integrated portals and
    // is not an actual environment for customers use.
    // To reduce confusion portal environment is hidden from configuration.
    $environments = $this->environmentController->getEntityIds();
    $environments = array_combine($environments, $environments);
    unset($environments['portal']);

    $form['label'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Environment to query for analytics data'),
      '#collapsible' => FALSE,
    ];

    $form['label']['available_environments'] = [
      '#type' => 'checkboxes',
      '#required' => TRUE,
      '#title' => $this->t('Which environments should be displayed on the form to query analytics data?'),
      '#default_value' => $this->config('apigee_edge.common_app_settings')->get('analytics_available_environments') ?: [],
      '#options' => $environments,
    ];

    $form['label']['environment'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Which environment should be selected by default?'),
      '#default_value' => $this->config('apigee_edge.common_app_settings')->get('analytics_environment'),
      '#options' => $environments,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if (!in_array($form_state->getValue('environment'), array_values(array_filter($form_state->getValue('available_environments'))))) {
      $form_state->setError($form['label']['environment'], $this->t('The selected default environment is not available on the form.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('apigee_edge.common_app_settings')
      ->set('analytics_environment', $form_state->getValue('environment'))
      ->set('analytics_available_environments', array_values(array_filter($form_state->getValue('available_environments'))))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
