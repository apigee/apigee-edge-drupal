<?php

namespace Drupal\apigee_edge\Form;

use Drupal\apigee_edge\AuthenticationMethodManager;
use Drupal\apigee_edge\CredentialsStorageManager;
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

    foreach($credentials_storage_plugin_manager->getDefinitions() as $storage) {
      $this->credentialsStorageTypes[$storage['id']] = $storage['name']->render();
    }

    foreach($authentication_method_plugin_manager->getDefinitions() as $method) {
      $this->authenticationMethodTypes[$method['id']] = $method['name']->render();
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
    $credentials_storage_config = $this->config('apigee_edge.credentials_storage');
    $authentication_method_config = $this->config('apigee_edge.authentication_method');

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
    ];
    $form['credentials']['credentials_api_username'] = [
      '#type' => 'textfield',
      '#title' => t('API username'),
    ];
    $form['credentials']['credentials_api_password'] = [
      '#type' => 'textfield',
      '#title' => t('API password'),
    ];

    $form['test_connection'] = [
      '#type' => 'details',
      '#title' => t('Test connection'),
      '#description' => 'Send request using the given credentials and authentication method.',
      '#open' => TRUE,
    ];
    $form['test_connection']['test_connection_response'] = [
      '#type' => 'item',
      '#markup' => 'Response:',
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

  }

  /**
   * Sends API test request using the current form data and set the response text on the UI.
   *
   * @return AjaxResponse
   */
  public function submitTestConnection(array $form, FormStateInterface $form_state) : AjaxResponse {
    $ajax_response = new AjaxResponse();
    $response_wrapper = '#edit-test-connection-response';
    $response_text = 'Test';

    // TODO: Send an API test request and set the response text on the UI.

    $ajax_response->addCommand(new HtmlCommand($response_wrapper, $response_text));
    return $ajax_response;
  }
}
