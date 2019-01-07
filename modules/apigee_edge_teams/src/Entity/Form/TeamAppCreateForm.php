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

use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\apigee_edge\Entity\Form\AppCreateFormTrait;
use Drupal\apigee_edge\Entity\Form\AppForm;
use Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the team app create.
 */
class TeamAppCreateForm extends AppForm {

  use AppCreateFormTrait {
    apiProductList as private privateApiProductList;
  }
  use TeamAppFormTrait;

  /**
   * The app credential controller factory.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface
   */
  protected $appCredentialControllerFactory;

  /**
   * Constructs TeamAppCreateForm.
   *
   * @param \Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface $app_credential_controller_factory
   *   The team app credential controller factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(TeamAppCredentialControllerFactoryInterface $app_credential_controller_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type_manager);
    $this->appCredentialControllerFactory = $app_credential_controller_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge_teams.controller.team_app_credential_controller_factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\apigee_edge_teams\Entity\TeamAppInterface $app */
    $app = $this->entity;

    $team_options = array_map(function (TeamInterface $team) {
      return $team->label();
    }, $this->entityTypeManager->getStorage('team')->loadMultiple());

    // Override the owner field to be a select list with all developers from
    // Apigee Edge.
    $form['owner'] = [
      '#title' => $this->t('Owner'),
      '#type' => 'select',
      '#weight' => $form['owner']['#weight'],
      '#default_value' => $app->getCompanyName(),
      '#options' => $team_options,
      '#required' => TRUE,
    ];

    // We do not know yet how existing API product access is going to be
    // applied on team (company) apps so we do not display a warning here.
    // @see \Drupal\apigee_edge\Entity\Form\DeveloperAppCreateForm::form()

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function appCredentialController(string $owner, string $app_name): AppCredentialControllerInterface {
    return $this->appCredentialControllerFactory->teamAppCredentialController($owner, $app_name);
  }

  /**
   * {@inheritdoc}
   */
  protected function apiProductList(): array {
    // TODO Revisit and define how API product access (by user) should be
    // applied on team apps.
    if ($this->currentUser()->hasPermission('bypass api product access control')) {
      return $this->entityTypeManager->getStorage('api_product')->loadMultiple();
    }

    return $this->privateApiProductList();
  }

}
