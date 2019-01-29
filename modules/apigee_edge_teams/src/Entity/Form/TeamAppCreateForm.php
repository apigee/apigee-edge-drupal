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

namespace Drupal\apigee_edge_teams\Entity\Form;

use Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface;
use Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\apigee_edge_teams\TeamApiProductAccessManagerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the team app create.
 */
class TeamAppCreateForm extends TeamAppCreateFormBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;

  /**
   * TeamAppCreateForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface $api_product_controller
   *   The API Product controller service.
   * @param \Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface $app_credential_controller_factory
   *   The team app credential controller factory.
   * @param \Drupal\apigee_edge_teams\TeamApiProductAccessManagerInterface $team_api_product_access
   *   The Team API product access manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ApiProductControllerInterface $api_product_controller, TeamAppCredentialControllerFactoryInterface $app_credential_controller_factory, TeamApiProductAccessManagerInterface $team_api_product_access, RendererInterface $renderer) {
    parent::__construct($entity_type_manager, $api_product_controller, $app_credential_controller_factory, $team_api_product_access);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('apigee_edge.controller.api_product'),
      $container->get('apigee_edge_teams.controller.team_app_credential_controller_factory'),
      $container->get('apigee_edge_teams.team_api_product_access_manager'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function alterFormBeforeApiProductElement(array &$form, FormStateInterface $form_state): void {
    // Do not recalculate team options when AJAX refreshes the form.
    $team_options = $form_state->get('team_options');
    if ($team_options === NULL) {
      $team_options = array_map(function (TeamInterface $team) {
        return $team->label();
      }, $this->entityTypeManager->getStorage('team')->loadMultiple());
      reset($team_options);
      $form_state->set('team_options', $team_options);
    }

    // Override the owner field to be a select list with all teams from
    // Apigee Edge.
    $form['owner'] = [
      '#title' => $this->t('Owner'),
      '#type' => 'select',
      '#weight' => $form['owner']['#weight'],
      '#default_value' => $form_state->get('owner') ?? key($team_options),
      '#options' => $team_options,
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
