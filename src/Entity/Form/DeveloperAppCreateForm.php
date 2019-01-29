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

use Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the developer app create.
 */
class DeveloperAppCreateForm extends DeveloperAppCreateFormBase {

  /**
   * The developer controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface
   */
  protected $developerController;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs DeveloperAppCreateForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface $api_product_controller
   *   The API product controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface $developer_controller
   *   The developer controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface $app_credential_controller_factory
   *   The developer app credential controller factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ApiProductControllerInterface $api_product_controller, DeveloperControllerInterface $developer_controller, DeveloperAppCredentialControllerFactoryInterface $app_credential_controller_factory, RendererInterface $renderer) {
    parent::__construct($entity_type_manager, $api_product_controller, $app_credential_controller_factory);
    $this->developerController = $developer_controller;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('apigee_edge.controller.api_product'),
      $container->get('apigee_edge.controller.developer'),
      $container->get('apigee_edge.controller.developer_app_credential_factory'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function alterFormBeforeApiProductElement(array &$form, FormStateInterface $form_state): void {

    // Do not reload a developer ids and users when AJAX refreshes the form.
    $developer_options = $form_state->get('developer_options');
    if ($developer_options === NULL) {
      // It is faster to collect existing developer emails like this
      // from Apigee Edge.
      $developer_emails = $this->developerController->getEntityIds();
      $developer_options = array_reduce($this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $developer_emails]), function ($carry, UserInterface $item) {
        $carry[$item->getEmail()] = $item->label();
        return $carry;
      }, []);

      reset($developer_options);

      $form_state->set('developer_options', $developer_options);
    }

    // Override the owner field to be a select list with all developers from
    // Apigee Edge.
    $form['owner'] = [
      '#title' => $this->t('Owner'),
      '#type' => 'select',
      '#weight' => $form['owner']['#weight'],
      '#default_value' => $form_state->get('owner') ?? key($developer_options),
      '#options' => $developer_options,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateApiProductList',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function alterFormWithApiProductElement(array &$form, FormStateInterface $form_state): void {
    $form['api_products']['#prefix'] = '<div id="api-products-ajax-wrapper">';
    $form['api_products']['#suffix'] = '</div>';
  }

  /**
   * Ajax command that refreshes the API product list when owner changes.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function updateApiProductList(array $form, FormStateInterface $form_state) : AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#api-products-ajax-wrapper', $this->renderer->render($form['api_products'])));
    return $response;
  }

}
