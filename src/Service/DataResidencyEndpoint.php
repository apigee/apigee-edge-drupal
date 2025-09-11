<?php

/**
 * Copyright 2025 Google Inc.
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

namespace Drupal\apigee_edge\Service;

use Apigee\Edge\Api\Management\Controller\OrganizationController;
use Apigee\Edge\ClientInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\key\KeyInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Discovers the data residency endpoint for a given key.
 */
class DataResidencyEndpoint implements DataResidencyEndpointInterface {
  use StringTranslationTrait;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $sdkConnector;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * Constructs a new DataResidencyEndpoint object.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   The SDK connector service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   String translation.
   */
  public function __construct(SDKConnectorInterface $sdk_connector, StateInterface $state, MessengerInterface $messenger, TranslationInterface $string_translation) {
    $this->sdkConnector = $sdk_connector;
    $this->state = $state;
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint(KeyInterface $key): void {
    /** @var \Drupal\apigee_edge\Plugin\KeyType\ApigeeAuthKeyType $key_type */
    $key_type = $key->getKeyType();

    try {
      $base_endpoint = ClientInterface::APIGEE_ON_GCP_ENDPOINT;

      $client = $this->sdkConnector->buildClient($key_type->getAuthenticationMethod($key), $base_endpoint);
      $orgController = new OrganizationController($client);
      $dataResidencyData = $orgController->getProjectMapping($key_type->getOrganization($key));

      if (isset($dataResidencyData['location']) && $dataResidencyData['location']) {
        $dataResidencyEndpoint = str_replace("https://", "https://{$dataResidencyData['location']}-", $base_endpoint);

        $this->state->set(self::DRZ_ENDPOINT, $dataResidencyEndpoint);
        $this->messenger->addStatus($this->stringTranslation->translate('Data residency is enabled for this organization. Service endpoint being used is @serviceEndpoint', ['@serviceEndpoint' => $dataResidencyEndpoint]));
      }
      else {
        $this->state->delete(self::DRZ_ENDPOINT);
      }
    }
    catch (\Exception $e) {
      // We can ignore this exception, as it means that the data residency
      // endpoint could not be determined.
      $this->state->delete(self::DRZ_ENDPOINT);
    }
  }

}
