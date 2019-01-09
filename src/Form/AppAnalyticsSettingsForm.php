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
    $environments = $this->environmentController->getEntityIds();

    $form['label'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Environment to query for analytics data'),
      '#collapsible' => FALSE,
    ];

    $form['label']['environment'] = [
      '#type' => 'radios',
      '#title' => $this->t('Environments'),
      '#default_value' => $this->config('apigee_edge.common_app_settings')->get('analytics_environment'),
      '#options' => array_combine($environments, $environments),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('apigee_edge.common_app_settings')
      ->set('analytics_environment', $form_state->getValue('environment'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
