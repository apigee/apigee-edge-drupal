<?php

namespace Drupal\apigee_edge\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for saving the API credentials.
 *
 * @ingroup apigee_edge
 */
class AuthenticationForm extends ConfigFormBase {

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
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('apigee_edge.credentials_storage');
    $plugin_type = \Drupal::service('plugin.manager.credentials_storage');
    $plugin_definitions = $plugin_type->getDefinitions();

    foreach($plugin_definitions as $storage) {
      $storage_types[$storage['id']] = $storage['name']->render();
    }

    $form['credentials_storage'] = [
      '#type' => 'details',
      '#title' => $this->t('Credentials storage type'),
      '#description' => 'Select how to store credentials.',
      '#open' => TRUE,
    ];
    $form['credentials_storage']['credentials_storage_type'] = [
      '#type' => 'select',
      '#options' => $storage_types,
      '#default_value' => $config->get('credentials_storage_type'),
    ];

    $form['credentials'] = [
      '#type' => 'details',
      '#title' => $this->t('Credentials'),
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
      '#description' => 'Send request using the given credentials.',
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

    $form['credentials_save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save API credentials'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Send API test request using the current form data and set the response text on the UI.
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
