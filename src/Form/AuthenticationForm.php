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

use Apigee\Edge\Exception\ApiRequestException;
use Apigee\Edge\Exception\OauthAuthenticationException;
use Apigee\Edge\HttpClient\Plugin\Authentication\Oauth;
use Drupal\apigee_edge\Plugin\KeyType\OauthKeyType;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\Exception\ConnectException;
use Http\Client\Exception\NetworkException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for saving the Apigee Edge API authentication key.
 */
class AuthenticationForm extends ConfigFormBase {

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

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
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new AuthenticationForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key/value store.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   SDK connector service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, KeyRepositoryInterface $key_repository, SDKConnectorInterface $sdk_connector, ModuleHandlerInterface $module_handler) {
    parent::__construct($config_factory);
    $this->state = $state;
    $this->keyRepository = $key_repository;
    $this->sdkConnector = $sdk_connector;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('key.repository'),
      $container->get('apigee_edge.sdk_connector'),
      $container->get('module_handler')
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
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $keys = $this->state->get('apigee_edge.auth');
    $form = parent::buildForm($form, $form_state);
    $form['#prefix'] = '<div id="apigee-edge-auth-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.admin';

    $form['debug'] = [
      '#type' => 'details',
      '#title' => $this->t('Debug information'),
      '#access' => FALSE,
      '#open' => FALSE,
    ];
    $form['debug']['debug_text'] = [
      '#type' => 'textarea',
      '#disabled' => TRUE,
      '#rows' => 20,
    ];

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
    $basic_auth_default_value = array_key_exists($keys['active_key'], $basic_auth_keys) ? $keys['active_key'] : NULL;

    // Loading OAuth keys.
    $oauth_keys = $this->keyRepository->getKeyNamesAsOptions(['type' => 'apigee_edge_oauth']);
    foreach ($oauth_keys as $key_id => $key_name) {
      $oauth_keys[$key_id] = $this->t('@key_name <a href=":url">Edit</a>', [
        '@key_name' => $key_name,
        ':url' => Url::fromRoute('entity.key.edit_form', ['key' => $key_id, 'destination' => 'admin/config/apigee-edge/settings'])->toString(),
      ]);
    }
    $oauth_default_value = array_key_exists($keys['active_key'], $oauth_keys) ? $keys['active_key'] : NULL;

    // Loading OAuth token keys.
    $oauth_token_keys = $this->keyRepository->getKeyNamesAsOptions(['type' => 'apigee_edge_oauth_token']);
    foreach ($oauth_token_keys as $key_id => $key_name) {
      $oauth_token_keys[$key_id] = $this->t('@key_name <a href=":url">Edit</a>', [
        '@key_name' => $key_name,
        ':url' => Url::fromRoute('entity.key.edit_form', ['key' => $key_id, 'destination' => 'admin/config/apigee-edge/settings'])->toString(),
      ]);
    }
    $oauth_token_default_value = array_key_exists($keys['active_key_oauth_token'], $oauth_token_keys) ? $keys['active_key_oauth_token'] : NULL;

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
      '#submit' => ['::validateForm'],
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
      // Ensure that testing connection using clean token storage.
      if (isset($key_token)) {
        $key_token->deleteKeyValue();
      }
      $this->sdkConnector->testConnection($key, $key_token);
      $this->messenger()->addStatus($this->t('Connection successful.'));
    }
    catch (\Exception $exception) {
      watchdog_exception('apigee_edge', $exception);

      $form_state->setError($form, $this->t('@suggestion Error message: %response.', [
        '@suggestion' => $this->createSuggestion($exception, $key),
        '%response' => $exception->getMessage(),
      ]));

      // Display debug information.
      $form['debug']['#access'] = $form['debug']['debug_text']['#access'] = TRUE;
      $form['debug']['debug_text']['#value'] = $this->createDebugText($exception, $key, $key_token);
    }
  }

  /**
   * Creates a suggestion text to be displayed in the connection failed message.
   *
   * @param \Exception $exception
   *   The thrown exception during form validation.
   * @param \Drupal\key\KeyInterface $key
   *   The used key during form validation.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The suggestion text to be displayed.
   */
  protected function createSuggestion(\Exception $exception, KeyInterface $key): MarkupInterface {
    /** @var \Drupal\apigee_edge\Plugin\KeyType\BasicAuthKeyType $key_type */
    $key_type = $key->getKeyType();

    // Failed to connect to the Oauth authorization server.
    if ($exception instanceof OauthAuthenticationException) {
      /** @var \Drupal\apigee_edge\Plugin\KeyType\OauthKeyType $key_type */
      $fail_text = $this->t('Failed to connect to the OAuth authorization server.');
      // General error message.
      $suggestion = $this->t('@fail_text Check the debug information below for more details.', [
        '@fail_text' => $fail_text,
      ]);
      // Invalid credentials.
      if ($exception->getCode() === 401) {
        // Invalid credentials using defined client_id/client_secret.
        if ($key_type->getClientId($key) !== Oauth::DEFAULT_CLIENT_ID || $key_type->getClientSecret($key) !== Oauth::DEFAULT_CLIENT_SECRET) {
          $suggestion = $this->t('@fail_text The given username (%username) or password or client ID (%client_id) or client secret is incorrect.', [
            '@fail_text' => $fail_text,
            '%client_id' => $key_type->getClientId($key),
            '%username' => $key_type->getUsername($key),
          ]);
        }
        // Invalid credentials using default client_id/client_secret.
        else {
          $suggestion = $this->t('@fail_text The given username (%username) or password is incorrect.', [
            '@fail_text' => $fail_text,
            '%username' => $key_type->getUsername($key),
          ]);
        }
      }
      // Failed request.
      elseif ($exception->getCode() === 0) {
        if ($exception->getPrevious() instanceof ApiRequestException && $exception->getPrevious()->getPrevious() instanceof NetworkException && $exception->getPrevious()->getPrevious()->getPrevious() instanceof ConnectException) {
          /** @var \GuzzleHttp\Exception\ConnectException $curl_exception */
          $curl_exception = $exception->getPrevious()->getPrevious()->getPrevious();
          // Resolving timed out.
          if ($curl_exception->getHandlerContext()['errno'] === CURLE_OPERATION_TIMEDOUT) {
            $suggestion = $this->t('@fail_text The connection timeout threshold (%connect_timeout) or the request timeout (%timeout) is too low or something is wrong with the connection.', [
              '@fail_text' => $fail_text,
              '%connect_timeout' => $this->state->get('apigee_edge.client')['http_client_connect_timeout'],
              '%timeout' => $this->state->get('apigee_edge.client')['http_client_timeout'],
            ]);
          }
          // The remote host was not resolved (authorization server).
          if ($curl_exception->getHandlerContext()['errno'] === CURLE_COULDNT_RESOLVE_HOST) {
            $suggestion = $this->t('@fail_text The given authorization server (%authorization_server) is incorrect or something is wrong with the connection.', [
              '@fail_text' => $fail_text,
              '%authorization_server' => $key_type->getAuthorizationServer($key),
            ]);
          }
        }
      }
    }
    // Failed to connect to Apigee Edge (basic authentication or bearer
    // authentication).
    else {
      $fail_text = $this->t('Failed to connect to Apigee Edge.');
      // General error message.
      $suggestion = $this->t('@fail_text Check the debug information below for more details.', [
        '@fail_text' => $fail_text,
      ]);
      // Invalid credentials.
      if ($exception->getCode() === 401) {
        $suggestion = $this->t('@fail_text The given username (%username) or password is incorrect.', [
          '@fail_text' => $fail_text,
          '%username' => $key_type->getUsername($key),
        ]);
      }
      // Invalid organization name.
      elseif ($exception->getCode() === 403) {
        $suggestion = $this->t('@fail_text The given organization name (%organization) is incorrect.', [
          '@fail_text' => $fail_text,
          '%organization' => $key_type->getOrganization($key),
        ]);
      }
      // Failed request.
      elseif ($exception->getCode() === 0) {
        if ($exception->getPrevious() instanceof NetworkException && $exception->getPrevious()->getPrevious() instanceof ConnectException) {
          /** @var \GuzzleHttp\Exception\ConnectException $curl_exception */
          $curl_exception = $exception->getPrevious()->getPrevious();
          // Resolving timed out.
          if ($curl_exception->getHandlerContext()['errno'] === CURLE_OPERATION_TIMEDOUT) {
            $suggestion = $this->t('@fail_text The connection timeout threshold (%connect_timeout) or the request timeout (%timeout) is too low or something is wrong with the connection.', [
              '@fail_text' => $fail_text,
              '%connect_timeout' => $this->state->get('apigee_edge.client')['http_client_connect_timeout'],
              '%timeout' => $this->state->get('apigee_edge.client')['http_client_timeout'],
            ]);
          }
          // The remote host was not resolved (endpoint).
          elseif ($curl_exception->getHandlerContext()['errno'] === CURLE_COULDNT_RESOLVE_HOST) {
            $suggestion = $this->t('@fail_text The given endpoint (%endpoint) is incorrect or something is wrong with the connection.', [
              '@fail_text' => $fail_text,
              '%endpoint' => $key_type->getEndpoint($key),
            ]);
          }
        }
      }
    }

    return $suggestion;
  }

  /**
   * Creates debug text if there was an error during form validation.
   *
   * @param \Exception $exception
   *   The thrown exception during form validation.
   * @param \Drupal\key\KeyInterface $key
   *   The used key during form validation.
   * @param \Drupal\key\KeyInterface|null $key_token
   *   The user token key during form validation.
   *
   * @return string
   *   The debug text to be displayed.
   */
  protected function createDebugText(\Exception $exception, KeyInterface $key, ?KeyInterface $key_token): string {
    /** @var \Drupal\apigee_edge\Plugin\KeyType\BasicAuthKeyType $key_type */
    $key_type = $key->getKeyType();

    $credentials = [
      'endpoint' => $key_type->getEndpoint($key),
      'organization' => $key_type->getOrganization($key),
      'username' => $key_type->getUsername($key),
    ];

    $keys = [
      'key_type' => get_class($key_type),
      'key_provider' => get_class($key->getKeyProvider()),
    ];

    if ($key_type instanceof OauthKeyType) {
      /** @var \Drupal\apigee_edge\Plugin\KeyType\OauthKeyType $key_type */
      $credentials['authorization_server'] = $key_type->getAuthorizationServer($key);
      $credentials['client_id'] = $key_type->getClientId($key);
      $credentials['client_secret'] = $key_type->getClientSecret($key) === Oauth::DEFAULT_CLIENT_SECRET ? Oauth::DEFAULT_CLIENT_SECRET : '***client-secret***';

      $keys['key_token_type'] = get_class($key_token->getKeyType());
      $keys['key_token_provider'] = get_class($key_token->getKeyProvider());
    }

    $exception_text = (string) $exception;
    $exception_text = preg_replace('/(.*refresh_token=)([^\&\r\n]+)(.*)/', '$1***refresh-token***$3', $exception_text);
    $exception_text = preg_replace('/(.*mfa_token=)([^\&\r\n]+)(.*)/', '$1***mfa-token***$3', $exception_text);
    $exception_text = preg_replace('/(.*password=)([^\&\r\n]+)(.*)/', '$1***password***$3', $exception_text);
    $exception_text = preg_replace('/(Authorization: (Basic|Bearer) ).*/', '$1***credentials***', $exception_text);

    $text = json_encode($credentials, JSON_PRETTY_PRINT) .
      PHP_EOL .
      json_encode($keys, JSON_PRETTY_PRINT) .
      PHP_EOL .
      json_encode($this->state->get('apigee_edge.client'), JSON_PRETTY_PRINT) .
      PHP_EOL .
      $exception_text;

    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $keys = $this->state->get('apigee_edge.auth');
    if ($form_state->getValue('key_type') === 'apigee_edge_basic_auth') {
      $keys['active_key'] = $form_state->getValue('key_basic_auth');
      $keys['active_key_oauth_token'] = '';
      $this->state->set('apigee_edge.auth', $keys);
    }
    elseif ($form_state->getValue('key_type') === 'apigee_edge_oauth') {
      $keys['active_key'] = $form_state->getValue('key_oauth');
      $keys['active_key_oauth_token'] = $form_state->getValue('key_oauth_token');
      $this->state->set('apigee_edge.auth', $keys);
    }
    // Reset state's static cache to correctly display the active key in the
    // form's key list.
    $this->state->resetCache();
    parent::submitForm($form, $form_state);
  }

  /**
   * Pass form array to the AJAX callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   The AJAX response.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state): array {
    return $form;
  }

}
