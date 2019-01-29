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
use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base entity form for developer app create forms.
 */
abstract class DeveloperAppCreateFormBase extends AppCreateForm {

  use DeveloperAppFormTrait;

  /**
   * The app credential controller factory.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface
   */
  protected $appCredentialControllerFactory;

  /**
   * DeveloperAppCreateFormBase constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface $api_product_controller
   *   The API product controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface $app_credential_controller_factory
   *   The developer app credential controller factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ApiProductControllerInterface $api_product_controller, DeveloperAppCredentialControllerFactoryInterface $app_credential_controller_factory) {
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
      $container->get('apigee_edge.controller.developer_app_credential_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function appCredentialController(string $owner, string $app_name): AppCredentialControllerInterface {
    return $this->appCredentialControllerFactory->developerAppCredentialController($owner, $app_name);
  }

}
