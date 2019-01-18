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

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apigee Edge API connection error page tests.
 *
 * @group apigee_edge
 */
class ErrorHandlerTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * User prefix.
   *
   * @var string
   */
  protected $prefix;

  /**
   * Drupal user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $drupalUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->prefix = $this->randomMachineName();
    // Disable Apigee Edge connection.
    $this->invalidateKey();
    // It is not necessary to create a developer here so skip
    // apigee_edge_user_presave().
    $this->disableUserPresave();
    $this->drupalUser = $this->createAccount([], TRUE, $this->prefix);
    $this->drupalUser->save();
    $this->enableUserPresave();
  }

  /**
   * Tests connection error page configuration and developer failures.
   */
  public function testErrorPages() {
    $this->drupalLogin($this->rootUser);
    $errorPageTitle = $this->getRandomGenerator()->word(16);
    $this->drupalPostForm(Url::fromRoute('apigee_edge.settings.error_page'), [
      'error_page_title' => $errorPageTitle,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $this->drupalLogin($this->drupalUser);
    $parameters = [
      'user' => $this->drupalUser->id(),
      'app' => 'x',
    ];

    $routes = [
      'apigee_edge_test.entity_storage_exception',
      'apigee_edge_test.api_exception',
      'entity.developer_app.collection_by_developer',
      'entity.developer_app.add_form_for_developer',
      'entity.developer_app.canonical_by_developer',
      'entity.developer_app.edit_form_for_developer',
      'entity.developer_app.delete_form_for_developer',
      'entity.developer_app.analytics_for_developer',
    ];

    foreach ($routes as $route_name) {
      $route = Url::fromRoute($route_name, $parameters);
      $this->drupalGet($route);
      $this->assertEquals(Response::HTTP_SERVICE_UNAVAILABLE, $this->getSession()->getStatusCode(), $route_name);
      $this->assertSession()->pageTextContains($errorPageTitle);
    }
  }

}
