<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\Tests\apigee_edge\Kernel\Entity;

use Apigee\Edge\Api\Management\Entity\App;
use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Drupal\Tests\apigee_edge\Kernel\ApigeeEdgeKernelTestBase;
use Drupal\Tests\apigee_edge\Kernel\ApigeeEdgeKernelTestTrait;
use Drupal\Tests\apigee_edge\Traits\CredsUtilsTrait;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the AppWarningsChecker.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class AppWarningsCheckerTest extends ApigeeEdgeKernelTestBase {

  use ApigeeMockApiClientHelperTrait, ApigeeEdgeKernelTestTrait, UserCreationTrait, CredsUtilsTrait;

  /**
   * Indicates this test class is mock API client ready.
   *
   * @var bool
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * The entity type to test.
   */
  const ENTITY_TYPE = 'developer_app';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'apigee_edge',
    'apigee_mock_api_client',
    'key',
    'user',
    'options',
  ];

  /**
   * The user account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $account;

  /**
   * The owner of the developer app.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * An approved DeveloperApp entity with all credentials approved.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $approvedAppWithApprovedCredential;

  /**
   * An approved DeveloperApp entity with at least one credential revoked.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $approvedAppWithOneRevokedCredential;

  /**
   * An approved DeveloperApp entity with all credentials revoked.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $approvedAppWithAllRevokedCredential;

  /**
   * A revoked DeveloperApp entity with at least one credential revoked.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $revokedAppWithRevokedCredential;

  /**
   * An approved DeveloperApp entity with an expired credential.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $approvedAppWithExpiredCredential;

  /**
   * A revoked DeveloperApp entity with an expired credential.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $revokedAppWithExpiredCredential;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['apigee_edge']);

    $this->apigeeTestHelperSetup();

    $this->addOrganizationMatchedResponse();

    $this->account = User::create([
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => $this->randomMachineName(),
      'first_name' => $this->getRandomGenerator()->word(16),
      'last_name' => $this->getRandomGenerator()->word(16),
    ]);
    $this->account->save();

    $this->queueDeveloperResponse($this->account);
    $this->developer = Developer::load($this->account->getEmail());

    // Approved App.
    $this->approvedAppWithApprovedCredential = DeveloperApp::create([
      'name' => 'Approved App with approved credential',
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->approvedAppWithApprovedCredential->setOwner($this->account);
    $this->queueDeveloperAppResponse($this->approvedAppWithApprovedCredential);
    $this->approvedAppWithApprovedCredential->save();

    // Approved app with one revoked credential.
    $this->approvedAppWithOneRevokedCredential = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->approvedAppWithOneRevokedCredential->setOwner($this->account);
    $this->queueDeveloperAppResponse($this->approvedAppWithOneRevokedCredential);
    $this->approvedAppWithOneRevokedCredential->save();

    // Approved app with all credentials revoked.
    $this->approvedAppWithAllRevokedCredential = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->approvedAppWithAllRevokedCredential->setOwner($this->account);
    $this->queueDeveloperAppResponse($this->approvedAppWithAllRevokedCredential);
    $this->approvedAppWithAllRevokedCredential->save();

    // Revoked app with revoked credential.
    $this->revokedAppWithRevokedCredential = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_REVOKED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->revokedAppWithRevokedCredential->setOwner($this->account);
    $this->queueDeveloperAppResponse($this->revokedAppWithRevokedCredential);
    $this->revokedAppWithRevokedCredential->save();

    // Approved app with expired credential.
    $this->approvedAppWithExpiredCredential = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->approvedAppWithExpiredCredential->setOwner($this->account);
    $this->queueDeveloperAppResponse($this->approvedAppWithExpiredCredential);
    $this->approvedAppWithExpiredCredential->save();

    // Revoked app with expired credential.
    $this->revokedAppWithExpiredCredential = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_REVOKED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->revokedAppWithExpiredCredential->setOwner($this->account);
    $this->queueDeveloperAppResponse($this->revokedAppWithExpiredCredential);
    $this->revokedAppWithExpiredCredential->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->stack->reset();
    try {
      if ($this->account) {
        $this->queueDeveloperResponse($this->account);
        $developer = \Drupal::entityTypeManager()
          ->getStorage('developer')
          ->create([
            'email' => $this->account->getEmail(),
          ]);
        $developer->delete();
      }

      if ($this->approvedAppWithApprovedCredential) {
        $this->approvedAppWithApprovedCredential->delete();
      }

      if ($this->approvedAppWithOneRevokedCredential) {
        $this->approvedAppWithOneRevokedCredential->delete();
      }

      if ($this->approvedAppWithAllRevokedCredential) {
        $this->approvedAppWithAllRevokedCredential->delete();
      }

      if ($this->revokedAppWithRevokedCredential) {
        $this->revokedAppWithRevokedCredential->delete();
      }

      if ($this->approvedAppWithExpiredCredential) {
        $this->approvedAppWithExpiredCredential->delete();
      }

      if ($this->revokedAppWithExpiredCredential) {
        $this->revokedAppWithExpiredCredential->delete();
      }

      if ($this->apiProduct) {
        $this->apiProduct->delete();
      }

    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }

    parent::tearDown();
  }

  /**
   * Test app warnings.
   *
   * @covers \Drupal\apigee_edge\Entity\AppWarningsChecker::getWarnings
   */
  public function testGetWarnings() {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\apigee_edge\Entity\AppWarningsCheckerInterface $app_warnings_checker */
    $app_warnings_checker = $this->container->get('apigee_edge.entity.app_warnings_checker');

    if ($this->integration_enabled) {
      $this->apiProduct = ApiProduct::create([
        'name' => $this->randomMachineName(),
        'displayName' => $this->randomMachineName(),
        'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
      ]);
      $this->apiProduct->save();

      // Revoke old credential and create a new valid one.
      $this->operationOnCredential($this->approvedAppWithOneRevokedCredential, 'revoke', 0);
      $this->operationOnCredential($this->approvedAppWithOneRevokedCredential, 'generate');

      // Revoke old credential.
      $this->operationOnCredential($this->approvedAppWithAllRevokedCredential, 'revoke', 0);

      // Revoke old credential.
      $this->operationOnCredential($this->revokedAppWithRevokedCredential, 'revoke', 0);
      $this->operationOnCredential($this->revokedAppWithRevokedCredential, 'generate');

      // Create a new cred that will expire in 5 seconds, delete old.
      $this->operationOnCredential($this->approvedAppWithExpiredCredential, 'delete', 0);
      $this->operationOnCredential($this->approvedAppWithExpiredCredential, 'generate', 0, 5 * 1000);

      // Create a new cred that will expire in 5 seconds, delete old.
      $this->operationOnCredential($this->revokedAppWithExpiredCredential, 'delete', 0);
      $this->operationOnCredential($this->revokedAppWithExpiredCredential, 'generate', 0, 5 * 1000);

      // Wait a bit and reset "request time" to make sure credentials
      // are considered expired.
      sleep(6);
      $request = Request::create('/', 'GET');
      $this->container->get('http_kernel')->handle($request);
    }
    else {
      $approved_credential = [
        "consumerKey" => $this->randomMachineName(),
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
        'expiresAt' => ($this->container->get('datetime.time')->getRequestTime() + 24 * 60 * 60) * 1000,
      ];

      $revoked_credential = [
        "consumerKey" => $this->randomMachineName(),
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_REVOKED,
        'expiresAt' => ($this->container->get('datetime.time')->getRequestTime() + 24 * 60 * 60) * 1000,
      ];

      $expired_credential = [
        "consumerKey" => $this->randomMachineName(),
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
        'expiresAt' => ($this->container->get('datetime.time')->getRequestTime() - 24 * 60 * 60) * 1000,
      ];

      $this->stack->queueMockResponse([
        'get_developer_apps_with_credentials' => [
          'apps' => [
            $this->approvedAppWithApprovedCredential,
            $this->approvedAppWithOneRevokedCredential,
            $this->revokedAppWithRevokedCredential,
            $this->approvedAppWithExpiredCredential,
            $this->revokedAppWithExpiredCredential,
          ],
          'credentials' => [
            $this->approvedAppWithApprovedCredential->id() => [
              $approved_credential,
            ],
            $this->approvedAppWithOneRevokedCredential->id() => [
              $approved_credential,
              $revoked_credential,
            ],
            $this->approvedAppWithAllRevokedCredential->id() => [
              $revoked_credential,
            ],
            $this->revokedAppWithRevokedCredential->id() => [
              $approved_credential,
              $revoked_credential,
            ],
            $this->approvedAppWithExpiredCredential->id() => [
              $expired_credential,
            ],
            $this->revokedAppWithExpiredCredential->id() => [
              $expired_credential,
            ],
          ],
        ],
      ]);

      $entity_type_manager->getStorage('developer_app')->loadMultiple();
    }

    // No warnings for approved app.
    $this->assertEmpty(array_filter($app_warnings_checker->getWarnings($this->approvedAppWithApprovedCredential)));

    // No warnings to approved app with one revoked credentials.
    $this->assertEmpty(array_filter($app_warnings_checker->getWarnings($this->approvedAppWithOneRevokedCredential)));

    // One warning for approved app with all credentials revoked.
    $warnings = array_filter($app_warnings_checker->getWarnings($this->approvedAppWithAllRevokedCredential));
    $this->assertCount(1, $warnings);
    $this->assertEquals('No valid credentials associated with this app.', (string) $warnings['revokedCred']);

    // No warnings to revoked app with revoked credentials.
    $this->assertEmpty(array_filter($app_warnings_checker->getWarnings($this->revokedAppWithRevokedCredential)));

    // One warning for approved app with expired credentials.
    $warnings = array_filter($app_warnings_checker->getWarnings($this->approvedAppWithExpiredCredential));
    $this->assertCount(1, $warnings);
    $this->assertEquals('At least one of the credentials associated with this app is expired.', (string) $warnings['expiredCred']);

    // One warning for revoked app with expired credentials.
    $warnings = array_filter($app_warnings_checker->getWarnings($this->revokedAppWithExpiredCredential));
    $this->assertCount(1, $warnings);
    $this->assertEquals('At least one of the credentials associated with this app is expired.', (string) $warnings['expiredCred']);
  }

}
