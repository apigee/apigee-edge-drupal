<?php

namespace Drupal\apigee_edge\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for saving the API credentials.
 *
 * @ingroup apigee_edge
 */
class AuthenticationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_authentication';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $credentials_storage_types = array('Private file', 'Environment variable', 'Database');

    $form['credentials_storage'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Credentials storage type'),
    );
    $form['credentials_storage']['credentials_storage_type'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select how to store credentials'),
      '#options' => $credentials_storage_types,
    );

    $form['credentials'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Credentials'),
    );
    $form['credentials']['credentials_api_base_url'] = array(
      '#type' => 'textfield',
      '#title' => t('API base URL'),
    );
    $form['credentials']['credentials_api_username'] = array(
      '#type' => 'textfield',
      '#title' => t('API username'),
    );
    $form['credentials']['credentials_api_password'] = array(
      '#type' => 'textfield',
      '#title' => t('API password'),
    );

    $form['credentials_save'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save API credentials'),
    );

    $type = \Drupal::service('plugin.manager.credentials_storage');
    $plugin_definitions = $type->getDefinitions();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }
}