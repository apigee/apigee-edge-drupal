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

    $this->account = $this->createAccount(['analytics own developer app']);
    $this->developer = Developer::createFromDrupalUser($this->account);

    $this->developerApp = DeveloperApp::create([
      'name' => $this->randomMachineName(),
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
        'since' => $since->sub(new \DateInterval('P3D')),
        'until' => $until->sub(new \DateInterval('P2D')),
      ],
    ];
  }

  /**
   * {@inheritdoc}
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
   * Visits the developer app analytics page using the given path.
   *
   * @param string $path
   *   The path of the analytics page.
   * @param bool $appendQueryParameters
   *   A boolean indicating whether the URL query parameters should be appended.
   * 
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  protected function visitAnalyticsPage(string $path, bool $appendQueryParameters = FALSE) {
    if ($appendQueryParameters) {
      $this->drupalGet($path, $this->queryParameters);
    }
    else {
      $this->drupalGet($path);
    }

    $this->assertAnalyticsPage();
  }

  /**
   * Asserts the visited analytics page.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  protected function assertAnalyticsPage() {
    $this->assertSession()->pageTextNotContains("Analytics of {$this->developerApp->getDisplayName()}");
    $this->assertSession()->pageTextContains("Your timezone: {$this->loggedInUser->getTimeZone()}");
    $this->assertSession()->pageTextContains('No performance data is available for the criteria you supplied.');
    $this->assertSession()->pageTextNotContains('Export CSV');
  }

}
