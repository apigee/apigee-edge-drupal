<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\apigee_edge\Form;

use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface;
use Drupal\apigee_edge\Entity\Form\DeveloperAppFormTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides credential generate form for developer app.
 */
class DeveloperAppCredentialGenerateForm extends AppCredentialGenerateFormBase {

  use DeveloperAppFormTrait;

  /**
   * The user from route.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The app credential controller factory.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface
   */
  protected $appCredentialControllerFactory;

  /**
   * DeveloperAppDeleteCredentialForm constructor.
   *
   * @param \Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface $app_credential_controller_factory
   *   The app credential controller factory.
   */
  public function __construct(DeveloperAppCredentialControllerFactoryInterface $app_credential_controller_factory) {
    $this->appCredentialControllerFactory = $app_credential_controller_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge.controller.developer_app_credential_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AppInterface $app = NULL, ?UserInterface $user = NULL) {
    $this->user = $user;
    return parent::buildForm($form, $form_state, $app);
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
  protected function getAppOwner(): string {
    return $this->user->getEmail();
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl(): Url {
    return $this->app->toUrl('canonical-by-developer');
  }

}
