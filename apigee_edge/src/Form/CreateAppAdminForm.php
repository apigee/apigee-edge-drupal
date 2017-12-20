<?php

namespace Drupal\apigee_edge\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CreateAppAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.createapp',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'create_app_admin_form';
  }

  /**
   * @var array
   */
  protected $configs;

  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
    $this->configs = [
      'multiple_products' => $this->t('Multiple products'),
      'display_as_checkboxes' => $this->t('Display the API Product widget as checkboxes instead of a select box'),
      'description_visible' => $this->t('Description field is visible'),
      'description_required' => $this->t('Description field is required'),
      'callback_url_visible' => $this->t('Callback URL field is visible'),
      'callback_url_required' => $this->t('Callback URL field is required'),
    ];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('apigee_edge.createapp');

    foreach ($this->configs as $name => $title) {
      $form[$name] = [
        '#type' => 'checkbox',
        '#title' => $title,
        '#default_value' => $config->get($name),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('apigee_edge.createapp');

    foreach (array_keys($this->configs) as $name) {
      $config->set($name, $form_state->getValue($name));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
