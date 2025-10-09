<?php

namespace Drupal\apigee_edge_teams\Service;

use Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Apigee\Edge\Api\ApigeeX\Controller\AppGroupAppCredentialController;
use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;

/**
 * Handles AppGroup scopes after API products have been added to a credential.
 */
class AppGroupScopeManager {

  /**
   * The SDK connector.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $sdkConnector;

  /**
   * The organization controller.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface
   */
  protected $organizationController;

  /**
   * AppGroupScopeManager constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdkConnector
   *   The SDK connector.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $organizationController
   *   The organization controller.
   */
  public function __construct(SDKConnectorInterface $sdkConnector, OrganizationControllerInterface $organizationController) {
    $this->sdkConnector = $sdkConnector;
    $this->organizationController = $organizationController;
  }

  /**
   * Overrides AppGroup scopes if necessary.
   *
   * @param array $originalScopes
   *   The original scopes.
   * @param \Apigee\Edge\Api\Management\Entity\AppCredentialInterface $credential
   *   The credential.
   * @param string $ownerId
   *   The owner id.
   * @param string $appName
   *   The app name.
   */
  public function overrideScopes(array $originalScopes, AppCredentialInterface $credential, string $ownerId, string $appName): void {
    if (!$this->organizationController->isOrganizationApigeeX()) {
      return;
    }

    $client = $this->sdkConnector->getClient();
    $organization = $this->sdkConnector->getOrganization();
    $controller = new AppGroupAppCredentialController($organization, $ownerId, $appName, $client);
    $controller->overrideAppGroupScopes($credential->getConsumerKey(), $originalScopes);
  }

}
