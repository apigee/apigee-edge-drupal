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

use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\key\Exception\KeyValueNotRetrievedException;
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
      'apigee_edge.authentication',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('apigee_edge.authentication');
    $form = parent::buildForm($form, $form_state);
    $form['#prefix'] = '<div id="apigee-edge-auth-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.admin';

    $form['sync'] = [
      '#type' => 'details',
      '#title' => $this->t('Sync developers'),
      '#open' => TRUE,
    ];

    $form['sync']['sync_submit'] = [
      '#title' => $this->t('Now'),
      '#type' => 'link',
      '#url' => $this->buildUrl('apigee_edge.user_sync.run'),
      '#attributes' => [
        'class' => [
          'button',
          'button--primary',
        ],
      ],
    ];

    $form['sync']['background_sync_submit'] = [
      '#title' => $this->t('Background'),
      '#type' => 'link',
      '#url' => $this->buildUrl('apigee_edge.user_sync.schedule'),
      '#attributes' => [
        'class' => [
          'button',
        ],
      ],
    ];

    $form['sync']['sync_info'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => '?',
      '#attributes' => [
        'class' => 'info-circle',
        'title' => t('A background sync is recommended for large numbers of developers.'),
      ],
    ];

    $form['authentication'] = [
      '#type' => 'details',
      '#title' => $this->t('Authentication key'),
      '#description' => t('Select an available key. If the desired key is not listed, <a href=":link">create a new key</a>.', [
        ':link' => Url::fromRoute('entity.key.add_form')->toString(),
      ]),
      '#open' => TRUE,
    ];

    $options = $this->keyRepository->getKeyNamesAsOptions(['type_group' => 'apigee_edge']);
    if (empty($options)) {
      $this->messenger->addWarning(t('There is no available key for connecting to Apigee Edge API server. <a href=":link">Create a new key.</a>', [
        ':link' => Url::fromRoute('entity.key.add_form')->toString(),
      ]));
    }
    $default_value = in_array($config->get('active_key'), $options) ? $config->get('active_key') : NULL;
    $form['authentication']['key'] = [
      '#type' => 'radios',
      '#title' => t('Keys'),
      '#options' => $options,
      '#access' => !empty($options),
      '#default_value' => $default_value,
      '#required' => TRUE,
    ];

    $form['test_connection'] = [
      '#type' => 'details',
      '#title' => $this->t('Test connection'),
      '#description' => 'Send request using the selected authentication key.',
      '#open' => TRUE,
    ];
    $form['test_connection']['test_connection_response'] = [
      '#type' => 'item',
    ];
    $form['test_connection']['test_connection_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send request'),
      '#disabled' => !$form['authentication']['key']['#access'],
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
          ':input[name="key"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
      '#submit' => ['::submitTestConnection'],
    ];

    $form['actions']['submit']['#disabled'] = !$form['authentication']['key']['#access'];
    $form['actions']['submit']['#states'] = [
      'enabled' => [
        ':input[name="key"]' => [
          'checked' => TRUE,
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('key') === NULL) {
      if ($form['authentication']['key']['#access'] === FALSE) {
        $form_state->setError($form, $this->t('Select an authentication key.'));
      }
      return;
    }

    $key = $this->keyRepository->getKey($form_state->getValue('key'));
    try {
      $this->sdkConnector->testConnection($key);
    }
    catch (KeyValueNotRetrievedException $exception) {
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
    $this->config('apigee_edge.authentication')
      ->set('active_key', $form_state->getValue('key'))
      ->save();

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
   * Build URL for user synchronization processes, using CSRF protection.
   *
   * @param string $route_name
   *   The name of the route.
   *
   * @return \Drupal\Core\Url
   *   The URL to redirect to.
   */
  protected function buildUrl(string $route_name) {
    $url = Url::fromRoute($route_name);
    $token = \Drupal::csrfToken()->get($url->getInternalPath());
    $url->setOptions(['query' => ['destination' => '/admin/config/apigee-edge/settings', 'token' => $token]]);
    return $url;
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
