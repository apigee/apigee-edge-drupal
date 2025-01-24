<?php

/**
 * Copyright 2018 Google Inc.
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

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Api\Management\Entity\App;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;

/**
 * Developer app analytics test.
 *
 * @group apigee_edge
 * @group apigee_edge_developer_app
 */
class DeveloperAppAnalyticsTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * The Drupal user that belongs to the developer app's developer.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The developer entity.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * The developer app entity.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $developerApp;

  /**
   * The URL query parameters.
   *
   * @var array
   */
  protected $queryParameters;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->account = $this->createAccount([
      'analytics own developer_app',
      'analytics any developer_app',
    ]);

    $this->queueDeveloperResponse($this->account, 200);
    $this->developer = Developer::load($this->account->getEmail());

    $this->developerApp = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->developerApp->setOwner($this->account);

    $this->queueDeveloperAppResponse($this->developerApp, 201);
    $this->developerApp->save();

    // Build the URL query string.
    $since = new DrupalDateTime();
    $until = new DrupalDateTime();
    $this->queryParameters = [
      'query' => [
        'metric' => 'min(total_response_time)',
        'since' => $since->sub(new \DateInterval('P3D'))->getTimestamp(),
        'until' => $until->sub(new \DateInterval('P2D'))->getTimestamp(),
        'environment' => 'prod',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    try {
      if ($this->developer !== NULL) {
        $this->developer->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    parent::tearDown();
  }

  /**
   * Tests the analytics page with the logged in developer app owner.
   */
  public function testAnalytics() {
    $this->drupalLogin($this->account);

    $path = Url::fromRoute('entity.developer_app.analytics_for_developer', [
      'user' => $this->account->id(),
      'app' => $this->developerApp->getName(),
    ])->toString();

    // App is loaded once and then saved into cache, so the mock
    // developerApp response only needs to be added once.
    $this->queueDeveloperAppResponse($this->developerApp);

    $this->visitAnalyticsPage($path);
    $this->visitAnalyticsPage($path, TRUE);

    $path = Url::fromRoute('entity.developer_app.analytics', [
      'developer_app' => $this->developerApp->id(),
    ])->toString();
    $this->visitAnalyticsPage($path);
    $this->visitAnalyticsPage($path, TRUE);

    $this->exportAnalyticsTest();
  }

  /**
   * Visits the developer app analytics page using the given path.
   *
   * @param string $path
   *   The path of the analytics page.
   * @param bool $appendQueryParameters
   *   A boolean indicating whether the URL query parameters should be appended.
   */
  protected function visitAnalyticsPage(string $path, bool $appendQueryParameters = FALSE) {
    $this->queueAppAnalyticsStackedResponse();
    if ($appendQueryParameters) {
      $this->drupalGet($path, $this->queryParameters);
    }
    else {
      $this->drupalGet($path);
    }

    $this->assertAnalyticsPage();
    $this->assertSession()->pageTextNotContains('Invalid URL query parameters.');

    // End date is before the start date.
    $since_in_the_future = new DrupalDateTime();
    $since_in_the_future->add(new \DateInterval('P3D'));
    $until = new DrupalDateTime();
    $this->queueAppAnalyticsStackedResponse();
    $this->drupalGet($path, [
      'query' => [
        'metric' => 'sum(message_count)',
        'since' => $since_in_the_future->getTimestamp(),
        'until' => $until->getTimestamp(),
        'environment' => 'prod',
      ],
    ]);
    $this->assertAnalyticsPage();
    $this->assertSession()->pageTextContains('The end date cannot be before the start date.');

    // Start date is in the future.
    $until = new DrupalDateTime();
    $this->queueAppAnalyticsStackedResponse();
    $this->drupalGet($path, [
      'query' => [
        'metric' => 'sum(message_count)',
        'since' => $since_in_the_future->getTimestamp(),
        'until' => $until->add(new \DateInterval('P4D'))->getTimestamp(),
        'environment' => 'prod',
      ],
    ]);
    $this->assertAnalyticsPage();
    $this->assertSession()->pageTextContains('Start date cannot be in future. The current local time of the Developer Portal:');

    // Invalid metric in the URL query.
    $this->queueAppAnalyticsStackedResponse();
    $this->drupalGet($path, [
      'query' => [
        'metric' => $this->randomMachineName(),
        'since' => $this->randomMachineName(),
        'until' => $this->randomMachineName(),
        'environment' => 'prod',
      ],
    ]);
    $this->assertAnalyticsPage();
    $this->assertSession()->pageTextContains('Invalid parameter metric in the URL.');

    // Invalid timestamp parameters in the URL query.
    $this->queueAppAnalyticsStackedResponse();
    $this->drupalGet($path, [
      'query' => [
        'metric' => 'min(total_response_time)',
        'since' => $this->randomMachineName(),
        'until' => $this->randomMachineName(),
        'environment' => 'prod',
      ],
    ]);
    $this->assertAnalyticsPage();
    $this->assertSession()->pageTextContains('Invalid URL query parameters.');

    // Invalid environment parameters in the URL query.
    $this->queueAppAnalyticsStackedResponse();
    $this->drupalGet($path, [
      'query' => [
        'metric' => 'min(total_response_time)',
        'since' => (new DrupalDateTime())->getTimestamp(),
        'until' => (new DrupalDateTime())->getTimestamp(),
        'environment' => $this->randomMachineName(),
      ],
    ]);
    $this->assertAnalyticsPage();
    $this->assertSession()->pageTextContains('Invalid parameter environment in the URL.');
  }

  /**
   * Asserts the visited analytics page.
   */
  protected function assertAnalyticsPage() {
    $timezone = date_default_timezone_get();
    $this->assertSession()->pageTextContains("Analytics of {$this->developerApp->label()}");
    $this->assertSession()->pageTextContains("Your timezone: {$timezone}");
    $this->assertSession()->pageTextContains('No performance data is available for the criteria you supplied.');
    $this->assertSession()->pageTextNotContains('Export CSV');
  }

  /**
   * Tests the export analytics route.
   */
  protected function exportAnalyticsTest() {
    $this->drupalLogin($this->rootUser);
    $data_id = Crypt::randomBytesBase64();
    $this->drupalGet(Url::fromRoute('apigee_edge.export_analytics.csv', ['data_id' => $data_id]));
    $this->assertEquals(403, $this->getSession()->getStatusCode());

    // Without CSRF token.
    $store = $this->container->get('tempstore.private')->get('apigee_edge.analytics');
    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store->set($data_id = Crypt::randomBytesBase64(), []);
    $this->drupalGet(Url::fromRoute('apigee_edge.export_analytics.csv', ['data_id' => $data_id]));
    $this->assertEquals(403, $this->getSession()->getStatusCode());
  }

  /**
   * Add an app analytics mock response to the stack.
   */
  protected function queueAppAnalyticsStackedResponse() {
    $this->stack->queueMockResponse([
      'app_analytics' => [],
    ]);
  }

}
