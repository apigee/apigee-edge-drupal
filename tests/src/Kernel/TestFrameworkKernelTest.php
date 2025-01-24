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

use Drupal\Core\Form\FormState;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\apigee_edge\Entity\Developer;
use GuzzleHttp\Psr7\Response;
use Http\Message\Authentication\AutoBasicAuth;

/**
 * Tests the testing framework for testing offline.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class TestFrameworkKernelTest extends ApigeeEdgeKernelTestBase {

  use ApigeeMockApiClientHelperTrait;
  use UserCreationTrait;

  /**
   * Indicates this test class is mock API client ready.
   *
   * @var bool
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'key',
    'file',
    'entity',
    'syslog',
    'apigee_edge',
    'apigee_mock_api_client',
  ];

  /**
   * Developer entities to test.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface[]
   */
  protected $developers = [];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['apigee_edge']);

    // Prepare to create a user.
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);

    $this->apigeeTestHelperSetup();
  }

  /**
   * Tests that the service override is working properly.
   */
  public function testServiceModification() {
    self::assertEquals(
      (string) $this->container->getDefinition('apigee_edge.sdk_connector')->getArgument(0),
      'apigee_mock_api_client.mock_http_client_factory'
    );
  }

  /**
   * Tests that API responses can be queued.
   */
  public function testInlineResponseQueue() {
    if ($this->integration_enabled) {
      static::markTestSkipped('Only test the response queue when running offline tests.');
      return;
    }

    // Queue a response from the mock server.
    $this->stack->addResponse(new Response(200, [], "{\"status\": \"success\"}"));

    // Execute a client call.
    $response = $this->sdkConnector->buildClient(new AutoBasicAuth())->get('/');

    self::assertEquals("200", $response->getStatusCode());
    self::assertEquals('{"status": "success"}', (string) $response->getBody());
  }

  /**
   * Tests that a response is fetched from the mocks using response matcher.
   */
  public function testMatchedResponse() {
    if ($this->integration_enabled) {
      $this->markTestSkipped('Integration enabled, skipping test.');
    }

    $org_name = $this->randomMachineName();

    // Stack up org response.
    $this->addOrganizationMatchedResponse($org_name);

    $org_controller = $this->container->get('apigee_edge.controller.organization');
    $org = $org_controller->load($org_name);

    $this->assertEquals($org->getName(), $org_name);
  }

  /**
   * Tests that a response is fetched from the stacked mocks.
   */
  public function testStackedMockResponse() {
    if ($this->integration_enabled) {
      $this->markTestSkipped('Integration enabled, skipping test.');
    }

    $test_user = [
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => $this->randomMachineName(),
      'first_name' => $this->getRandomGenerator()->word(16),
      'last_name' => $this->getRandomGenerator()->word(16),
    ];

    $account = $this->entityTypeManager->getStorage('user')->create($test_user);
    $this->assertEquals($test_user['mail'], $account->mail->value);

    $this->queueDeveloperResponse($account);

    $developerStorage = $this->entityTypeManager->getStorage('developer');
    $developerStorage->resetCache([$test_user['mail']]);
    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = $developerStorage->load($test_user['mail']);

    $this->assertEquals($developer->getEmail(), $test_user['mail']);
    // Attribute is set by mock twig template.
    $this->assertEquals($developer->getAttributeValue('IS_MOCK_CLIENT'), 1);
  }

  /**
   * Test integration enabled.
   *
   * Tests that responses are not fetched from the stacked mocks when
   * integration is enabled.
   */
  public function testNotStackedMockResponse() {
    if (!$this->integration_enabled) {
      $this->markTestSkipped('Integration not enabled, skipping test.');
    }

    $developerStorage = $this->entityTypeManager->getStorage('developer');

    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = $developerStorage->create([
      'email' => $this->randomMachineName() . '@example.com',
      'userName' => $this->randomMachineName(),
      'firstName' => $this->getRandomGenerator()->word(8),
      'lastName' => $this->getRandomGenerator()->word(8),
    ]);

    $developerStorage->resetCache([$developer->getEmail()]);

    // Unsaved developer, should not be found (should ignore the mock stack).
    $this->queueDeveloperResponseFromDeveloper($developer);
    $loaded_developer = $developerStorage->load($developer->getEmail());
    $this->isEmpty($loaded_developer);

    // Saved developer, following calls should load from the real API,
    // and ignore all stacked responses.
    $this->queueDeveloperResponseFromDeveloper($developer, 201);
    $developer->save();

    // Add to the array of developers to be deleted in tearDown().
    $this->developers[] = $developer;

    $this->queueDeveloperResponseFromDeveloper($developer);
    $loaded_developer = $developerStorage->load($developer->getEmail());
    $this->assertInstanceOf(Developer::class, $loaded_developer);
    $this->assertEquals($loaded_developer->getEmail(), $developer->getEmail());

    // This line is what actually tests that the mock is not used since the mock template sets this attribute.
    $this->assertEmpty($developer->getAttributeValue('IS_MOCK_CLIENT'));
  }

  /**
   * Tests a more complex scenario.
   *
   * Registers a user and fetches the "created" developer from the API mocks.
   */
  public function testRegisterUser() {
    if ($this->integration_enabled) {
      $this->markTestSkipped('Integration enabled, skipping test.');
    }

    // Stack up org response.
    $this->addOrganizationMatchedResponse();

    // Run as anonymous user.
    $this->setUpCurrentUser(['uid' => 0]);

    $test_user = [
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => $this->randomMachineName(),
      'first_name' => $this->getRandomGenerator()->word(16),
      'last_name' => $this->getRandomGenerator()->word(16),
    ];

    $form_data = [
      'mail' => $test_user['mail'],
      'name' => $test_user['name'],
      'first_name[0][value]' => $test_user['first_name'],
      'last_name[0][value]' => $test_user['last_name'],
      'op' => 'Create new account',
      'pass' => $this->getRandomGenerator()->word(8),
    ];

    $account = $this->entityTypeManager->getStorage('user')->create($test_user);

    $formObject = $this->entityTypeManager
      ->getFormObject('user', 'register')
      ->setEntity($account);

    $form_state = new FormState();
    $form_state->setUserInput($form_data);
    $form_state->setValues($form_data);

    $this->stack->queueMockResponse('get_not_found');
    $this->queueDeveloperResponse($account);

    \Drupal::formBuilder()->submitForm($formObject, $form_state);

    $developerStorage = $this->entityTypeManager->getStorage('developer');
    $developerStorage->resetCache([$test_user['mail']]);

    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = $developerStorage->load($test_user['mail']);

    $this->assertEquals($developer->getEmail(), $test_user['mail']);
    // Attribute is set by mock twig template.
    $this->assertEquals($developer->getAttributeValue('IS_MOCK_CLIENT'), 1);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->integration_enabled && !empty($this->developers)) {
      foreach ($this->developers as $developer) {
        $developer->delete();
      }
    }

    parent::tearDown();
  }

}
