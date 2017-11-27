<?php

namespace Drupal\apigee_edge\Form;

use Apigee\Edge\Api\Management\Controller\OrganizationController;
use Apigee\Edge\Entity\EntityControllerFactory;
use Apigee\Edge\HttpClient\Client;
use Drupal\apigee_edge\AuthenticationMethodManager;
use Drupal\apigee_edge\Credentials;
use Drupal\apigee_edge\CredentialsSaveException;
use Drupal\apigee_edge\CredentialsStorageManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for saving the API credentials.
 *
 * @ingroup apigee_edge
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
   * @var CredentialsStorageManager
   */
  protected $credentialsStoragePluginManager;

  /**
   * The authentication method plugin manager object.
   *
   * @var AuthenticationMethodManager
   */
  protected $authenticationStoragePluginManager;

  /**
   * Constructs a new AuthenticationForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\apigee_edge\CredentialsStorageManager $credentials_storage_plugin_manager
   *   The entity storage.
   * @param \Drupal\apigee_edge\AuthenticationMethodManager $authentication_method_plugin_manager
   *   The entity storage.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              CredentialsStorageManager $credentials_storage_plugin_manager,
                              AuthenticationMethodManager $authentication_method_plugin_manager) {
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

    /** @var Credentials $credentials */
    $credentials = $this->credentialsStoragePluginManager->createInstance($credentials_storage_config->get('credentials_storage_type'))->loadCredentials();

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

    $form['credentials']['credentials_api_organization'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Management API organization'),
      '#description' => $this->t('The v4 product organization name. Changing this value could make your site stop working.'),
      '#default_value' => $credentials->getOrganization(),
      '#states' => $credentials_states,
    ];
    $form['credentials']['credentials_api_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Management API endpoint URL'),
      '#description' => $this->t('URL to which to make Edge REST calls.'),
      '#default_value' => $credentials->getBaseURL(),
      '#states' => $credentials_states,
    ];
    $form['credentials']['credentials_api_username'] = [
      '#type' => 'email',
      '#title' => $this->t('Endpoint authenticated user'),
      '#description' => $this->t('User name used when authenticating with the endpoint. Generally this takes the form of an email address. (Only enter it if you want to change the existing user.)'),
      '#default_value' => $credentials->getUsername(),
      '#states' => $credentials_states,
    ];
    $form['credentials']['credentials_api_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Authenticated user’s password'),
      '#description' => t('Password used when authenticating with the endpoint. (Only enter it if you want to change the existing password.)'),
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

    $credentials = $credentials_storage->readonly() ?
      $credentials_storage->loadCredentials() :
      $this->createCredentials($form_state);
    try {
      $auth = $this->authenticationStoragePluginManager
        ->createInstance($form_state->getValue('authentication_method_type'))
        ->createAuthenticationObject($credentials);
      $client = new Client($auth);
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
    catch (CredentialsSaveException $exception) {
      drupal_set_message($exception->getMessage(), 'error');
    }
  }

  /**
   * Creates a Credentials object from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\apigee_edge\Credentials
   */
  protected function createCredentials(FormStateInterface $form_state) : Credentials {
    $credentials = new Credentials();
    $credentials->setBaseUrl($form_state->getValue('credentials_api_base_url'));
    $credentials->setOrganization($form_state->getValue('credentials_api_organization'));
    $credentials->setUsername($form_state->getValue('credentials_api_username'));
    $credentials->setPassword($form_state->getValue('credentials_api_password'));

    return $credentials;
  }

  public function ajaxCallback(array $form) : array {
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
  public function submitTestConnection(array $form, FormStateInterface $form_state) : void {
    $form_state->setRebuild();
    drupal_set_message($this->t('Connection successful.'));
  }

}
