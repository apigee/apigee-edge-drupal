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
use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\apigee_edge\Entity\Form\AppCreateForm;
use Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface;
use Drupal\apigee_edge_teams\TeamMemberApiProductAccessHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for team app create forms.
 *
 * @internal
 */
abstract class TeamAppCreateFormBase extends AppCreateForm {

  use TeamAppFormTrait;

  /**
   * The app credential controller factory.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface
   */
  protected $appCredentialControllerFactory;

  /**
   * Constructs TeamAppCreateFormBase.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface $api_product_controller
   *   The API Product controller service.
   * @param \Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface $app_credential_controller_factory
   *   The team app credential controller factory.
   * @param \Drupal\apigee_edge_teams\TeamMemberApiProductAccessHandlerInterface $team_member_api_product_access_handler
   *   The team API product access handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ApiProductControllerInterface $api_product_controller, TeamAppCredentialControllerFactoryInterface $app_credential_controller_factory, TeamMemberApiProductAccessHandlerInterface $team_member_api_product_access_handler) {
    parent::__construct($entity_type_manager, $api_product_controller);
    $this->appCredentialControllerFactory = $app_credential_controller_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('apigee_edge.controller.api_product'),
      $container->get('apigee_edge_teams.controller.team_app_credential_controller_factory'),
      $container->get('apigee_edge_teams.team_member_api_product_access_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function alterFormWithApiProductElement(array &$form, FormStateInterface $form_state): void {
    parent::alterFormWithApiProductElement($form, $form_state);
    $form['api_products'] += $this->nonMemberApiProductAccessWarningElement($form, $form_state);
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
  protected function getRedirectUrl(): Url {
    $entity = $this->getEntity();
    if ($entity->hasLinkTemplate('collection-by-team')) {
      return $entity->toUrl('collection-by-team');
    }
    return parent::getRedirectUrl();
  }

}
