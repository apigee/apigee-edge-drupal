<?php

namespace Drupal\apigee_edge\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for changing the entity labels.
 */
class ProductSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_entity_label';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.entity_labels',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('apigee_edge.entity_labels');

    $form['label'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('How to refer to an API Product on the UI'),
      '#collapsible' => FALSE,
    ];

    $form['label']['api_product_label_singular'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Singular format'),
      '#default_value' => $config->get('api_product_label_singular'),
    );

    $form['label']['api_product_label_plural'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Plural format'),
      '#default_value' => $config->get('api_product_label_plural'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('apigee_edge.entity_labels');

    $config_names = [
      'api_product_label_singular',
      'api_product_label_plural',
    ];

    foreach ($config_names as $name) {
      $config->set($name, $form_state->getValue($name));
    }

    $config->save();

    \Drupal::entityTypeManager()->clearCachedDefinitions();
    menu_cache_clear_all();

    parent::submitForm($form, $form_state);
  }

}
