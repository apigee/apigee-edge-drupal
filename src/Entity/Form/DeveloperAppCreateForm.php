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

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the developer app create.
 */
class DeveloperAppCreateForm extends AppForm {

  use AppCreateFormTrait {
    apiProductList as private privateApiProductList;
  }
  use DeveloperAppFormTrait;

  /**
   * The app credential controller factory.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface
   */
  protected $appCredentialControllerFactory;

  /**
   * Constructs DeveloperAppCreateForm.
   *
   * @param \Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface $app_credential_controller_factory
   *   The developer app credential controller factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(DeveloperAppCredentialControllerFactoryInterface $app_credential_controller_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type_manager);
    $this->appCredentialControllerFactory = $app_credential_controller_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge.controller.developer_app_credential_factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $app */
    $app = $this->entity;

    $developer_options = [];
    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    foreach ($this->entityTypeManager->getStorage('developer')->loadMultiple() as $developer) {
      $developer_options[$developer->uuid()] = $developer->label();
    }

    // Override the owner field to be a select list with all developers from
    // Apigee Edge.
    $form['owner'] = [
      '#title' => $this->t('Owner'),
      '#type' => 'select',
      '#weight' => $form['owner']['#weight'],
      '#default_value' => $app->getDeveloperId(),
      '#options' => $developer_options,
      '#required' => TRUE,
    ];

    // If "Let user select the product(s)" is enabled.
    // Add this feature later if it gets requested, this is a "secret" admin
    // form at this moment.
    if ($form['api_products']['#access']) {
      $form['warning_message'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [$this->t('The list of @api_products above is not limited to the selected owner by the API product access control settings here. <strong>All @api_products are visible here from Apigee Edge.</strong>', [
            '@api_products' => $this->entityTypeManager->getDefinition('api_product')->getPluralLabel(),
          ]),
          ],
        ],
        '#weight' => $form['api_products']['#weight'] + 0.0001,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function appCredentialController(string $owner, string $app_name): AppCredentialControllerInterface {
    return $this->appCredentialControllerFactory->developerAppCredentialController($owner, $app_name);
  }

  /**
   * {@inheritdoc}
   */
  protected function apiProductList(): array {
    if ($this->currentUser()->hasPermission('bypass api product access control')) {
      return \Drupal::entityTypeManager()->getStorage('api_product')->loadMultiple();
    }

    return $this->privateApiProductList();
  }

}
