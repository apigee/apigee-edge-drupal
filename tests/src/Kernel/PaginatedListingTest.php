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

namespace Drupal\Tests\apigee_edge\Kernel;

use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\Core\Database\Database;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;

/**
 * Tests the paginated listing in controllers.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class PaginatedListingTest extends KernelTestBase {

  use ApigeeMockApiClientHelperTrait;

  /**
   * Array of created API products.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProductInterface[]
   */
  protected $apiProducts = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'key',
    'file',
    'entity',
    'dblog',
    'apigee_edge',
    'apigee_mock_api_client',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['apigee_edge']);

    $this->installSchema('system', ['sequences']);
    $this->installSchema('dblog', ['watchdog']);

    $this->apigeeTestHelperSetup();

    if ($this->integration_enabled) {
      static::markTestSkipped('Only run this test when using Mock API Client.');
      return;
    }

    for ($i = 0; $i < 5; $i++) {
      /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $api_product */
      $machine_name = $this->randomMachineName();
      $this->apiProducts[$machine_name] = ApiProduct::create([
        'name' => $machine_name,
        'displayName' => $this->randomMachineName(),
        'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
      ]);
    }

    $this->addOrganizationMatchedResponse();
  }

  /**
   * Tests the pagination fallback method.
   *
   * If a pagination call with "expand=true" fails, it tries to load each
   * entity individually.
   */
  public function testPaginatedListingFallback() {

    // First return a 404 error for the listing call with "expand=true".
    $this->stack->queueMockResponse([
      'get_not_found' => [
        'status_code' => 404,
        'code' => 'cps.kms.ApiProductDoesNotExist',
        'message' => 'API Product [name] does not exist for  tenant [tenant] and id [null]',
      ],
    ]);

    // Then return a successful listing call with "expand=false".
    $this->stack->queueMockResponse([
      'ids_only' => [
        'ids' => array_keys($this->apiProducts),
      ],
    ]);

    // Throw an error for the first product loaded individually.
    // Then return the rest individually.
    foreach ($this->apiProducts as $apiProduct) {
      if (!isset($failing_id)) {
        $failing_id = $apiProduct->id();
        $this->stack->queueMockResponse([
          'get_not_found' => [
            'status_code' => 404,
            'code' => 'cps.kms.ApiProductDoesNotExist',
            'message' => 'API Product [name] does not exist for  tenant [tenant] and id [null]',
          ],
        ]);
      }
      else {
        $this->stack->queueMockResponse(['api_product' => ['product' => $apiProduct]]);
      }
    }

    $entities = \Drupal::entityTypeManager()
      ->getStorage('api_product')
      ->loadMultiple();

    $this->assertEqual(count($entities), count($this->apiProducts) - 1);

    foreach ($this->apiProducts as $apiProduct) {
      if ($apiProduct->id() == $failing_id) {
        $this->assertFalse(array_key_exists($apiProduct->id(), $entities));
      }
      else {
        $this->assertTrue(array_key_exists($apiProduct->id(), $entities));
      }
    }

    // Verify failing listing call gets logged.
    $logged = (bool) Database::getConnection()->select('watchdog')
      ->fields('watchdog', ['wid'])
      ->condition('type', 'apigee_edge')
      ->condition('message', 'Could not load paginated entity list%', 'LIKE')
      ->condition('severity', RfcLogLevel::ERROR)
      ->execute()
      ->fetchField();
    $this->assertTrue($logged);

    // Verify failing entity load gets logged.
    $logged = (bool) Database::getConnection()->select('watchdog')
      ->fields('watchdog', ['wid', 'message'])
      ->condition('type', 'apigee_edge')
      ->condition('message', '%failed to load entity with ID%', 'LIKE')
      ->condition('variables', "%$failing_id%", 'LIKE')
      ->condition('severity', RfcLogLevel::ERROR)
      ->execute()
      ->fetchField();
    $this->assertTrue($logged);
  }

  /**
   * Tests that an error is thrown if pagination fallback method fails too.
   *
   * @expectedException \Drupal\Core\Entity\EntityStorageException
   */
  public function testPaginatedListingFallbackFail() {

    // First return a 404 error for the listing call with "expand=true".
    $this->stack->queueMockResponse([
      'get_not_found' => [
        'status_code' => 404,
        'code' => 'cps.kms.ApiProductDoesNotExist',
        'message' => 'API Product [name] does not exist for  tenant [tenant] and id [null]',
      ],
    ]);

    // Then return the same error for the listing call with "expand=false".
    $this->stack->queueMockResponse([
      'get_not_found' => [
        'status_code' => 404,
        'code' => 'cps.kms.ApiProductDoesNotExist',
        'message' => 'API Product [name] does not exist for  tenant [tenant] and id [null]',
      ],
    ]);

    $entities = \Drupal::entityTypeManager()
      ->getStorage('api_product')
      ->loadMultiple();
  }

}
