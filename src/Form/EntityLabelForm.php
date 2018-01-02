<?php

namespace Drupal\apigee_edge\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for changing the entity labels.
 */
class EntityLabelForm extends ConfigFormBase {

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

    $form['developer_app_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label of Developer App'),
      '#default_value' => $config->get('developer_app_label'),
      '#required' => TRUE,
    );

    $form['api_product_label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label of API Product'),
      '#default_value' => $config->get('api_product_label'),
      '#required' => TRUE,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('apigee_edge.entity_labels')
      ->set('developer_app_label', $form_state->getValue('developer_app_label'))
      ->set('api_product_label', $form_state->getValue('api_product_label'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
