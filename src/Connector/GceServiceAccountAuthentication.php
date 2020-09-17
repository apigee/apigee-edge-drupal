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

use Apigee\Edge\ClientInterface;
use Apigee\Edge\Exception\HybridOauth2AuthenticationException;
use Apigee\Edge\HttpClient\Plugin\Authentication\AbstractOauth;
use Apigee\Edge\HttpClient\Plugin\Authentication\OauthTokenStorageInterface;
use GuzzleHttp\Exception\ConnectException;
use Http\Message\Authentication\Header;

/**
 * Decorator for Hybrid authentication plugin.
 */
class GceServiceAccountAuthentication extends AbstractOauth {

  public const DEFAULT_GCE_AUTH_SERVER = "http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token";

  /**
   * GceServiceAccountAuthentication constructor.
   *
   * @param \Apigee\Edge\HttpClient\Plugin\Authentication\OauthTokenStorageInterface $tokenStorage
   *   Storage where access token gets saved.
   */
  public function __construct(OauthTokenStorageInterface $tokenStorage) {
    parent::__construct($tokenStorage, static::DEFAULT_GCE_AUTH_SERVER);
  }

  /**
   * Get access token from the GCE Service account.
   */
  protected function getAccessToken(): void {
    try {
      $response = $this->authClient()->get("");
      $decoded_token = json_decode((string) $response->getBody(), TRUE);
      $this->tokenStorage->saveToken($decoded_token);
    }
    catch (Exception $e) {
      throw new HybridOauth2AuthenticationException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function authClient(): ClientInterface {
    /** @var \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector */
    $sdk_connector = \Drupal::service('apigee_edge.sdk_connector');
    return $sdk_connector->buildClient(new Header("Metadata-Flavor", "Google"), static::DEFAULT_GCE_AUTH_SERVER);
  }

  /**
   * Validate if the GCE Auth Server URL is reachable.
   *
   * This is only available on GCP.
   *
   * @return bool
   *   If GCE Service Account authentication is available.
   */
  public static function isAvailable(): bool {
    try {
      $client = \Drupal::httpClient();
      $client->get(static::DEFAULT_GCE_AUTH_SERVER, ['headers' => ["Metadata-Flavor" => "Google"]]);
      return TRUE;
    }
    catch (ConnectException $e) {
      return FALSE;
    }
  }

}
