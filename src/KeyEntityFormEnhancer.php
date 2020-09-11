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

namespace Drupal\apigee_edge;

use Apigee\Edge\Exception\ApiRequestException;
use Apigee\Edge\Exception\HybridOauth2AuthenticationException;
use Apigee\Edge\Exception\OauthAuthenticationException;
use Apigee\Edge\HttpClient\Plugin\Authentication\Oauth;
use Drupal\apigee_edge\Exception\AuthenticationKeyException;
use Drupal\apigee_edge\Exception\InvalidArgumentException;
use Drupal\apigee_edge\Exception\KeyProviderRequirementsException;
use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\apigee_edge\Plugin\KeyProviderRequirementsInterface;
use Drupal\apigee_edge\Plugin\KeyType\ApigeeAuthKeyType;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Component\Utility\Random;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\Element\StatusMessages;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\key\Form\KeyFormBase;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyProviderSettableValueInterface;
use GuzzleHttp\Exception\ConnectException;
use Http\Client\Exception\NetworkException;

/**
 * Enhances Apigee Edge related Key entity add/edit forms.
 *
 * This service only exists because it allows us to use dependency
 * injection and unit test our customizations.
 *
 * @internal This service should be replaced or decorated. This is the reason
 * why it does not define an interface either.
 */
final class KeyEntityFormEnhancer {

  use MessengerTrait;
  use StringTranslationTrait;
  // Required to be able to call validateForm() and similar methods with
  // defining them as static.
  use DependencySerializationTrait;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $connector;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The OAuth token storage.
   *
   * @var \Drupal\apigee_edge\OauthTokenStorageInterface
   */
  private $oauthTokenStorage;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * The email validator.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * KeyEntityFormEnhancer constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\OauthTokenStorageInterface $oauth_token_storage
   *   The OAuth token storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager serivce.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Component\Utility\EmailValidatorInterface $email_validator
   *   The email validator.
   */
  public function __construct(SDKConnectorInterface $connector, OauthTokenStorageInterface $oauth_token_storage, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, EmailValidatorInterface $email_validator) {
    $this->connector = $connector;
    $this->entityTypeManager = $entity_type_manager;
    $this->oauthTokenStorage = $oauth_token_storage;
    $this->configFactory = $config_factory;
    $this->emailValidator = $email_validator;
  }

  /**
   * Alters entity forms that defines an Apigee Edge authentication key.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  public function alterForm(array &$form, FormStateInterface $form_state): void {
    // Sanity check, form must be a Key form.
    if (!$form_state->getFormObject() instanceof KeyFormBase) {
      return;
    }

    /** @var \Drupal\key\KeyInterface $key */
    $key = $form_state->getFormObject()->getEntity();

    // Do not alter the confirmation step of the key edit form.
    if (!$key->isNew() && isset($form['confirm_edit'])) {
      return;
    }

    $form['#prefix'] = '<div id="apigee-edge-key-form-enhancer">';
    $form['#suffix'] = '</div>';

    // We can not add this when AJAX reloads the page and it is sure that this
    // is an Apigee Edge Authentication key but it only validates Apigee Edge
    // Authentication keys.
    $form['#validate'][] = [$this, 'validateForm'];

