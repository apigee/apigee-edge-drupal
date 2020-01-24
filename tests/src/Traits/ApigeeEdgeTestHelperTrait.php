<?php

/*
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

namespace Drupal\Tests\apigee_edge\Traits;

use Apigee\Edge\Api\Management\Entity\Organization;
use Apigee\MockClient\Generator\ApigeeSdkEntitySource;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperInterface;
use Drupal\apigee_mock_api_client\Plugin\KeyProvider\TestEnvironmentVariablesKeyProvider;
use Drupal\key\Entity\Key;
use Drupal\user\UserInterface;
use Http\Message\RequestMatcher\RequestMatcher;

/**
 * Helper functions working with Apigee tests.
 */
trait ApigeeEdgeTestHelperTrait {

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $sdkConnector;

  /**
   * The mock handler stack is responsible for serving queued api responses.
   *
   * @var \Drupal\apigee_mock_api_client\MockHandlerStack
   */
  protected $stack;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mock response factory service.
   *
   * @var \Apigee\MockClient\ResponseFactoryInterface
   */
  protected $mockResponseFactory;

  /**
   * If integration (real API connection) is enabled.
   *
   * @var bool
   */
  protected $integration_enabled;

  /**
   * The Apigee Edge key used in tests.
   *
   * @var string
   */
  protected $apigee_edge_test_key = 'apigee_edge_test_auth';

  /**
   * Setup.
   */
  protected function apigeeTestHelperSetup() {
    $this->apigeeTestPropertiesSetup();
    $this->initAuth();
  }

  /**
   * Setup.
   */
  protected function apigeeTestPropertiesSetup() {
    $this->stack = $this->container->get('apigee_mock_api_client.mock_http_handler_stack');
    $this->sdkConnector = $this->container->get('apigee_edge.sdk_connector');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->mockResponseFactory = $this->container->get('apigee_mock_api_client.response_factory');
    $this->integration_enabled = getenv('APIGEE_INTEGRATION_ENABLE');
  }

  /**
   * Initialize SDK connector.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function initAuth() {

    // Create new Apigee Edge basic auth key.
    $key = Key::create([
      'id'           => $this->apigee_edge_test_key,
      'label'        => 'Apigee M10n Test Authorization',
      'key_type'     => 'apigee_auth',
      'key_provider' => 'apigee_edge_environment_variables',
      'key_input'    => 'apigee_auth_input',
    ]);

    $key->save();

    // Collect credentials from environment variables.
    $fields = [];
    foreach (array_keys($key->getKeyType()->getPluginDefinition()['multivalue']['fields']) as $field) {
      $id = 'APIGEE_EDGE_' . strtoupper($field);
      if ($value = getenv($id)) {
        $fields[$id] = $value;
      }
    }
    // Make sure the credentials persists for functional tests.
    \Drupal::state()->set(TestEnvironmentVariablesKeyProvider::KEY_VALUE_STATE_ID, $fields);

    $this->config('apigee_edge.auth')
      ->set('active_key', $this->apigee_edge_test_key)
      ->save();
  }

  /**
   * Add matched org response.
   *
   * @param string $organizationName
   *   The organization name, or empty to use the default from the credentials.
   */
  protected function addOrganizationMatchedResponse($organizationName = '') {
    $organizationName = $organizationName ?: $this->sdkConnector->getOrganization();

    $organization = new Organization(['name' => $organizationName]);
    $this->stack->on(
      new RequestMatcher("/v1/organizations/{$organization->id()}$", NULL, [
        'GET',
      ]),
      $this->mockResponseFactory->generateResponse(new ApigeeSdkEntitySource($organization))
    );
  }

  /**
   * Add matched developer response.
   *
   * @param \Drupal\user\UserInterface $developer
   *   The developer user to get properties from.
   */
  protected function addDeveloperMatchedResponse(UserInterface $developer) {
    $organization = $this->sdkConnector->getOrganization();
    $dev = new Developer([
      'email' => $developer->getEmail(),
      'developerId' => $developer->uuid(),
      'firstName' => $developer->first_name->value,
      'lastName' => $developer->last_name->value,
      'userName' => $developer->getAccountName(),
      'organizationName' => $organization,
    ]);

    $this->stack->on(
      new RequestMatcher("/v1/organizations/{$organization}/developers/{$developer->getEmail()}$", NULL, [
        'GET',
      ]),
      $this->mockResponseFactory->generateResponse(new ApigeeSdkEntitySource($developer))
    );
  }

  /**
   * Queues up a mock developer response.
   *
   * @param \Drupal\user\UserInterface $developer
   *   The developer user to get properties from.
   * @param string|null $response_code
   *   Add a response code to override the default.
   */
  protected function queueDeveloperResponse(UserInterface $developer, $response_code = NULL) {
    $context = empty($response_code) ? [] : ['status_code' => $response_code];

    $context['developer'] = $developer;
    $context['org_name'] = $this->sdkConnector->getOrganization();

    $this->stack->queueMockResponse(['get_developer' => $context]);
  }

  /**
   * Queues up a mock developer response.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperInterface $developer
   *   The developer user to get properties from.
   * @param string|null $response_code
   *   Add a response code to override the default.
   */
  protected function queueDeveloperResponseFromDeveloper(DeveloperInterface $developer, $response_code = NULL) {
    $account = $this->entityTypeManager->getStorage('user')->create([
      'mail' => $developer->getEmail(),
      'name' => $developer->getUserName(),
      'first_name' => $developer->getFirstName(),
      'last_name' => $developer->getLastName(),
    ]);

    $this->queueDeveloperResponse($account, $response_code);
  }

  /**
   * Installs a given list of modules and rebuilds the cache.
   *
   * @param string[] $module_list
   *   An array of module names.
   *
   * @see \Drupal\Tests\toolbar\Functional\ToolbarCacheContextsTest::installExtraModules()
   */
  protected function installExtraModules(array $module_list) {
    \Drupal::service('module_installer')->install($module_list);
    // Installing modules updates the container and needs a router rebuild.
    $this->container = \Drupal::getContainer();
    $this->container->get('router.builder')->rebuildIfNeeded();
  }

}
