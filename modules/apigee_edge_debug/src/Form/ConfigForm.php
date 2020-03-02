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

namespace Drupal\apigee_edge_debug\Form;

use Drupal\apigee_edge_debug\DebugMessageFormatterPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for changing configuration of the debug module.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * Debug message formatter plugin manager.
   *
   * @var \Drupal\apigee_edge_debug\DebugMessageFormatterPluginManager
   */
  private $pluginManager;

  /**
   * ConfigForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\apigee_edge_debug\DebugMessageFormatterPluginManager $plugin_manager
   *   The debug message formatter plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, DebugMessageFormatterPluginManager $plugin_manager) {
    parent::__construct($config_factory);
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('plugin.manager.apigee_edge_debug.debug_message_formatter'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['apigee_edge_debug.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_debug_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('apigee_edge_debug.settings');

    $options = [];
    foreach ($this->pluginManager->getDefinitions() as $id => $definition) {
      $options[$id] = $definition['label'];
    }

    $form['log_message_format'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $form['log_message_format']['format'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Log message format'),
      '#description' => $this->t('Note: Adding API responses to log messages by using <em>{response_formatted}</em> token may contain sensitive data (ex.: app credentials, etc.).'),
      '#required' => TRUE,
      '#default_value' => $config->get('log_message_format'),
    ];
    $form['log_message_format']['help'] = [
      '#type' => 'container',
      'tokens' => [
        '#type' => 'details',
        '#title' => $this->t('Available tokens'),
        '#open' => FALSE,
        'token_list' => [
          '#theme' => 'item_list',
          '#items' => [
            '{request_formatted} - Formatted HTTP request by the selected formatter.',
            '{response_formatted} - Formatted HTTP response by the selected formatter.',
            '{stats} - Transfer statistics of the request.',
          ],
        ],
      ],
    ];

    $form['formatter'] = [
      '#type' => 'select',
      '#title' => $this->t('Formatter'),
      '#description' => $this->t('The formatter plugin for the HTTP requests, responses and transfer statistics in log messages.'),
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => $config->get('formatter'),
    ];

    $form['sanitization'] = [
      '#type' => 'details',
      '#title' => $this->t('Sanitization'),
      '#open' => TRUE,
    ];

    $form['sanitization']['mask_organization'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mask organization'),
      '#description' => $this->t('Mask organization name in log entries.'),
      '#default_value' => $config->get('mask_organization'),
    ];

    $form['sanitization']['remove_credentials'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove credentials'),
      '#description' => $this->t('Remove Apigee Edge authentication data from log entries, ex.: authentication header, OAuth client id and secret, access token, refresh token, etc.'),
      '#default_value' => $config->get('remove_credentials'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('apigee_edge_debug.settings')
      ->set('formatter', $form_state->getValue('formatter'))
      ->set('log_message_format', $form_state->getValue(['log_message_format', 'format']))
      ->set('mask_organization', ($form_state->getValue('mask_organization')))
      ->set('remove_credentials', $form_state->getValue('remove_credentials'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
