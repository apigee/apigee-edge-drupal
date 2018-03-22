<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\apigee_edge\Entity\DeveloperStatusCheckTrait;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dedicated form handler that allows a developer to create an developer app.
 */
class DeveloperAppCreateFormForDeveloper extends DeveloperAppCreateForm {

  use DeveloperStatusCheckTrait;

  /**
   * The developer app entity.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $entity;

  /**
   * Id of the Drupal user who owns the entity.
   *
   * @var null|int
   */
  protected $userId;

  /**
   * DeveloperCreateDeveloperAppForm constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdkConnector
   *   SDK connector service.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   *   Entity manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(SDKConnectorInterface $sdkConnector, ConfigFactory $configFactory, EntityManagerInterface $entityManager, EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($sdkConnector, $configFactory, $entityTypeManager);
    $this->sdkConnector = $sdkConnector;
    $this->configFactory = $configFactory;
    $this->entityManager = $entityManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
    $this->entity = $this->entityTypeManager->getStorage('developer_app')->create();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge.sdk_connector'),
      $container->get('config.factory'),
      $container->get('entity.manager'),
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param int|null $user
   *   User id, up-casting is not working, because _entity_form is used instead
   *   of _form in routing.yml.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $user = NULL) {
    $this->userId = $user;
    $this->checkDeveloperStatus($user);

    $form = parent::buildForm($form, $form_state);
    $form['developerId'] = [
      '#type' => 'value',
      '#value' => $this->entity->getDeveloperId(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    $this->entity->setOwnerId($this->userId);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    $entity = $this->getEntity();
    return $entity->toUrl('collection-by-developer');
  }

}
