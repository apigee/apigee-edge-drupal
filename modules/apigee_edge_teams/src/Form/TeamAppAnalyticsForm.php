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

namespace Drupal\apigee_edge_teams\Form;

use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface;
use Drupal\apigee_edge\Form\AppAnalyticsFormBase;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the analytics page of a team app on the UI.
 */
class TeamAppAnalyticsForm extends AppAnalyticsFormBase {

  /**
   * The organization controller.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface
   */
  protected $organizationController;

  /**
   * Constructs a new TeamAppAnalyticsForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   The SDK connector service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstore_private
   *   The private temp store factory.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $organization_controller
   *   The organization controller.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, SDKConnectorInterface $sdk_connector, PrivateTempStoreFactory $tempstore_private, UrlGeneratorInterface $url_generator, OrganizationControllerInterface $organization_controller) {
    parent::__construct($entity_type_manager, $sdk_connector, $tempstore_private, $url_generator);
    $this->organizationController = $organization_controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('apigee_edge.sdk_connector'),
      $container->get('tempstore.private'),
      $container->get('url_generator'),
      $container->get('apigee_edge.controller.organization')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_teams_team_app_analytics';
  }

  /**
   * {@inheritdoc}
   */
  protected function getAnalyticsFilterCriteriaByAppOwner(AppInterface $app): string {
    return "developer eq '{$this->connector->getOrganization()}@@@{$app->getAppOwner()}'";
  }

  /**
   * {@inheritdoc}
   */
  protected function getAnalyticsDimensions(): array {
    if ($this->organizationController->isOrganizationApigeeX()) {
      return ['app_group_app'];
    }
    return parent::getAnalyticsDimensions();
  }

  /**
   * {@inheritdoc}
   */
  protected function getAnalyticsFilter(AppInterface $app): string {
    if ($this->organizationController->isOrganizationApigeeX()) {
      return "(app_group_name eq '{$app->getAppOwner()}' and app_group_app eq '{$app->getName()}')";
    }
    return parent::getAnalyticsFilter($app);
  }

}
