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

namespace Drupal\Tests\apigee_mock_api_client\Traits;

use Apigee\Edge\Api\ApigeeX\Entity\AppGroup;
use Apigee\Edge\Api\Management\Entity\App;
use Apigee\Edge\Api\Management\Entity\Company;
use Apigee\Edge\Api\Management\Entity\Organization;
use Apigee\Edge\Structure\AddonsConfig;
use Apigee\Edge\Structure\MonetizationConfig;
use Apigee\MockClient\Generator\ApigeeSdkEntitySource;
use Drupal\Tests\apigee_edge\Traits\ApigeeEdgeUtilTestTrait;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Entity\DeveloperInterface;
use Drupal\apigee_edge_teams\Entity\Team;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\user\UserInterface;
use Http\Message\RequestMatcher\RequestMatcher;

/**
 * Helper functions working with Apigee tests.
 */
trait ApigeeMockApiClientHelperTrait {

  use ApigeeEdgeUtilTestTrait;

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
    $this->createTestKey();
    $this->restoreKey();
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
   * Add matched org response for Apigee X.
   *
   * @param string $organizationName
   *   The organization name, or empty to use the default from the credentials.
   * @param string $runtimeType
   *   The organization runtime Type.
   */
  protected function addApigeexOrganizationMatchedResponse($organizationName = '', $runtimeType = 'CLOUD') {
    $organizationName = $organizationName ?: $this->sdkConnector->getOrganization();
    $organization = new Organization([
      'name'          => $organizationName,
      'runtimeType'   => $runtimeType,
      'addonsConfig'  => new AddonsConfig([
        'monetizationConfig' => new MonetizationConfig([
          'enabled' => 'true',
        ]),
      ]),
    ]);

    $this->stack->on(
      new RequestMatcher("/v1/organizations/{$organization->id()}$", NULL, [
        'GET',
      ]),
      $this->mockResponseFactory->generateResponse(new ApigeeSdkEntitySource($organization))
    );
  }

