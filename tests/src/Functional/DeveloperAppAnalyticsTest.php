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
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Developer app analytics test.
 *
 * @group apigee_edge
 * @group apigee_edge_developer_app
 */
class DeveloperAppAnalyticsTest extends ApigeeEdgeFunctionalTestBase {

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
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  protected function setUp() {
    parent::setUp();

    $this->account = $this->createAccount(['analytics own developer_app']);
    $this->developer = Developer::createFromDrupalUser($this->account);

    $this->developerApp = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->developerApp->setOwner($this->account);
    $this->developerApp->save();

    // Build the URL query string.
    $since = new DrupalDateTime();
    $until = new DrupalDateTime();
    $this->queryParameters = [
      'query' => [
        'metric' => 'min_response_time',
        'since' => $since->sub(new \DateInterval('P3D'))->getTimestamp(),
        'until' => $until->sub(new \DateInterval('P2D'))->getTimestamp(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function tearDown() {
    $this->developer->delete();
    parent::tearDown();
  }

  /**
   * Tests the analytics page with the logged in developer app owner.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testAuthenticatedUserAnalytics() {
    $this->drupalLogin($this->account);

    $path = "/user/{$this->account->id()}/apps/{$this->developerApp->getName()}/analytics";
    $this->visitAnalyticsPage($path);
    $this->visitAnalyticsPage($path, TRUE);
  }

  /**
   * Tests the analytics page with the logged in admin user.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testAdminAnalytics() {
    $this->drupalLogin($this->rootUser);

    $path = "/developer-apps/{$this->developerApp->id()}/analytics";
    $this->visitAnalyticsPage($path);
    $this->visitAnalyticsPage($path, TRUE);

    $path = "/user/{$this->account->id()}/apps/{$this->developerApp->getName()}/analytics";
    $this->visitAnalyticsPage($path);
    $this->visitAnalyticsPage($path, TRUE);
  }

  /**
   * Tests the export analytics route.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function testExportAnalytics() {
    $this->drupalLogin($this->rootUser);
    $data_id = Crypt::randomBytesBase64();
    $this->drupalGet("/analytics/export/{$data_id}/csv");
    $this->assertEquals(403, $this->getSession()->getStatusCode());

    // Without CSRF token.
    $store = $this->container->get('tempstore.private')->get('apigee_edge.analytics');
    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store->set($data_id = Crypt::randomBytesBase64(), []);
    $this->drupalGet("/analytics/export/{$data_id}/csv");
    $this->assertEquals(403, $this->getSession()->getStatusCode());
  }

  /**
   * Visits the developer app analytics page using the given path.
   *
   * @param string $path
   *   The path of the analytics page.
   * @param bool $appendQueryParameters
   *   A boolean indicating whether the URL query parameters should be appended.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Exception
   */
  protected function visitAnalyticsPage(string $path, bool $appendQueryParameters = FALSE) {
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
    $this->drupalGet($path, [
      'query' => [
        'metric' => 'message_count',
        'since' => $since_in_the_future->getTimestamp(),
        'until' => $until->getTimestamp(),
      ],
    ]);
    $this->assertAnalyticsPage();
    $this->assertSession()->pageTextContains('The end date cannot be before the start date.');

    // Start date is in the future.
    $until = new DrupalDateTime();
    $this->drupalGet($path, [
      'query' => [
        'metric' => 'message_count',
        'since' => $since_in_the_future->getTimestamp(),
        'until' => $until->add(new \DateInterval('P4D'))->getTimestamp(),
      ],
    ]);
    $this->assertAnalyticsPage();
    $this->assertSession()->pageTextContains('Start date cannot be in future. The current local time of the Developer Portal:');

    // Invalid metric in the URL query.
    $this->drupalGet($path, [
      'query' => [
        'metric' => $this->randomMachineName(),
        'since' => $this->randomMachineName(),
        'until' => $this->randomMachineName(),
      ],
    ]);
    $this->assertAnalyticsPage();
    $this->assertSession()->pageTextContains('Invalid parameter metric in the URL.');

    // Invalid timestamp parameters in the URL query.
    $this->drupalGet($path, [
      'query' => [
        'metric' => 'min_response_time',
        'since' => $this->randomMachineName(),
        'until' => $this->randomMachineName(),
      ],
    ]);
    $this->assertAnalyticsPage();
    $this->assertSession()->pageTextContains('Invalid URL query parameters.');
  }

  /**
   * Asserts the visited analytics page.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  protected function assertAnalyticsPage() {
    $this->assertSession()->pageTextContains("Analytics of {$this->developerApp->getDisplayName()}");
    $this->assertSession()->pageTextContains("Your timezone: {$this->loggedInUser->getTimeZone()}");
    $this->assertSession()->pageTextContains('No performance data is available for the criteria you supplied.');
    $this->assertSession()->pageTextNotContains('Export CSV');
  }

}
