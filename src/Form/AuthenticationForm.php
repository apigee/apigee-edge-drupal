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

use Apigee\Edge\Api\Management\Controller\OrganizationController;
use Apigee\Edge\HttpClient\Client;
use Drupal\apigee_edge\Credentials;
use Drupal\apigee_edge\CredentialsInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for saving the API credentials.
 */
class AuthenticationForm extends ConfigFormBase {

  /**
   * Implemented credentials storage classes.
   *
   * @var array
   */
  protected $credentialsStorageTypes;

  /**
   * Implemented authentication method classes.
   *
   * @var array
   */
  protected $authenticationMethodTypes;

  /**
   * The credentials storage plugin manager object.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $credentialsStoragePluginManager;

  /**
   * The authentication method plugin manager object.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $authenticationStoragePluginManager;

  /**
   * Constructs a new AuthenticationForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $credentials_storage_plugin_manager
   *   The manager for credentials storage plugins.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $authentication_method_plugin_manager
   *   The manager for authentication method plugins.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              PluginManagerInterface $credentials_storage_plugin_manager,
                              PluginManagerInterface $authentication_method_plugin_manager) {
    parent::__construct($config_factory);
    $this->credentialsStoragePluginManager = $credentials_storage_plugin_manager;
    $this->authenticationStoragePluginManager = $authentication_method_plugin_manager;

    foreach ($credentials_storage_plugin_manager->getDefinitions() as $key => $value) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $plugin_name */
      $plugin_name = $value['name'];
      $this->credentialsStorageTypes[$key] = $plugin_name->render();
    }

    foreach ($authentication_method_plugin_manager->getDefinitions() as $key => $value) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $plugin_name */
      $plugin_name = $value['name'];
      $this->authenticationMethodTypes[$key] = $plugin_name->render();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.apigee_edge.credentials_storage'),
      $container->get('plugin.manager.apigee_edge.authentication_method')
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
      'apigee_edge.credentials_storage',
      'apigee_edge.authentication_method',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="apigee-edge-auth-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.admin';

    $credentials_storage_config = $this->config('apigee_edge.credentials_storage');
    $authentication_method_config = $this->config('apigee_edge.authentication_method');

    /** @var \Drupal\apigee_edge\Credentials $credentials */
    $credentials = $this->credentialsStoragePluginManager->createInstance($credentials_storage_config->get('credentials_storage_type'))
      ->loadCredentials();

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
        ],
      ],
    ];

    $form['sync']['background_sync_submit'] = [
      '#title' => $this->t('Background...'),
      '#type' => 'link',
      '#url' => $this->buildUrl('apigee_edge.user_sync.schedule'),
      '#attributes' => [
        'class' => [
          'button',
        ],
      ],
    ];

    $form['sync']['sync_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'help--button',
      ],
    ];

    $form['sync']['sync_wrapper']['background_sync_text'] = [
      '#markup' => $this->t('A background sync is recommended for large numbers of developers.'),
    ];

    $form['credentials_storage'] = [
      '#type' => 'details',
      '#title' => $this->t('Credentials storage type'),
      '#description' => 'Select how to store the API credentials.',
      '#open' => TRUE,
    ];
    $form['credentials_storage']['credentials_storage_type'] = [
      '#type' => 'radios',
      '#options' => $this->credentialsStorageTypes,
      '#default_value' => $credentials_storage_config->get('credentials_storage_type'),
    ];

    $form['authentication_method'] = [
      '#type' => 'details',
      '#title' => $this->t('Authentication method type'),
      '#description' => 'Select an API authentication method.',
      '#open' => TRUE,
    ];
    $form['authentication_method']['authentication_method_type'] = [
      '#type' => 'radios',
      '#options' => $this->authenticationMethodTypes,
      '#default_value' => $authentication_method_config->get('authentication_method'),
    ];

    $state_event = 'visible';
    $credentials_states = [
      $state_event => [],
    ];

    $form['credentials'] = [
      '#type' => 'details',
      '#title' => $this->t('Credentials'),
      '#open' => TRUE,
    ];

    foreach ($this->credentialsStoragePluginManager->getDefinitions() as $key => $value) {
      /** @var \Drupal\apigee_edge\CredentialsStoragePluginInterface $instance */
      $instance = $this->credentialsStoragePluginManager->createInstance($key);
      if ($instance->readonly()) {
        $credentials_states[$state_event][] = 'or';
        $credentials_states[$state_event][] = [
          ':input[name="credentials_storage_type"]' => ['!value' => $key],
        ];
      }

      if (($helptext = $instance->helpText())) {
        // This should be a markup, not a checkbox, but the states api won't
        // work that way.
        $form['credentials']["help_{$key}"] = [
          '#type' => 'checkbox',
          '#title' => $helptext,
          '#attributes' => [
            'style' => 'display: none',
          ],
          '#prefix' => '<div class="apigee-auth-form-help-text">',
          '#suffix' => '</div>',
          '#states' => [
            'visible' => [
              [':input[name="credentials_storage_type"]' => ['value' => $key]],
            ],
          ],
        ];
      }
    }

    array_shift($credentials_states[$state_event]);

    $form['credentials']['credentials_api_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Apigee Edge endpoint'),
      '#description' => $this->t('Apigee Edge endpoint where the API calls are being sent. Defaults to the enterprise endpoint: %url.', ['%url' => $credentials::ENTERPRISE_ENDPOINT]),
      '#default_value' => $credentials->getEndpoint(),
      '#states' => $credentials_states,
    ];
    $form['credentials']['credentials_api_organization'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization'),
      '#description' => $this->t('Name of the organization on Edge. Changing this value could make your site stop working.'),
      '#default_value' => $credentials->getOrganization(),
      '#states' => $credentials_states,
    ];
    $form['credentials']['credentials_api_username'] = [
      '#type' => 'email',
      '#title' => $this->t('Username'),
      '#description' => $this->t("Organization user's email address that is used for authenticating with the endpoint."),
      '#default_value' => $credentials->getUsername(),
      '#states' => $credentials_states,
    ];
    $form['credentials']['credentials_api_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => t("Organization user's password that is used for authenticating with the endpoint."),
      '#default_value' => $credentials->getPassword(),
      '#states' => $credentials_states,
    ];

    $form['test_connection'] = [
      '#type' => 'details',
      '#title' => $this->t('Test connection'),
      '#description' => 'Send request using the given credentials and authentication method.',
      '#open' => TRUE,
    ];
    $form['test_connection']['test_connection_response'] = [
      '#type' => 'item',
    ];
    $form['test_connection']['test_connection_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send request'),
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'apigee-edge-auth-form',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Waiting for response...'),
        ],
      ],
      '#submit' => ['::submitTestConnection'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge\CredentialsStoragePluginInterface $credentials_storage */
    $credentials_storage = $this->credentialsStoragePluginManager
      ->createInstance($form_state->getValue('credentials_storage_type'));
    $credentials_storage_error = $credentials_storage->hasRequirements();
    if (!empty($credentials_storage_error)) {
      $form_state->setErrorByName('credentials_storage_type', $credentials_storage_error);
    }

    if ($credentials_storage->readonly()) {
      $credentials = $credentials_storage->loadCredentials();
    }
    else {
      if ($form_state->hasValue('credentials_api_password') && $form_state->has('ajax_credentials_api_password')) {
        $form_state->setValue('credentials_api_password', $form_state->get('ajax_credentials_api_password'));
      }
      $credentials = $this->createCredentials($form_state);
    }
    try {
      $auth = $this->authenticationStoragePluginManager
        ->createInstance($form_state->getValue('authentication_method_type'))
        ->createAuthenticationObject($credentials);
      $client = new Client($auth, NULL, $credentials->getEndpoint());
      $oc = new OrganizationController($client);
      $oc->load($credentials->getOrganization());
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
    try {
      foreach ($this->credentialsStorageTypes as $key => $value) {
        if ($form_state->getValue('credentials_storage_type') === $key) {
          $credentials = $this->createCredentials($form_state);

          $this->credentialsStoragePluginManager
            ->createInstance($form_state->getValue('credentials_storage_type'))
            ->saveCredentials($credentials);

          $this->config('apigee_edge.credentials_storage')
            ->set('credentials_storage_type', $form_state->getValue('credentials_storage_type'))
            ->save();
        }
        else {
          $this->credentialsStoragePluginManager
            ->createInstance($key)
            ->deleteCredentials();
        }
      }

      $this->config('apigee_edge.authentication_method')
        ->set('authentication_method', $form_state->getValue('authentication_method_type'))
        ->save();

      parent::submitForm($form, $form_state);
    }
    catch (\Exception $exception) {
      drupal_set_message($exception->getMessage(), 'error');
    }
  }

  /**
   * Creates a Credentials object from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\apigee_edge\CredentialsInterface
   *   The credentials object.
   */
  protected function createCredentials(FormStateInterface $form_state): CredentialsInterface {
    $credentials = new Credentials();
    $credentials->setEndpoint($form_state->getValue('credentials_api_endpoint'));
    $credentials->setOrganization($form_state->getValue('credentials_api_organization'));
    $credentials->setUsername($form_state->getValue('credentials_api_username'));
    $credentials->setPassword($form_state->getValue('credentials_api_password'));

    return $credentials;
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
    $url->setOptions(['query' => ['destination' => '/admin/config/apigee-edge', 'token' => $token]]);
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
    // Store the provided password, otherwise saving form after clicking on
    // "Send request" button is not going to work because password field
    // value becomes empty. (Password form elements has no default values.)
    $form_state->set('ajax_credentials_api_password', $form_state->getValue('credentials_api_password', ''));
    drupal_set_message($this->t('Connection successful.'));
  }

}