    // Add enhancements to Apigee Edge Authentication keys.
    if ($this->isApigeeKeyTypeAuthForm($form_state)) {

      /** @var \Drupal\apigee_edge\Plugin\KeyProviderRequirementsInterface $key_provider */
      $key_provider = $key->getKeyProvider();

      // Warn user about key provider pre-requirement issues before form
      // submission.
      if ($key_provider instanceof KeyProviderRequirementsInterface) {
        try {
          $key_provider->checkRequirements($key);
        }
        catch (KeyProviderRequirementsException $exception) {
          // Report key provider errors inline. This also allows us to clear
          // these error messages when the provider changes.
          $form['settings']['provider_section']['key_provider_error'] = [
            '#theme' => 'status_messages',
            '#message_list' => [
              'error' => [
                $this->t('The requirements of the selected %key_provider key provider are not fulfilled. Fix errors described below or change the key provider.', [
                  '%key_provider' => $key_provider->getPluginDefinition()['label'],
                ]),
                $exception->getTranslatableMarkupMessage(),
              ],
            ],
            // Display it on the top of the section.
            '#weight' => -100,
          ];
        }
      }

      // Placeholder for messages. This also must be part of the form always
      // because without it we could not render messages on the top of the form.
      $form['settings']['messages'] = [
        '#theme' => 'status_messages',
        '#message_list' => [],
        '#weight' => -100,
      ];

      // Placeholder for debug.
      $form['settings']['debug_placeholder'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['id' => 'apigee-edge-auth-form-debug-info'],
      ];
      $form['settings']['debug'] = [
        '#type' => 'details',
        '#title' => $this->t('Debug information'),
        '#access' => FALSE,
        '#open' => FALSE,
        '#theme_wrappers' => [
          'details' => [],
          'container' => ['#attributes' => ['id' => 'apigee-edge-auth-form-debug-info']],
        ],
      ];
      $form['settings']['debug']['debug_text'] = [
        '#type' => 'textarea',
        '#disabled' => TRUE,
        '#rows' => 20,
      ];

      $form['settings']['test_connection'] = [
        '#type' => 'details',
        '#title' => $this->t('Test connection'),
        '#description' => $this->t('Send request using the given API credentials.'),
        '#open' => TRUE,
        '#theme_wrappers' => [
          'details' => [],
          'container' => ['#attributes' => ['id' => 'apigee-edge-connection-info']],
        ],
      ];

      if (!$this->keyIsWritable($key)) {
        if ($key->isNew()) {
          $form['settings']['test_connection']['#description'] = $this->t('Send request using the stored credentials in the key provider');
        }
        else {
          $form['settings']['test_connection']['#description'] = $this->t("Send request using the <a href=':key_config_uri' target='_blank'>active authentication key</a>.", [
            ':key_config_uri' => $key->toUrl()->toString(),
          ]);
        }
      }

      $form['settings']['test_connection']['test_connection_submit'] = [
        '#type' => 'submit',
        '#executes_submit_callback' => FALSE,
        '#value' => $this->t('Send request'),
        '#name' => 'test_connection',
        '#ajax' => [
          'callback' => [$this, 'testConnectionAjaxCallback'],
          'wrapper' => 'apigee-edge-key-form-enhancer',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Waiting for response...'),
          ],
        ],
        '#states' => [
          'enabled' => [
            [
              ':input[name="key_input_settings[organization]"]' => ['empty' => FALSE],
              ':input[name="key_input_settings[password]"]' => ['empty' => FALSE],
              ':input[name="key_input_settings[username]"]' => ['empty' => FALSE],
            ],
            [
              ':input[name="key_input_settings[instance_type]"]' => ['value' => EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID],
              ':input[name="key_input_settings[organization]"]' => ['empty' => FALSE],
              ':input[name="key_input_settings[account_json_key]"]' => ['empty' => FALSE],
            ],
          ],
        ],
      ];
    }
  }

  /**
   * Additional validation handler for Apigee Edge authentication key forms.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Sanity check, if this is not a Apigee Edge Authentication key form
    // do nothing.
    if (!$this->isApigeeKeyTypeAuthForm($form_state)) {
      return;
    }

    // Only call this when form is saved or "Test connection" is clicked.
    if (!in_array($form_state->getTriggeringElement()['#name'] ?? [], ['test_connection', 'op'])) {
      return;
    }

    // If there is a form error already do not continue.
    if (!empty($form_state->getErrors())) {
      return;
    }

    /** @var \Drupal\key\KeyInterface $key */
    $key = $form_state->getFormObject()->getEntity();

    // Check whether or not we know how to write to this key.
    if ($this->keyIsWritable($key)) {
      // When form gets saved, key values are already processed.
      if (isset($form_state->getStorage()['key_value']['processed_submitted'])) {
        $key_value = $form_state->getStorage()['key_value']['processed_submitted'];
      }
      else {
        // When "Test connection" reloads the page they are not yet processed.
        // @see \Drupal\key\Form\KeyFormBase::createPluginFormState()
        $key_input_plugin_form_state = clone $form_state;
        $key_input_plugin_form_state->setValues($form_state->getValue('key_input_settings', []));
        // @see \Drupal\key\Form\KeyFormBase::validateForm()
        $key_input_processed_values = $key->getKeyInput()->processSubmittedKeyValue($key_input_plugin_form_state);
        $key_value = $key_input_processed_values['processed_submitted'];
        // Although, if the key exists, the key_value storage contains the
        // saved key. We must remove that from the storage otherwise an
        // invalid combination of the saved key values and the new values from
        // the input fields gets sent to Apigee Edge.
        $key_value_from_storage = &$form_state->get('key_value');
        unset($key_value_from_storage['original'], $key_value_from_storage['processed_original']);
      }

      // Create a temp key for testing without saving it.
      $random = new Random();
      /** @var \Drupal\key\KeyInterface $test_key */
      $test_key = $this->entityTypeManager->getStorage('key')->create([
        'id' => strtolower($random->name(16)),
        'key_type' => $key->getKeyType()->getPluginID(),
        'key_input' => $key->getKeyInput()->getPluginID(),
        'key_provider' => 'config',
        'key_provider_settings' => ['key_value' => $key_value],
      ]);
    }
    else {
      // There will be no input form for the key since it's not writable so
      // just use the actual key for testing.
      $test_key = clone $key;
    }

    /** @var \Drupal\apigee_edge\Plugin\KeyType\ApigeeAuthKeyType $test_key_type */
    $test_key_type = $test_key->getKeyType();
    $test_auth_type = $test_key_type->getAuthenticationType($test_key);
    try {
      if (in_array($test_auth_type, [EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH, EdgeKeyTypeInterface::EDGE_AUTH_TYPE_JWT])) {
        // Check the requirements first.
        $this->oauthTokenStorage->checkRequirements();
        // Clear existing OAuth token data.
        $this->cleanUpOauthTokenData();
      }
      // Test the connection.
      $this->connector->testConnection($test_key);
      $this->messenger()->addStatus($this->t('Connection successful.'));
    }
    catch (\Exception $exception) {
      watchdog_exception('apigee_edge', $exception);

      $form_state->setError($form, $this->t('@suggestion Error message: %response', [
        '@suggestion' => $this->createSuggestion($exception, $test_key),
        '%response' => $exception->getMessage(),
      ]));

      // Display debug information.
      $form['settings']['debug']['#access'] = $form['settings']['debug']['debug_text']['#access'] = TRUE;
      $form['settings']['debug']['debug_text']['#value'] = $this->createDebugText($exception, $test_key);

      // In case of a form validation error the password field should
      // still not clear the submitted value.
      // \Drupal\apigee_edge\Plugin\KeyInput\ApigeeAuthKeyInput::buildConfigurationForm()
      // does not get called in this case.
      if ($test_key_type->getInstanceType($test_key) != EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID) {
        $form['settings']['input_section']['key_input_settings']['password']['#attributes']['value'] = $test_key_type->getPassword($test_key);
      }
    }
    finally {
      // Clear Oauth token data that may have been saved during testing
      // connection or left behind when authentication type changed from OAuth
      // to basic.
      $this->cleanUpOauthTokenData();
    }

  }

  /**
   * Removes Oauth token data.
   */
  private function cleanUpOauthTokenData(): void {
    if ($this->oauthTokenStorage instanceof OauthTokenFileStorage) {
      $this->oauthTokenStorage->removeTokenFile();
    }
    else {
      $this->oauthTokenStorage->removeToken();
    }
  }

  /**
   * Checks whether a key form is an Apigee Edge authentication form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  private function isApigeeKeyTypeAuthForm(FormStateInterface $form_state): bool {
    // Sanity check, form must be a Key form.
    if (!$form_state->getFormObject() instanceof KeyFormBase) {
      FALSE;
    }
    /** @var \Drupal\key\KeyInterface $key */
    $key = $form_state->getFormObject()->getEntity();
    // When Ajax reloads the form - for example when Key provider changes -
    // the type of the entity falls back to the default "Authentication" type
    // on the Key add form.
    $key_type_from_user_input = $form_state->getUserInput()['key_type'] ?? '';

    return $key->getKeyType() instanceof ApigeeAuthKeyType || $key_type_from_user_input === 'apigee_auth';
  }

  /**
   * Ajax callback for test connection.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Render array.
   */
  public static function testConnectionAjaxCallback(array $form, FormStateInterface $form_state): array {
    // Capture and re-render all messages as part of the form so when AJAX
    // rebuilds the form all previous messages get removed from the UI.
    $original_weight = $form['settings']['messages']['#weight'] ?? 0;
    $form['settings']['messages'] = StatusMessages::renderMessages();
    $form['settings']['messages']['#weight'] = $original_weight;
    return $form;
  }

  /**
   * Checks whether we know how to write to a key.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key to test.
   *
   * @return bool
   *   Whether the key is writable.
   */
  private function keyIsWritable(KeyInterface $key): bool {
    return $key->getKeyProvider() instanceof KeyProviderSettableValueInterface;
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
  private function createSuggestion(\Exception $exception, KeyInterface $key): MarkupInterface {
    $fail_text = $this->t('Failed to connect to Apigee Edge.');
    // General error message.
    $suggestion = $this->t('@fail_text', [
      '@fail_text' => $fail_text,
    ]);
    /** @var \Drupal\apigee_edge\Plugin\KeyType\ApigeeAuthKeyType $key_type */
    $key_type = $key->getKeyType();

    if ($exception instanceof AuthenticationKeyException) {
      $suggestion = $this->t('@fail_text Verify the Apigee Edge connection settings.', [
        '@fail_text' => $fail_text,
      ]);
    }

    elseif ($exception instanceof HybridOauth2AuthenticationException) {
      $fail_text = $this->t('Failed to connect to the authorization server.');
      // General error message.
      $suggestion = $this->t('@fail_text Check the debug information below for more details.', [
        '@fail_text' => $fail_text,
      ]);

      // Invalid key / OpenSSL unable to sign data.
      if ($exception->getPrevious() && $exception->getPrevious() instanceof \DomainException) {
        $suggestion = $this->t('@fail_text The private key in the GCP service account key JSON is invalid.', [
          '@fail_text' => $fail_text,
        ]);
      }
    }

    // Failed to connect to the Oauth authorization server.
    elseif ($exception instanceof OauthAuthenticationException) {
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
      // TODO Remove the second condition which is a workaround for a
      // regression bug in the Apigee Edge for Public Cloud 19.03.01 release. If
      // valid organization name and username provided with an invalid password
      // the MGMT server returns HTTP 500 with an error instead of HTTP 401.
      if ($exception->getCode() === 401 || ($exception->getCode() === 500 && $exception->getEdgeErrorCode() === 'usersandroles.SsoInternalServerError')) {

        // If on public cloud, the username should be an email.
        if ($key_type->getInstanceType($key) === EdgeKeyTypeInterface::INSTANCE_TYPE_PUBLIC && !$this->emailValidator->isValid($key_type->getUsername($key))) {
          $suggestion = $this->t('@fail_text The organization username should be a valid email.', [
            '@fail_text' => $fail_text,
          ]);
        }
        else {
          $suggestion = $this->t('@fail_text The given username (%username) or password is incorrect.', [
            '@fail_text' => $fail_text,
            '%username' => $key_type->getUsername($key),
          ]);
        }
      }
      // Invalid organization name.
      elseif ($exception->getCode() === 404) {
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

        // If SDKConnector::testConnection() fails to retrieve a valid org,
        // then this exception is thrown.
        elseif ($exception instanceof InvalidArgumentException) {
          $suggestion = $this->t('@fail_text The given endpoint (%endpoint) is incorrect or something is wrong with the connection.', [
            '@fail_text' => $fail_text,
            '%endpoint' => $key_type->getEndpoint($key),
          ]);
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
   *
   * @return string
   *   The debug text to be displayed.
   */
  private function createDebugText(\Exception $exception, KeyInterface $key): string {
    $key_type = $key->getKeyType();
    $credentials = [];
    $keys = [
      'auth_type' => ($key_type instanceof EdgeKeyTypeInterface) ? $key_type->getAuthenticationType($key) : 'invalid credentials',
      'key_provider' => get_class($key->getKeyProvider()),
    ];

    if ($key_type instanceof EdgeKeyTypeInterface) {
      $credentials = [
        'endpoint' => $key_type->getEndpoint($key),
        'organization' => $key_type->getOrganization($key),
      ];

      if ($key_type->getInstanceType($key) != EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID) {
        $credentials['username'] = $key_type->getUsername($key);
      }

      if ($key_type->getAuthenticationType($key) === EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH) {
        $credentials['authorization_server'] = $key_type->getAuthorizationServer($key);
        $credentials['client_id'] = $key_type->getClientId($key);
        $credentials['client_secret'] = $key_type->getClientSecret($key) === Oauth::DEFAULT_CLIENT_SECRET ? Oauth::DEFAULT_CLIENT_SECRET : '***client-secret***';
      }
    }

    // Sanitize exception text.
    $exception_text = preg_replace([
      '/(.*refresh_token=)([^\&\r\n]+)(.*)/',
      '/(.*mfa_token=)([^\&\r\n]+)(.*)/',
      '/(.*password=)([^\&\r\n]+)(.*)/',
      '/(Authorization: (Basic|Bearer) ).*/',
    ], [
      '$1***refresh-token***$3',
      '$1***mfa-token***$3',
      '$1***password***$3',
      '$1***credentials***',
    ], (string) $exception);

    // Filter out any private values from config.
    $client_config = array_filter($this->configFactory->get('apigee_edge.client')->get(), static function ($key) {
      return !is_string($key) || $key[0] !== '_';
    }, ARRAY_FILTER_USE_KEY);

    return json_encode($credentials, JSON_PRETTY_PRINT) . PHP_EOL .
      json_encode($keys, JSON_PRETTY_PRINT) . PHP_EOL .
      json_encode($client_config, JSON_PRETTY_PRINT) . PHP_EOL .
      $exception_text;
  }

}
