<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Form;

use Drupal\apigee_edge\Entity\FieldableEdgeEntityUtilityTrait;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;

/**
 * Provides a form for changing the developer attribute related settings.
 */
class DeveloperAttributesSettingsForm extends ConfigFormBase {

  use FieldableEdgeEntityUtilityTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_developer_attributes_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.sync',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('apigee_edge.sync');
    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.admin';

    $form['developer_attributes'] = [
      '#type' => 'details',
      '#title' => $this->t('Developer attributes'),
      '#open' => TRUE,
    ];

    $form['developer_attributes']['instructions'] = [
      '#markup' => $this->t('Select the <a href=":url_manage">user fields</a> that have to be synced to the Apigee Edge server.<br>You can also add a <a href=":url_new">new field</a> to users.', [
        ':url_manage' => Url::fromRoute('entity.user.field_ui_fields')->toString(),
        ':url_new' => Url::fromRoute('field_ui.field_storage_config_add_user')->toString(),
      ]),
    ];

    $fields = array_filter(\Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user'), function ($field_definition) {
      return $field_definition instanceof FieldConfigInterface;
    });
    uasort($fields, [FieldConfig::class, 'sort']);

    $options = $default_values = [];
    /** @var \Drupal\apigee_edge\FieldStorageFormatManager $format_manager */
    $format_manager = \Drupal::service('plugin.manager.apigee_field_storage_format');
    /** @var \Drupal\field\FieldConfigInterface $field */
    foreach ($fields as $field) {
      $options[$field->getName()] = [
        'field_label' => $field->getLabel(),
        'field_name' => $field->getName(),
        'field_type' => $field->getType(),
        'attribute_name' => static::getAttributeName($field->getName()),
      ];
      $formatter = $format_manager->lookupPluginForFieldType($field->getType());
      if (isset($formatter)) {
        $rc = new \ReflectionClass($format_manager->lookupPluginForFieldType($field->getType()));
        $short_name = $rc->getShortName();
        $options[$field->getName()]['field_storage_formatter'] = $short_name;
      }
      else {
        $options[$field->getName()]['field_storage_formatter'] = $this->t('- None -');
      }

      if (in_array($field->getName(), $config->get('user_fields_to_sync'))) {
        $default_values[$field->getName()] = TRUE;
      }
    }

    $form['developer_attributes']['attributes'] = [
      '#type' => 'tableselect',
      '#header' => [
        'field_label' => $this->t('User field label'),
        'field_name' => $this->t('User field name'),
        'field_type' => $this->t('User field type'),
        'field_storage_formatter' => $this->t('Storage formatter'),
        'attribute_name' => $this->t('Developer attribute name'),
      ],
      '#options' => $options,
      '#default_value' => $default_values,
      '#empty' => $this->t('No user fields found.'),
      '#attributes' => [
        'class' => [
          'table--developer-attributes',
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('apigee_edge.sync')
      ->set('user_fields_to_sync', array_values(array_filter($form_state->getValue('attributes'))))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
