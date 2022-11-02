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
use Drupal\apigee_edge\Entity\Form\AppEditForm;
use Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface;
use Drupal\apigee_edge_teams\TeamPermissionHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the team app edit.
 */
class TeamAppEditForm extends AppEditForm {

  use TeamAppFormTrait;

  /**
   * The app credential controller factory.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface
   */
  protected $appCredentialControllerFactory;

  /**
   * The team permission handler.
   *
   * @var \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface
   */
  protected $teamPermissionHandler;

  /**
   * Constructs TeamAppEditForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface $app_credential_controller_factory
   *   The team app credential controller factory.
   * @param \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface $team_permission_handler
   *   The team permission handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, TeamAppCredentialControllerFactoryInterface $app_credential_controller_factory, TeamPermissionHandlerInterface $team_permission_handler = NULL) {
    if (!$team_permission_handler) {
      $team_permission_handler = \Drupal::service('apigee_edge_teams.team_permissions');
    }
    parent::__construct($entity_type_manager, $renderer);
    $this->appCredentialControllerFactory = $app_credential_controller_factory;
    $this->teamPermissionHandler = $team_permission_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('apigee_edge_teams.controller.team_app_credential_controller_factory'),
      $container->get('apigee_edge_teams.team_permissions')
    );
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
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    if (isset($form['credential'])) {
      foreach (Element::children($form['credential']) as $credential) {
        $form['credential'][$credential]['api_products'] += $this->nonMemberApiProductAccessWarningElement($form, $form_state);
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl(): Url {
    $entity = $this->getEntity();
    if ($entity->hasLinkTemplate('collection-by-team')) {
      return $entity->toUrl('collection-by-team');
    }
    return parent::getRedirectUrl();
  }

  /**
   * {@inheritdoc}
   */
  protected function canEditApiProducts(): bool {
    if (($team_name = $this->entity->getAppOwner()) && $team = $this->entityTypeManager->getStorage('team')->load($team_name)) {
      return in_array('team_app_edit_api_products', $this->teamPermissionHandler->getDeveloperPermissionsByTeam($team, $this->currentUser()));
    }

    return parent::canEditApiProducts();
  }

}
