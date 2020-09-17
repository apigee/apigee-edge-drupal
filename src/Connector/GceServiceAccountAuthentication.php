<?php

/**
 * Copyright 2019 Google Inc.
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

namespace Drupal\apigee_edge\Connector;

use Apigee\Edge\Api\Management\Controller\OrganizationController;
use Apigee\Edge\Api\Management\Entity\Organization;
use Apigee\Edge\ClientInterface;
use Apigee\Edge\HttpClient\Plugin\Authentication\AbstractOauth;
use Apigee\Edge\HttpClient\Plugin\Authentication\NullAuthentication;
use Apigee\Edge\HttpClient\Plugin\Authentication\OauthTokenStorageInterface;
use Drupal;
use Drupal\apigee_edge\SDKConnectorInterface;
use Http\Message\Authentication\Bearer;
use Http\Message\Authentication\Header;

/**
 * Decorator for Hybrid authentication plugin.
 */
class GceServiceAccountAuthentication extends AbstractOauth
{
  private const DEFAULT_GCE_AUTH_SERVER = "http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token";
  static private $token;

  public function __construct(OauthTokenStorageInterface $tokenStorage)
  {
    parent::__construct($tokenStorage, DEFAULT_GCE_AUTH_SERVER);
  }

  /**
   * @inheritDoc
   */
  protected function getAccessToken(): void
  {
    $this->tokenStorage->saveToken(static::fetchTokenFromGceEndpoint());
  }

  /**
   * {@inheritdoc}
   */
  protected function authClient(): ClientInterface
  {
    return null;
  }
  private static function fetchTokenFromGceEndpoint(): ClientInterface
  {
    if(empty(static::$token)) {
      /** @var SDKConnectorInterface $sdk_connector */
      $sdk_connector = Drupal::service('apigee_edge.sdk_connector');
      $client = $sdk_connector->buildClient(new Header("Metadata-Flavor", "Google"), DEFAULT_GCE_AUTH_SERVER);
      $response = $client->get("");
      static::$token = json_decode((string)$response->getBody(), true);
    }
    return static::$token;
  }

  public static function getOrganization(): ?Organization
  {
      $token = static::fetchTokenFromGceEndpoint();
      /** @var SDKConnectorInterface $sdk_connector */
      $sdk_connector = Drupal::service('apigee_edge.sdk_connector');
      $client = $sdk_connector->buildClient(new Bearer($token->access_token), ClientInterface::HYBRID_ENDPOINT);
      $org_controller = new OrganizationController($client);
      /** @var Organization[] $orgs */
      $orgs = $org_controller->getEntities();
      if(!empty($orgs)) {
        return reset($orgs);
      }
      return null;
  }
}
