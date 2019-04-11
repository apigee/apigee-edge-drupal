<?php

/**
 * Copyright 2019 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge_apidocs\Form;

use Drupal\apigee_edge_apidocs\Entity\ApiDoc;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ApiDocSettingsForm.
 *
 * Settings for the ApiDoc entity type.
 */
class ApiDocSettingsForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apidoc_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type = $this->entityTypeManager
      ->getStorage('apidoc')
      ->getEntityType();
    $config = $this->configFactory()
      ->getEditable('apigee_edge_apidocs.settings');

    $options = $form_state->getValue('options');
    $config->set('default_revision', (bool) $options['new_revision'])->save();

    $this->messenger()->addStatus($this->t('@type settings have been updated.', [
      '@type' => $entity_type->getLabel(),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var \Drupal\apigee_edge_apidocs\Entity\ApiDoc $entity */
    $entity = $this->entityTypeManager->getStorage('apidoc')->create();
    $form['apidoc_settings']['#markup'] = $this->t('Settings for API Docs. Manage field settings using the tabs above.');

    $form['additional_settings'] = [
      '#type' => 'vertical_tabs',
    ];

    $form['workflow'] = [
      '#type' => 'details',
      '#title' => $this->t('Publishing options'),
      '#group' => 'additional_settings',
    ];
    $workflow_options = [
      'new_revision' => $entity->shouldCreateNewRevision(),
    ];
    // Prepare workflow options to be used for 'checkboxes' form element.
    $workflow_options_keys = array_keys(array_filter($workflow_options));
    $workflow_options = array_combine($workflow_options_keys, $workflow_options_keys);
    $form['workflow']['options'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Default options'),
      '#default_value' => $workflow_options,
      '#options' => [
        'new_revision' => $this->t('Create new revision'),
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

}
