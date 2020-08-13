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

namespace Drupal\Tests\apigee_edge\Kernel\Entity\ListBuilder;

use Apigee\Edge\Api\Management\Entity\App;
use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\Component\Utility\Html;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\apigee_edge\Kernel\ApigeeEdgeKernelTestTrait;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;

/**
 * Tests the AppListBuilder.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class AppListBuilderTest extends KernelTestBase {

  use ApigeeMockApiClientHelperTrait, ApigeeEdgeKernelTestTrait, UserCreationTrait;

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
    'options'
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
  protected $approvedAppWithRevokedCredential;

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
  protected function setUp() {
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
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->approvedAppWithApprovedCredential->setOwner($this->account);
    $this->queueDeveloperAppResponse($this->approvedAppWithApprovedCredential);
    $this->approvedAppWithApprovedCredential->save();

    // Approved app with revoked credential.
    $this->approvedAppWithRevokedCredential = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->approvedAppWithRevokedCredential->setOwner($this->account);
    $this->queueDeveloperAppResponse($this->approvedAppWithRevokedCredential);
    $this->approvedAppWithRevokedCredential->save();

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
  protected function tearDown() {
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

      if ($this->approvedAppWithRevokedCredential) {
        $this->approvedAppWithRevokedCredential->delete();
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
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }

    parent::tearDown();
  }

  /**
   * Test app warnings.
   */
  public function testAppWarnings() {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
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
      "status" => AppCredentialInterface::STATUS_REVOKED,
      'expiresAt' => ($this->container->get('datetime.time')->getRequestTime() - 24 * 60 * 60) * 1000,
    ];

    $this->stack->queueMockResponse([
      'get_developer_apps_with_credentials' => [
        'apps' => [
          $this->approvedAppWithApprovedCredential,
          $this->approvedAppWithRevokedCredential,
          $this->revokedAppWithRevokedCredential,
          $this->approvedAppWithExpiredCredential,
          $this->revokedAppWithExpiredCredential,
        ],
        'credentials' => [
          $this->approvedAppWithApprovedCredential->id() => [
            $approved_credential,
          ],
          $this->approvedAppWithRevokedCredential->id() => [
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

    $build = $entity_type_manager->getListBuilder(static::ENTITY_TYPE)->render();

    // No warnings for approved app.
    $this->assertEmpty($build['table']['#rows'][$this->getStatusRowKey($this->approvedAppWithApprovedCredential)]['data']);

    // No warnings to approved app with revoked credentials.
    $this->assertEmpty($build['table']['#rows'][$this->getStatusRowKey($this->approvedAppWithRevokedCredential)]['data']);

    // No warnings to revoked app with revoked credentials.
    $this->assertEmpty($build['table']['#rows'][$this->getStatusRowKey($this->revokedAppWithRevokedCredential)]['data']);

    // One warning for approved app with expired credentials.
    $warnings = $build['table']['#rows'][$this->getStatusRowKey($this->approvedAppWithExpiredCredential)]['data'];
    $this->assertCount(1, $warnings);
    $this->assertEqual('At least one of the credentials associated with this app is expired.', (string) $warnings['info']['data']['#items'][0]);

    // No warnings for revoked app with expired credentials.
    $this->assertEmpty($build['table']['#rows'][$this->getStatusRowKey($this->revokedAppWithExpiredCredential)]['data']);
  }

  /**
   * Helper to get the status row key for an app.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity.
   * @param string $key
   *   The key: warning or info.
   *
   * @return string
   *   The status row key.
   */
  protected function getStatusRowKey(AppInterface $app, $key = "warning"): string {
    return Html::getId($app->getAppId()) . '-' . $key;
  }

}
