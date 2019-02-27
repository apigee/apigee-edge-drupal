<?php

/**
 * Copyright 2018 Google Inc.
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

namespace Drupal\apigee_edge\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for configuring visible API products in Drupal.
 */
class ApiProductAccessControlForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ProductAccessControlForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['apigee_edge.api_product_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_api_product_access_control_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    $role_names = [];
    $roles_with_bypass_perm = [];

    $form['access'] = [
      '#type' => 'details',
      '#title' => $this->t('Access by visibility'),
      '#description' => $this->t('Limit access to API products by "Access" settings on Apigee Edge.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    /** @var \Drupal\user\RoleInterface $role */
    foreach ($role_storage->loadMultiple() as $role_name => $role) {
      // Retrieve role names for columns.
      $role_names[$role_name] = $role->label();
      $roles_with_bypass_perm[$role_name] = in_array('bypass api product access control', $role->getPermissions()) || $role->isAdmin();
    }

    // Store $role_names for use when saving the data.
    $form['access']['role_names'] = [
      '#type' => 'value',
      '#value' => $role_names,
    ];

    // Store $rolesWithBypassPerm for use when saving the data.
    $form['access']['roles_with_bypass'] = [
      '#type' => 'value',
      '#value' => $roles_with_bypass_perm,
    ];

    $form['access']['visibility'] = [
      '#type' => 'table',
      '#header' => [t('Visibility')],
      '#id' => 'visibility',
      '#attributes' => ['class' => ['visibility', 'js-visibility']],
      '#sticky' => TRUE,
    ];

    foreach ($role_names as $name) {
      $form['access']['visibility']['#header'][] = [
        'data' => $name,
        'class' => ['checkbox'],
      ];
    }

    $visibilities = [
      'public' => $this->t('Public'),
      'private' => $this->t('Private'),
      'internal' => $this->t('Internal'),
    ];

    // Pass this information to the form submit handler.
    $form['access']['visibility']['options'] = [
      '#type' => 'value',
      '#value' => $visibilities,
    ];

    foreach ($visibilities as $visibility => $label) {
      $selected_roles = $this->config('apigee_edge.api_product_settings')->get('access')[$visibility] ?? [];
      $form['access']['visibility'][$visibility]['name'] = [
        '#markup' => $label,
      ];

      foreach ($role_names as $rid => $name) {
        $form['access']['visibility'][$visibility][$rid] = [
          '#title' => $label,
          '#title_display' => 'invisible',
          '#wrapper_attributes' => [
            'class' => ['checkbox'],
          ],
          '#type' => 'checkbox',
          '#default_value' => in_array($rid, $selected_roles) ? 1 : 0,
          '#attributes' => ['class' => ['rid-' . $rid, 'js-rid-' . $rid]],
          '#parents' => ['access', 'visibility', $rid, $visibility],
        ];
        // Show a column of disabled but checked checkboxes.
        if ($roles_with_bypass_perm[$rid]) {
          $form['access']['visibility'][$visibility][$rid]['#disabled'] = TRUE;
          $form['access']['visibility'][$visibility][$rid]['#default_value'] = TRUE;
          $form['access']['visibility'][$visibility][$rid]['#attributes']['title'] = $this->t('This checkbox is disabled because this role has "Bypass API product access control" permission.');
        }
      }
    }

    $form['#attached']['library'][] = 'apigee_edge/apiproduct_access_admin';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $rid_product_map = [];
    foreach ($form_state->getValue(['access', 'role_names'], []) as $rid => $name) {
      // Do not store roles with by pass permission in the attribute
      // unnecessarily.
      if (!$form_state->getValue(['access', 'roles_with_bypass', $rid], FALSE)) {
        $rid_product_map[$rid] = array_filter($form_state->getValue([
          'access',
          'visibility',
          $rid,
        ], []));
      }
    }

    // Ensure that we always keep these 3 keys in config object.
    $visibility_rid_map = array_fill_keys(array_keys($form_state->getValue([
      'access',
      'visibility',
      'options',
    ])), []);
    foreach ($rid_product_map as $rid => $products) {
      foreach (array_keys($products) as $product) {
        $visibility_rid_map[$product][$rid] = $rid;
      }
    }

    $this->config('apigee_edge.api_product_settings')
      ->set('access', $visibility_rid_map)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
