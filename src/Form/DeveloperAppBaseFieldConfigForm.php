<?php

namespace Drupal\apigee_edge\Form;

use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for configuring custom base field settings.
 */
class DeveloperAppBaseFieldConfigForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'developer_app_base_field_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $baseFields = DeveloperApp::baseFieldDefinitions(\Drupal::entityTypeManager()->getDefinition('developer_app'));

    $form['table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Base field settings'),
      '#header' => [
        $this->t('Field name'),
        $this->t('Required'),
      ],
    ];

    foreach ($baseFields as $name => $baseField) {
      if ($baseField->isDisplayConfigurable('form')) {
        $form['table'][$name] = [
          'name' => [
            '#type' => 'item',
            '#markup' => $baseField->getLabel(),
          ],
          'required' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Required'),
            '#title_display' => 'invisible',
            '#default_value' => $baseField->isRequired(),
          ],
        ];
      }
    }

    foreach ($this->config('apigee_edge.appsettings')->get('locked_base_fields') as $locked) {
      $form['table'][$locked]['required']['#disabled'] = TRUE;
    }

    $form['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $required = [];

    foreach ($form_state->getValue('table') as $name => $data) {
      if ($data['required']) {
        $required[] = $name;
      }
    }

    $this->configFactory()
      ->getEditable('apigee_edge.appsettings')
      ->set('required_base_fields', $required)
      ->save();

    drupal_flush_all_caches();
  }

}
