<?php

namespace Drupal\apigee_edge\Form;

use Apigee\Edge\Entity\EntityControllerFactory;
use Apigee\Edge\HttpClient\Client;
use Drupal\apigee_edge\AuthenticationMethodManager;
use Drupal\apigee_edge\Credentials;
use Drupal\apigee_edge\CredentialsSaveException;
use Drupal\apigee_edge\CredentialsStorageManager;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
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
      $container->get('plugin.manager.credentials_storage'),
      $container->get('plugin.manager.authentication_method')
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
    $form['#attached']['library'][] = 'apigee_edge/authentication_form';

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

    $form['credentials'] = [
      '#type' => 'details',
      '#title' => $this->t('Credentials'),
      '#description' => 'Basic authentication parameters.',
      '#open' => TRUE,
    ];
    $form['credentials']['credentials_api_base_url'] = [
      '#type' => 'textfield',
      '#title' => t('API base URL'),
      '#default_value' => $credentials->getBaseURL(),
      '#required' => TRUE,
    ];
    $form['credentials']['credentials_api_organization'] = [
      '#type' => 'textfield',
      '#title' => t('Organization'),
      '#default_value' => $credentials->getOrganization(),
      '#required' => TRUE,
    ];
    $form['credentials']['credentials_api_username'] = [
      '#type' => 'email',
      '#title' => t('API username'),
      '#default_value' => $credentials->getUsername(),
      '#required' => TRUE,
    ];
    $form['credentials']['credentials_api_password'] = [
      '#type' => 'password',
      '#title' => t('API password'),
      '#required' => TRUE,
    ];

    $form['test_connection'] = [
      '#type' => 'details',
      '#title' => t('Test connection'),
      '#description' => 'Send request using the given credentials and authentication method.',
      '#open' => TRUE,
    ];
    $form['test_connection']['test_connection_response'] = [
      '#type' => 'item',
    ];
    $form['test_connection']['test_connection_submit'] = [
      '#type' => 'button',
      '#value' => t('Send request'),
      '#ajax' => [
        'callback' => '::submitTestConnection',
        'progress' => [
          'type' => 'throbber',
          'message' => t('Waiting for response...'),
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $credentials_storage_config = $this->config('apigee_edge.credentials_storage');

    try {
      foreach ($this->credentialsStorageTypes as $key => $value) {
        if ($form_state->getValue('credentials_storage_type') === $key) {
          $credentials = new Credentials();
          $credentials->setBaseURL($form_state->getValue('credentials_api_base_url'));
          $credentials->setOrganization($form_state->getValue('credentials_api_organization'));
          $credentials->setUsername($form_state->getValue('credentials_api_username'));
          $credentials->setPassword($form_state->getValue('credentials_api_password'));

          $this->credentialsStoragePluginManager
            ->createInstance($form_state->getValue('credentials_storage_type'))
            ->saveCredentials($credentials);

          $this->config('apigee_edge.credentials_storage')
            ->set('credentials_storage_type', $form_state->getValue('credentials_storage_type'))
            ->save();
        }
        else {
          $this->credentialsStoragePluginManager
            ->createInstance($credentials_storage_config->get($key))
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
   * API test connection.
   *
   * Sends API test request using the current form data and set
   * the response text on the UI.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return AjaxResponse
   *   The AjaxResponse object which displays the response.
   */
  public function submitTestConnection(array $form, FormStateInterface $form_state) : AjaxResponse {
    $ajax_response = new AjaxResponse();
    $response_wrapper = '#edit-test-connection-response';

    $credentials = new Credentials();
    $credentials->setBaseUrl($form_state->getValue('credentials_api_base_url'));
    $credentials->setOrganization($form_state->getValue('credentials_api_organization'));
    $credentials->setUsername($form_state->getValue('credentials_api_username'));
    $credentials->setPassword($form_state->getValue('credentials_api_password'));

    try {
      $auth = $this->authenticationStoragePluginManager
        ->createInstance($form_state->getValue('authentication_method_type'))
        ->createAuthenticationObject($credentials);
      $client = new Client($auth);
      $ecf = new EntityControllerFactory($credentials->getOrganization(), $client);
      $ecf->getControllerByEndpoint('organizations')
        ->load($credentials->getOrganization());

      $response_text = '<span class="test-connection-response-success">Connection successful</span>';
      $ajax_response->addCommand(new HtmlCommand($response_wrapper, $response_text));
    }
    catch (\Exception $exception) {
      $response_text = '<span class="test-connection-response-error">Connection error</span> '
        . Html::escape($exception->getCode())
        . ' '
        . Html::escape($exception->getMessage());
      $ajax_response->addCommand(new HtmlCommand($response_wrapper, $response_text));
    }

    return $ajax_response;
  }

}
