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

use Drupal\apigee_edge\KeyValueMalformedException;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for saving the Apigee Edge API authentication key.
 */
class AuthenticationForm extends ConfigFormBase {

  /**
   * The key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $sdkConnector;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new AuthenticationForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   SDK connector service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              KeyRepositoryInterface $key_repository,
                              SDKConnectorInterface $sdk_connector,
                              MessengerInterface $messenger) {
    parent::__construct($config_factory);
    $this->keyRepository = $key_repository;
    $this->sdkConnector = $sdk_connector;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('key.repository'),
      $container->get('apigee_edge.sdk_connector'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_authentication';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.client',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('apigee_edge.client');
    $form = parent::buildForm($form, $form_state);
    $form['#prefix'] = '<div id="apigee-edge-auth-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.admin';

    $form['authentication'] = [
      '#type' => 'details',
      '#title' => $this->t('Authentication key'),
      '#description' => $this->t('Select an available key. If the desired key is not listed, create a new key below.'),
      '#open' => TRUE,
    ];

    // Loading basic authentication keys.
    $basic_auth_keys = $this->keyRepository->getKeyNamesAsOptions(['type' => 'apigee_edge_basic_auth']);
    foreach ($basic_auth_keys as $key_id => $key_name) {
      $basic_auth_keys[$key_id] = $this->t('@key_name <a href=":url">Edit</a>', [
        '@key_name' => $key_name,
        ':url' => Url::fromRoute('entity.key.edit_form', ['key' => $key_id, 'destination' => 'admin/config/apigee-edge/settings'])->toString(),
      ]);
    }
    $basic_auth_default_value = array_key_exists($config->get('active_key'), $basic_auth_keys) ? $config->get('active_key') : NULL;

    // Loading OAuth keys.
    $oauth_keys = $this->keyRepository->getKeyNamesAsOptions(['type' => 'apigee_edge_oauth']);
    foreach ($oauth_keys as $key_id => $key_name) {
      $oauth_keys[$key_id] = $this->t('@key_name <a href=":url">Edit</a>', [
        '@key_name' => $key_name,
        ':url' => Url::fromRoute('entity.key.edit_form', ['key' => $key_id, 'destination' => 'admin/config/apigee-edge/settings'])->toString(),
      ]);
    }
    $oauth_default_value = array_key_exists($config->get('active_key'), $oauth_keys) ? $config->get('active_key') : NULL;

    // Loading OAuth token keys.
    $oauth_token_keys = $this->keyRepository->getKeyNamesAsOptions(['type' => 'apigee_edge_oauth_token']);
    foreach ($oauth_token_keys as $key_id => $key_name) {
      $oauth_token_keys[$key_id] = $this->t('@key_name <a href=":url">Edit</a>', [
        '@key_name' => $key_name,
        ':url' => Url::fromRoute('entity.key.edit_form', ['key' => $key_id, 'destination' => 'admin/config/apigee-edge/settings'])->toString(),
      ]);
    }
    $oauth_token_default_value = array_key_exists($config->get('active_key_oauth_token'), $oauth_token_keys) ? $config->get('active_key_oauth_token') : NULL;

    $form['authentication']['key_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Apigee Edge API authentication type'),
      '#options' => [
        'apigee_edge_basic_auth' => $this->t('Basic authentication'),
        'apigee_edge_oauth' => $this->t('OAuth'),
      ],
      '#default_value' => isset($oauth_default_value) ? 'apigee_edge_oauth' : 'apigee_edge_basic_auth',
      '#required' => TRUE,
    ];

    $form['authentication']['key_basic_auth'] = [
      '#type' => 'radios',
      '#title' => $this->t('Basic authentication keys'),
      '#options' => $basic_auth_keys,
      '#access' => !empty($basic_auth_keys),
      '#default_value' => $basic_auth_default_value,
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="key_type"]' => [
            'value' => 'apigee_edge_basic_auth',
          ],
        ],
      ],
    ];

    if (empty($basic_auth_keys)) {
      $form['authentication']['key_basic_auth_missing'] = [
        '#type' => 'item',
        '#title' => $this->t('There is no available basic authentication key for connecting to Apigee Edge.'),
        '#description' => $this->t('Select an OAuth key or <a href=":link">create a new basic authentication key</a>.', [
          ':link' => Url::fromRoute('entity.key.add_form', ['destination' => 'admin/config/apigee-edge/settings'])->toString(),
        ]),
        '#states' => [
          'visible' => [
            ':input[name="key_type"]' => [
              'value' => 'apigee_edge_basic_auth',
            ],
          ],
        ],
      ];
    }

    $form['authentication']['key_oauth'] = [
      '#type' => 'radios',
      '#title' => $this->t('OAuth keys'),
      '#options' => $oauth_keys,
      '#access' => !empty($oauth_keys) && !empty($oauth_token_keys),
      '#default_value' => $oauth_default_value,
      '#states' => [
        'visible' => [
          ':input[name="key_type"]' => [
            'value' => 'apigee_edge_oauth',
          ],
        ],
      ],
    ];

    $form['authentication']['key_oauth_token'] = [
      '#type' => 'radios',
      '#title' => $this->t('OAuth token keys'),
      '#options' => $oauth_token_keys,
      '#access' => !empty($oauth_keys) && !empty($oauth_token_keys),
      '#default_value' => $oauth_token_default_value,
      '#states' => [
        'visible' => [
          ':input[name="key_type"]' => [
            'value' => 'apigee_edge_oauth',
          ],
        ],
      ],
    ];

    if (empty($oauth_keys)) {
      $form['authentication']['key_oauth_missing'] = [
        '#type' => 'item',
        '#title' => $this->t('There is no available OAuth key for connecting to Apigee Edge.'),
        '#description' => $this->t('Select a basic authentication key or <a href=":link">create a new OAuth key</a>.', [
          ':link' => Url::fromRoute('entity.key.add_form', ['destination' => 'admin/config/apigee-edge/settings'])->toString(),
        ]),
        '#states' => [
          'visible' => [
            ':input[name="key_type"]' => [
              'value' => 'apigee_edge_oauth',
            ],
          ],
        ],
      ];
    }

    if (empty($oauth_token_keys)) {
      $form['authentication']['key_oauth_token_missing'] = [
        '#type' => 'item',
        '#title' => $this->t('There is no available OAuth token key for connecting to Apigee Edge.'),
        '#description' => $this->t('Select a basic authentication key or <a href=":link">create a new OAuth token key</a>.', [
          ':link' => Url::fromRoute('entity.key.add_form', ['destination' => 'admin/config/apigee-edge/settings'])->toString(),
        ]),
        '#states' => [
          'visible' => [
            ':input[name="key_type"]' => [
              'value' => 'apigee_edge_oauth',
            ],
          ],
        ],
      ];
    }

    $form['test_connection'] = [
      '#type' => 'details',
      '#title' => $this->t('Test connection'),
      '#description' => 'Send request using the selected authentication key.',
      '#open' => TRUE,
    ];
    $form['test_connection']['test_connection_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send request'),
      '#disabled' => !$form['authentication']['key_basic_auth']['#access'] && !$form['authentication']['key_oauth']['#access'],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'apigee-edge-auth-form',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Waiting for response...'),
        ],
      ],
      '#states' => [
        'enabled' => [
          [
            ':input[name="key_oauth"]' => [
              'checked' => TRUE,
            ],
            ':input[name="key_oauth_token"]' => [
              'checked' => TRUE,
            ],
            ':input[name="key_type"]' => [
              'value' => 'apigee_edge_oauth',
            ],
          ],
          'or',
          [
            ':input[name="key_basic_auth"]' => [
              'checked' => TRUE,
            ],
            ':input[name="key_type"]' => [
              'value' => 'apigee_edge_basic_auth',
            ],
          ],
        ],
      ],
      '#submit' => ['::submitTestConnection'],
    ];

    $form['actions']['submit']['#disabled'] = !$form['authentication']['key_basic_auth']['#access'] && !$form['authentication']['key_oauth']['#access'];
    $form['actions']['submit']['#states'] = [
      'enabled' => [
        [
          ':input[name="key_oauth"]' => [
            'checked' => TRUE,
          ],
          ':input[name="key_oauth_token"]' => [
            'checked' => TRUE,
          ],
          ':input[name="key_type"]' => [
            'value' => 'apigee_edge_oauth',
          ],
        ],
        'or',
        [
          ':input[name="key_basic_auth"]' => [
            'checked' => TRUE,
          ],
          ':input[name="key_type"]' => [
            'value' => 'apigee_edge_basic_auth',
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $key = NULL;
    $key_token = NULL;
    if ($form_state->getValue('key_type') === 'apigee_edge_basic_auth') {
      $key = $this->keyRepository->getKey($form_state->getValue('key_basic_auth'));
    }
    elseif ($form_state->getValue('key_type') === 'apigee_edge_oauth') {
      $key = $this->keyRepository->getKey($form_state->getValue('key_oauth'));
      $key_token = $this->keyRepository->getKey($form_state->getValue('key_oauth_token'));
    }
    try {
      $this->sdkConnector->testConnection($key, $key_token);
    }
    catch (KeyValueMalformedException $exception) {
      watchdog_exception('apigee_edge', $exception);
      $form_state->setError($form, $this->t('Could not read the key storage. Check the key provider and settings.'));
    }
    catch (\Exception $exception) {
      watchdog_exception('apigee_edge', $exception);
      $form_state->setError($form, $this->t('Connection failed. Response from edge: %response', [
        '%response' => $exception->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('key_type') === 'apigee_edge_basic_auth') {
      $this->config('apigee_edge.client')
        ->set('active_key', $form_state->getValue('key_basic_auth'))
        ->set('active_key_oauth_token', '')
        ->save();
    }
    elseif ($form_state->getValue('key_type') === 'apigee_edge_oauth') {
      $this->config('apigee_edge.client')
        ->set('active_key', $form_state->getValue('key_oauth'))
        ->set('active_key_oauth_token', $form_state->getValue('key_oauth_token'))
        ->save();
    }
    parent::submitForm($form, $form_state);
  }

  /**
   * Pass form array to the AJAX callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   *
   * @return array
   *   The AJAX response.
   */
  public function ajaxCallback(array $form): array {
    return $form;
  }

  /**
   * API test connection.
   *
   * Sends API test request using the current form data and set
   * the response text on the UI.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitTestConnection(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $this->messenger->addStatus($this->t('Connection successful.'));
  }

}