  /**
   * Helper function to queue up an Apigee X org response since every test will need it.
   *
   * @param string $runtimetype
   *   Whether or not the org is cloud, hybrid or non-hybrid.
   * @param bool $monetized
   *   Whether or not the org is monetized.
   *
   * @throws \Exception
   */
  protected function warmApigeexOrganizationCache($runtimetype = 'CLOUD', $monetized = TRUE) {
    if (!$this->sdkConnector->getOrganization()) {
      $this->addApigeexOrganizationMatchedResponse();
    }
    $this->stack
      ->queueMockResponse([
        'get_apigeex_organization' => [
          'runtimetype' => $runtimetype,
          'monetization_enabled' => $monetized ? 'true' : 'false',
          'timezone' => $this->org_default_timezone,
        ],
      ]);
    $this->sdkConnector->getOrganization();
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
   * @param array $context
   *   Extra keys to pass to the template.
   */
  protected function queueDeveloperResponse(UserInterface $developer, $response_code = NULL, array $context = []) {
    if (!empty($response_code)) {
      $context['status_code'] = $response_code;
    }

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
   *
   * @return \Drupal\user\Entity\User
   *   A user account with the same data as the created developer.
   */
  protected function queueDeveloperResponseFromDeveloper(DeveloperInterface $developer, $response_code = NULL) {
    $account = $this->entityTypeManager->getStorage('user')->create([
      'mail' => $developer->getEmail(),
      'name' => $developer->getUserName(),
      'first_name' => $developer->getFirstName(),
      'last_name' => $developer->getLastName(),
      'status' => ($developer->getStatus() == DeveloperInterface::STATUS_ACTIVE) ? 1 : 0,
    ]);

    $this->queueDeveloperResponse($account, $response_code);

    return $account;
  }

  /**
   * Queues up a mock company response.
   *
   * @param \Apigee\Edge\Api\Management\Entity\Company $company
   *   The cpmpany to get properties from.
   * @param string|null $response_code
   *   Add a response code to override the default.
   */
  protected function queueCompanyResponse(Company $company, $response_code = NULL) {
    $context = empty($response_code) ? [] : ['status_code' => $response_code];

    $context['company'] = $company;
    $context['org_name'] = $this->sdkConnector->getOrganization();

    $this->stack->queueMockResponse(['company' => $context]);
  }

  /**
   * Queues up a mock appgroup response.
   *
   * @param \Apigee\Edge\Api\ApigeeX\Entity\AppGroup $appgroup
   *   The appgroup to get properties from.
   * @param string|null $developer
   *   Member email.
   * @param string|null $response_code
   *   Add a response code to override the default.
   */
  protected function queueAppGroupResponse(AppGroup $appgroup, $developer = NULL, $response_code = NULL) {
    $context = empty($response_code) ? [] : ['status_code' => $response_code];

    $context['appgroup'] = $appgroup;
    $context['org_name'] = $this->sdkConnector->getOrganization();
    $context['members'] = $developer;
    $this->stack->queueMockResponse(['appgroup' => $context]);
  }

  /**
   * Queues up a mock companies response.
   *
   * @param array $companies
   *   An array of company objects.
   * @param string|null $response_code
   *   Add a response code to override the default.
   */
  protected function queueCompaniesResponse(array $companies, $response_code = NULL) {
    $context = empty($response_code) ? [] : ['status_code' => $response_code];
    $context['companies'] = $companies;

    $this->stack->queueMockResponse(['companies' => $context]);
  }

  /**
   * Queues up a mock appgroups response.
   *
   * @param array $appgroups
   *   An array of appgroup objects.
   * @param string|null $response_code
   *   Add a response code to override the default.
   */
  protected function queueAppGroupsResponse(array $appgroups, $response_code = NULL) {
    $context = empty($response_code) ? [] : ['status_code' => $response_code];
    $context['appgroups'] = $appgroups;

    $this->stack->queueMockResponse(['appgroups' => $context]);
  }

  /**
   * Queues up a mock developers in a company response.
   *
   * @param array $developers
   *   An array of arrays containing developer emails and roles.
   * @param string|null $response_code
   *   Add a response code to override the default.
   */
  protected function queueDevsInCompanyResponse(array $developers, $response_code = NULL) {
    $context = empty($response_code) ? [] : ['status_code' => $response_code];

    $context['developers'] = $developers;

    $this->stack->queueMockResponse(['developers_in_company' => $context]);
  }

  /**
   * Helper to create a DeveloperApp entity.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperAppInterface
   *   A DeveloperApp entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createDeveloperApp(): DeveloperAppInterface {
    static $appId;
    $appId = $appId ? $appId++ : 1;

    $this->queueDeveloperResponse($this->account);
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $entity */
    $entity = DeveloperApp::create([
      'appId' => $this->integration_enabled ? NULL : $appId,
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'displayName' => $this->randomMachineName(),
    ]);
    $entity->setOwner($this->account);
    $this->queueDeveloperAppResponse($entity);
    $entity->save();

    return $entity;
  }

  /**
   * Helper to create a Team entity.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamInterface
   *   A Team entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createTeam(): TeamInterface {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $team */
    $team = Team::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomGenerator->name(),
    ]);
    $this->queueCompanyResponse($team->decorated());
    $this->stack->queueMockResponse('no_content');
    $team->save();

    return $team;
  }

  /**
   * Helper to create a Apigee X Team entity.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamInterface
   *   A Team entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createApigeexTeam(): TeamInterface {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $team */
    $team = Team::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomGenerator->name(),
    ]);

    $this->queueAppGroupResponse($team->decorated());
    $team->save();

    return $team;
  }

  /**
   * Adds a user to a team.
   *
   * Adding a team to a user will add the team as long as the developer entity
   * is loaded from cache.
   *
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team.
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperInterface
   *   The developer entity.
   */
  public function addUserToTeam(TeamInterface $team, UserInterface $user) {
    $this->queueDevsInCompanyResponse([
      ['email' => $user->getEmail()],
    ]);
    $this->queueCompanyResponse($team->decorated());

    $teamMembershipManager = \Drupal::service('apigee_edge_teams.team_membership_manager');
    $teamMembershipManager->addMembers($team->id(), [$user->getEmail()]);

    $this->queueDeveloperResponse($user, 200, [
      'companies' => [$team->id()],
    ]);

    return $this->entityTypeManager->getStorage('developer')->load($user->getEmail());
  }

  /**
   * Adds a user to a X team.
   *
   * Adding a team to a user will add the team as long as the developer entity
   * is loaded from cache.
   *
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team.
   * @param \Drupal\user\UserInterface $user
   *   A drupal user.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperInterface
   *   The developer entity.
   */
  public function addUserToXteam(TeamInterface $team, UserInterface $user) {
    $this->queueDevsInCompanyResponse([
      ['email' => $user->getEmail()],
    ]);
    $this->queueAppGroupResponse($team->decorated(), $user->getEmail());
    $this->queueAppGroupResponse($team->decorated(), $user->getEmail());

    /** @var \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface $team_member_roles */
    $team_member_role_storage = \Drupal::entityTypeManager()->getStorage('team_member_role');
    $this->queueAppGroupResponse($team->decorated(), $user->getEmail());
    $team_member_role_storage->addTeamRoles($user, $team, ['admin']);
    $team_member_roles = $team_member_role_storage->loadByDeveloperAndTeam($user, $team);
    $team_member_roles->save();

    $this->queueDevsInCompanyResponse([
      ['email' => $user->getEmail()],
    ]);
    $teamMembershipManager = \Drupal::service('apigee_edge_teams.team_membership_manager');
    $this->queueAppGroupResponse($team->decorated(), $user->getEmail());
    $this->queueAppGroupResponse($team->decorated(), $user->getEmail());
    $teamMembershipManager->addMembers($team->id(), [$user->getEmail() => ['admin']]);

    $this->queueDeveloperResponse($user, 200, [
      'appgroups' => [$team->id()],
    ]);

    return $this->entityTypeManager->getStorage('developer')->load($user->getEmail());
  }

  /**
   * Helper to add Edge entity response to stack.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $app
   *   The app.
   * @param int $response_code
   *   Response code, defaults to 200.
   * @param array $credentials
   *   An array of app credentials.
   */
  protected function queueDeveloperAppResponse(DeveloperAppInterface $app, $response_code = 200, array $credentials = []) {
    $this->stack->queueMockResponse([
      'get_developer_app' => [
        'status_code' => $response_code,
        'app' => [
          'appId' => $app->getAppId() ?: $this->randomMachineName(),
          'name' => $app->getName(),
          'status' => $app->getStatus(),
          'displayName' => $app->getDisplayName(),
          'developerId' => $app->getDeveloperId(),
        ],
        'credentials' => $credentials,
      ],
    ]);
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
