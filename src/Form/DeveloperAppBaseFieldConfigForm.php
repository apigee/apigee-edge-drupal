<?php

namespace Drupal\apigee_edge\Form;

use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
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
    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.admin';
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

    foreach ($this->config('apigee_edge.developer_app_settings')->get('locked_base_fields') as $locked) {
      $form['table'][$locked]['required']['#disabled'] = TRUE;
    }

    $developer_app_settings = $this->config('apigee_edge.developer_app_settings');

    $form['callback_url'] = [
      '#type' => 'details',
      '#title' => t('Callback URL settings'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['callback_url']['pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern'),
      '#default_value' => $developer_app_settings->get('callback_url_pattern'),
      '#description' => $this->t('Regular expression that a Callback URL should match. Default is "^https?:\/\/.*$" that ensures callback url starts with either <em>http://</em> or <em>https://</em>.'),
      '#required' => TRUE,
    ];
    $form['callback_url']['pattern_error_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Validation error message'),
      '#default_value' => $developer_app_settings->get('callback_url_pattern_error_message'),
      '#description' => $this->t('Client-side validation error message if a callback URL does not match.'),
      '#required' => TRUE,
    ];
    $form['callback_url']['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#default_value' => $developer_app_settings->get('callback_url_description'),
      '#description' => $this->t('Description of a Callback URL field.'),
    ];
    $form['callback_url']['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $developer_app_settings->get('callback_url_placeholder'),
      '#description' => $this->t('Placeholder for a Callback URL field.'),
    ];

    $form['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $display = EntityFormDisplay::load('developer_app.developer_app.default');
    if ($display) {
      foreach ($form_state->getValue('table') as $name => $data) {
        $component = $display->getComponent($name);
        if ($data['required'] && !($component && $component['region'] !== 'hidden')) {
          $form_state->setError($form['table'][$name]['required'], $this->t('%field-name is hidden on the default form display.', [
            '%field-name' => $form['table'][$name]['name']['#markup'],
          ]));
        }
      }
    }

    if (strpos($form_state->getValue(['callback_url', 'pattern'], ''), '^http') === FALSE) {
      $form_state->setError($form['callback_url']['pattern'], $this->t('The pattern should start with <em>^http</em> to limit the acceptable protocols.'));
    }
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
      ->getEditable('apigee_edge.developer_app_settings')
      ->set('required_base_fields', $required)
      ->set('callback_url_pattern', $form_state->getValue(['callback_url', 'pattern']))
      ->set('callback_url_pattern_error_message', $form_state->getValue(['callback_url', 'pattern_error_message']))
      ->set('callback_url_description', $form_state->getValue(['callback_url', 'description']))
      ->set('callback_url_placeholder', $form_state->getValue(['callback_url', 'placeholder']))
      ->save();

    drupal_flush_all_caches();

    $this->messenger()->addStatus($this->t('Field settings have been saved successfully.'));
  }

}
