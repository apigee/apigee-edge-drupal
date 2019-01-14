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

use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\apigee_edge\Entity\Form\AppEditFormTrait;
use Drupal\apigee_edge\Entity\Form\AppForm;
use Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General form handler for the team app edit.
 */
class TeamAppEditForm extends AppForm {

  use AppEditFormTrait;
  use TeamAppFormTrait;

  /**
   * The app credential controller factory.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface
   */
  protected $appCredentialControllerFactory;

  /**
   * Constructs TeamAppEditForm.
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

  /**
   * {@inheritdoc}
   */
  protected function apiProductList(): array {
    // TODO Revisit and define how API product access (by user) should be
    // applied on team apps.
    if ($this->currentUser()->hasPermission('bypass api product access control')) {
      return $this->entityTypeManager->getStorage('api_product')->loadMultiple();
    }

    // For security reasons only return public API products by default.
    return array_filter(\Drupal::entityTypeManager()->getStorage('api_product')->loadMultiple(), function (ApiProductInterface $api_product) {
      // Attribute may not exists but in that case it means public.
      return ($api_product->getAttributeValue('access') ?? 'public') === 'public';
    });
  }

}
