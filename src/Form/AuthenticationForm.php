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
use Drupal\apigee_edge\Exception\AuthenticationKeyValueMalformedException;
use Drupal\apigee_edge\OauthTokenStorageInterface;
use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Random;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\StatusMessages;
use Drupal\Core\Render\RendererInterface;
use Drupal\key\Entity\Key;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\key\Plugin\KeyPluginFormInterface;
use Drupal\key\Plugin\KeyProviderSettableValueInterface;
use GuzzleHttp\Exception\ConnectException;
use Http\Client\Exception\NetworkException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for saving the Apigee Edge API authentication key.
 */
class AuthenticationForm extends ConfigFormBase {

  /**
   * The config named used by this form.
   */
  const CONFIG_NAME = 'apigee_edge.auth';

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
   * The active key.
   *
   * @var \Drupal\key\KeyInterface
   */
  protected $activeKey;

  /**
   * The OAuth token storage service.
   *
   * @var \Drupal\apigee_edge\OauthTokenStorageInterface
   */
  protected $oauthTokenStorage;

  /**
   * The renderer is used for better control in the ajax callback.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new AuthenticationForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   SDK connector service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param \Drupal\apigee_edge\OauthTokenStorageInterface $oauth_token_storage
   *   The OAuth token storage service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The `renderer` service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, KeyRepositoryInterface $key_repository, SDKConnectorInterface $sdk_connector, ModuleHandlerInterface $module_handler, OauthTokenStorageInterface $oauth_token_storage, RendererInterface $renderer) {
    parent::__construct($config_factory);
    $this->keyRepository = $key_repository;
    $this->sdkConnector = $sdk_connector;
    $this->moduleHandler = $module_handler;
    $this->oauthTokenStorage = $oauth_token_storage;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('key.repository'),
      $container->get('apigee_edge.sdk_connector'),
      $container->get('module_handler'),
      $container->get('apigee_edge.authentication.oauth_token_storage'),
      $container->get('renderer')
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
    return [static::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\key\Exception\KeyValueNotSetException
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.admin';

    // Save the key for later use.
    $this->activeKey = $active_key = $this->getActiveKey();

    // Placeholder for messages.
    $form['messages'] = [
      '#markup' => '<div id="apigee-edge-auth-form-messages"></div>',
    ];

    $form['connection_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Apigee Edge connection settings'),
      '#description' => $this->t('Apigee Edge connection settings.'),
      '#open' => TRUE,
      '#parents' => ['key_input_settings'],
      '#tree' => TRUE,
    ];
    if ($active_key->getKeyInput() instanceof KeyPluginFormInterface) {
      // Save the settings in form state.
      $form_state->set('key_value', $this->getKeyValueValues($active_key));
      // Create a form state for this sub form.
      $plugin_form_state = $this->createPluginFormState('key_input', $form_state);
      $plugin_form = $active_key->getKeyInput()->buildConfigurationForm([], $plugin_form_state);

      $form['connection_settings'] += $plugin_form;
      $form_state->setValue('connection_settings', $plugin_form_state->getValues());
    }

    $submittable_state = [
      [
        ':input[name="key_input_settings[auth_type]"]' => ['value' => EdgeKeyTypeInterface::EDGE_AUTH_TYPE_BASIC],
        ':input[name="key_input_settings[password]"]' => ['filled' => TRUE],
        ':input[name="key_input_settings[organization]"]' => ['filled' => TRUE],
        ':input[name="key_input_settings[username]"]' => ['filled' => TRUE],
      ],
      'xor',
      [
        ':input[name="key_input_settings[auth_type]"]' => ['value' => EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH],
        ':input[name="key_input_settings[password]"]' => ['filled' => TRUE],
        ':input[name="key_input_settings[organization]"]' => ['filled' => TRUE],
        ':input[name="key_input_settings[username]"]' => ['filled' => TRUE],
      ],
    ];
    // Placeholder for debug.
    $form['debug_placeholder'] = [
      '#markup' => '<div id="apigee-edge-auth-form-debug-info"></div>',
    ];
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

    $form['test_connection'] = [
      '#prefix' => '<div id="apigee-edge-connection-info">',
      '#suffix' => '</div>',
      '#type' => 'details',
      '#title' => $this->t('Test connection'),
      '#description' => 'Send request using the selected authentication key.',
      '#open' => TRUE,
    ];
    $form['test_connection']['test_connection_submit'] = [
      '#type' => 'submit',
      '#executes_submit_callback' => FALSE,
      '#value' => $this->t('Send request'),
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'apigee-edge-connection-info',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Waiting for response...'),
        ],
      ],
      '#states' => [
        'enabled' => $submittable_state,
      ],
    ];

    $form['actions']['submit']['#states'] = ['enabled' => $submittable_state];
    $form['actions']['#disabled'] = !$this->keyIsWritable($active_key);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $active_key = $this->activeKey;

    // Check whether or not we know how to write to this key.
    if ($this->keyIsWritable($active_key)) {
      // Get the key input plugin.
      $input_plugin = $active_key->getKeyInput();
      $plugin_form_state = $this->createPluginFormState('key_input', $form_state);

      $processed_values = $input_plugin->processSubmittedKeyValue($plugin_form_state);

      if ($form_state->isSubmitted()) {
        // Save the processed value for the submit handler.
        $form_state->set('processed_submitted', $processed_values['processed_submitted']);
      }

      // Validate the key using the input plugin.
      // @see: `\Drupal\key\Form\KeyFormBase::validateForm()`.
      $input_plugin->validateConfigurationForm($form, $plugin_form_state);
      $form_state->setValue('key_input_settings', $plugin_form_state->getValues());
      $this->moveFormStateErrors($plugin_form_state, $form_state);
      $this->moveFormStateStorage($plugin_form_state, $form_state);

      // Create a temp key for testing.
      $random = new Random();
      $test_key = Key::create([
        'id' => strtolower($random->name(16)),
        'key_type' => $active_key->getKeyType()->getPluginID(),
        'key_input' => $active_key->getKeyInput()->getPluginID(),
        'key_provider' => 'config',
      ]);
      // Set the key_value value on the test key.
      $test_key->getKeyProvider()
        ->setKeyValue($test_key, $processed_values['processed_submitted']);
    }
    else {
      // There will be no input form for the key since it's not writable so just
      // use the active key for testing.
      $test_key = $active_key;
    }

    // Test the connection.
    if (!empty($test_key->getKeyValue())) {
      /** @var \Drupal\apigee_edge\Plugin\KeyType\ApigeeAuthKeyType $test_key_type */
      $test_auth_type = $test_key->getKeyType()->getAuthenticationType($test_key);
      try {
        if ($test_auth_type === EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH) {
          // Check the requirements first.
          $this->oauthTokenStorage->checkRequirements();
          // Clear existing token data.
          $this->oauthTokenStorage->removeToken();
        }
        // TODO If email field contains an invalid email address
        // (form validation fails) then "Send request" should not send an API
        // request.
        $this->sdkConnector->testConnection($test_key);
        $this->messenger()->addStatus($this->t('Connection successful.'));
      }
      catch (\Exception $exception) {
        watchdog_exception('apigee_edge', $exception);

        $form_state->setError($form, $this->t('@suggestion Error message: %response', [
          '@suggestion' => $this->createSuggestion($exception, $test_key),
          '%response' => $exception->getMessage(),
        ]));

        // Display debug information.
        $form['debug']['#access'] = $form['debug']['debug_text']['#access'] = TRUE;
        $form['debug']['debug_text']['#value'] = $this->createDebugText($exception, $test_key, NULL);
      }
      finally {
        if ($test_auth_type === EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH) {
          // Clear keys that may have been saved during testing.
          $this->oauthTokenStorage->removeToken();
        }
      }
    }
    else {
      $form_state->setError($form, $this->t('Connection information is not available in the currently active key.'));
    }

    // Throw an error if the key is not writable.
    if ($form_state->isSubmitted() && !$this->keyIsWritable($active_key)) {
      $form_state->setError($form, $this->t('The active authentication key is not writable.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the processed value from the form state.
    $processed_submitted = $form_state->get('processed_submitted');

    if (!empty($processed_submitted) && $this->keyIsWritable($this->activeKey)) {
      // Set the active key's value.
      $this->activeKey
        ->getKeyProvider()
        ->setKeyValue($this->activeKey, $processed_submitted);
    }

    parent::submitForm($form, $form_state);
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
    $fail_text = $this->t('Failed to connect to Apigee Edge.');
    // General error message.
    $suggestion = $this->t('@fail_text', [
      '@fail_text' => $fail_text,
    ]);
    /** @var \Drupal\apigee_edge\Plugin\KeyType\ApigeeAuthKeyType $key_type */
    $key_type = $key->getKeyType();

    // Failed to connect to the Oauth authorization server.
    if ($exception instanceof OauthAuthenticationException) {
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
              '%connect_timeout' => $this->config('apigee_edge.client')->get('http_client_connect_timeout'),
              '%timeout' => $this->config('apigee_edge.client')->get('http_client_timeout'),
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
              '%connect_timeout' => $this->config('apigee_edge.client')->get('http_client_connect_timeout'),
              '%timeout' => $this->config('apigee_edge.client')->get('http_client_timeout'),
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
    /** @var \Drupal\apigee_edge\Plugin\KeyType\ApigeeAuthKeyType $key_type */
    $key_type = $key->getKeyType();

    $credentials = [];

    try {
      $credentials['endpoint'] = $key_type->getEndpoint($key);
      $credentials['organization'] = $key_type->getOrganization($key);
      $credentials['username'] = $key_type->getUsername($key);
    }
    catch (AuthenticationKeyValueMalformedException $exception) {
      // Could not read the credentials because the key value storage is
      // malformed.
    }

    $keys = [
      'key_type' => get_class($key_type),
      'key_provider' => get_class($key->getKeyProvider()),
    ];

    // TODO
    // * Remove deprecated code.
    // * Fix implementation of this method.
    // * Provide debug message for OauthTokenStorageException.
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
      json_encode($this->config('apigee_edge.client')->get(), JSON_PRETTY_PRINT) .
      PHP_EOL .
      $exception_text;

    return $text;
  }

  /**
   * Pass form array to the AJAX callback.
   *
   * @param array &$form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   *
   * @throws \Exception
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    // Get any status messages so they can be rendered in the placeholder.
    $status_messages = $this->renderer->render(StatusMessages::renderMessages());
    // Clear any existing messages from the initial page load.
    $response->addCommand(new ReplaceCommand('div.messages', ''));
    $response->addCommand(new ReplaceCommand('#apigee-edge-auth-form-messages', "<div id=\"apigee-edge-auth-form-messages\">{$status_messages}</div>"));

    // Only render the debug element if it's been enabled by validation.
    if ($form['debug']['#access']) {
      // Add a prefix to the debug section to replace the wrapper.
      $form['debug'] = $form['debug'] + [
        '#prefix' => '<div id="apigee-edge-auth-form-debug-info">',
        '#suffix' => '</div>',
      ];
      $response->addCommand(new ReplaceCommand('#apigee-edge-auth-form-debug-info', $this->renderer->render($form['debug'])));
    }
    else {
      // There may still be an outdated debug message so clear it.
      $response->addCommand(new ReplaceCommand('#apigee-edge-auth-form-debug-info', '<div id="apigee-edge-auth-form-debug-info"></div>'));
    }

    return $response;
  }

  /**
   * Get's the current active key or a newly generated one.
   *
   * @return \Drupal\key\KeyInterface
   *   The current active key or a new auth key.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\key\Exception\KeyValueNotSetException
   */
  protected function getActiveKey(): KeyInterface {
    $config = $this->config(static::CONFIG_NAME);

    // Gets the active key.
    if (!($active_key_id = $config->get('active_key')) || !($active_key = Key::load($active_key_id))) {
      $active_key = $this->generateNewAuthKey();
    }

    return $active_key;
  }

  /**
   * Creates a new auth key stored in a file.
   *
   * @return \Drupal\key\KeyInterface
   *   A new auth key.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\key\Exception\KeyValueNotSetException
   */
  protected function generateNewAuthKey(): KeyInterface {
    // Create a new key name.
    $new_key_id = 'apigee_edge_connection_default';
    $file_path = "private://.apigee_edge/{$new_key_id}.json";
    $new_key = NULL;

    // Make sure the key and the associated key file do not exist.
    for ($i = 1; (Key::load($new_key_id) || file_exists($file_path)); $i++) {
      $new_key_id = "apigee_edge_connection_default_{$i}";
      $file_path = "private://.apigee_edge/{$new_key_id}.json";
    }

    // Create a new key.
    $new_key = Key::create([
      'id' => $new_key_id,
      'label' => $this->t('Apigee Edge connection'),
      'description' => $this->t('Contains the credentials for connecting to Apigee Edge.'),
      'key_type' => 'apigee_auth',
      'key_input' => 'apigee_auth_input',
      'key_provider' => 'apigee_edge_private_file',
    ]);
    $new_key->save();

    /** @var \Drupal\apigee_edge\Plugin\KeyProvider\PrivateFileKeyProvider $provider */
    $provider = $new_key->getKeyProvider();
    // Write out an empty key.
    $provider->setKeyValue($new_key, Json::encode((object) ['auth_type' => EdgeKeyTypeInterface::EDGE_AUTH_TYPE_BASIC]));

    // Save the active key.
    $this
      ->config(static::CONFIG_NAME)
      ->set('active_key', $new_key->id())
      ->save();

    return $new_key;
  }

  /**
   * Moves form errors from one form state to another.
   *
   * Copied from `\Drupal\key\Form\KeyFormBase::moveFormStateErrors()`.
   *
   * @param \Drupal\Core\Form\FormStateInterface $from
   *   The form state object to move from.
   * @param \Drupal\Core\Form\FormStateInterface $to
   *   The form state object to move to.
   */
  protected function moveFormStateErrors(FormStateInterface $from, FormStateInterface $to) {
    foreach ($from->getErrors() as $name => $error) {
      $to->setErrorByName($name, $error);
    }
  }

  /**
   * Moves storage variables from one form state to another.
   *
   * Copied from `\Drupal\key\Form\KeyFormBase::moveFormStateStorage()`.
   *
   * @param \Drupal\Core\Form\FormStateInterface $from
   *   The form state object to move from.
   * @param \Drupal\Core\Form\FormStateInterface $to
   *   The form state object to move to.
   */
  protected function moveFormStateStorage(FormStateInterface $from, FormStateInterface $to) {
    foreach ($from->getStorage() as $index => $value) {
      $to->set($index, $value);
    }
  }

  /**
   * Creates a FormStateInterface object for a plugin.
   *
   * Copied from `\Drupal\key\Form\KeyFormBase::createPluginFormState()`.
   *
   * @param string $type
   *   The plugin type ID.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state to copy values from.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   A clone of the form state object with values from the plugin.
   */
  protected function createPluginFormState($type, FormStateInterface $form_state) {
    // Clone the form state.
    $plugin_form_state = clone $form_state;

    // Clear the values, except for this plugin type's settings.
    $plugin_form_state->setValues($form_state->getValue($type . '_settings', []));

    return $plugin_form_state;
  }

  /**
   * Tests whether we know how to write to a key.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key to test.
   *
   * @return bool
   *   Whether the file key is writable.
   */
  protected function keyIsWritable(KeyInterface $key) {
    return ($key->getKeyProvider() instanceof KeyProviderSettableValueInterface);
  }

  /**
   * Get `key_value` values for further processing.
   *
   * @param \Drupal\key\KeyInterface $key
   *   An auth key.
   *
   * @return array
   *   An array or values processed by the plugin form.
   */
  protected function getKeyValueValues(KeyInterface $key) {
    // Setup options for `::obscureKeyValue`.
    $obscure_options = [
      'key_type_id' => $key->getKeyType()->getPluginId(),
      'key_type_group' => $key->getKeyType()->getPluginDefinition()['group'],
      'key_provider_id' => $key->getKeyProvider()->getPluginId(),
    ];

    // Get values.
    $original = $key->getKeyValue();
    $processed_original = $key->getKeyInput()->processExistingKeyValue($original);
    $obscured = $key->getKeyProvider()->obscureKeyValue($processed_original, $obscure_options);

    return [
      'original' => $original,
      'processed_original' => $processed_original,
      'obscured' => $obscured,
      'current' => (!empty($obscured)) ? $obscured : $processed_original,
    ];
  }

  /**
   * Get the Form entity.
   *
   * Some of the legacy `key_input` plugins rely on this being an entity form so
   * we need to return the active key as the entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The active key.
   */
  public function getEntity() {
    return $this->activeKey;
  }

}
