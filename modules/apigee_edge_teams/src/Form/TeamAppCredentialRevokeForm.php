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

namespace Drupal\apigee_edge_teams\Form;

use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\apigee_edge\Form\AppCredentialRevokeFormBase;
use Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides revoke confirmation form for team app credential.
 */
class TeamAppCredentialRevokeForm extends AppCredentialRevokeFormBase {

  /**
   * The app credential controller factory.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface
   */
  protected $appCredentialControllerFactory;

  /**
   * TeamAppDeleteCredentialForm constructor.
   *
   * @param \Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface $app_credential_controller_factory
   *   The app credential controller factory.
   */
  public function __construct(TeamAppCredentialControllerFactoryInterface $app_credential_controller_factory) {
    $this->appCredentialControllerFactory = $app_credential_controller_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge_teams.controller.team_app_credential_controller_factory')
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
  public function getCancelUrl() {
    return $this->app->toUrl();
  }

}
