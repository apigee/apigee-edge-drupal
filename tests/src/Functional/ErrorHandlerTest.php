<?php

namespace Drupal\Tests\apigee_edge\Functional;

class ErrorHandlerTest extends ApigeeEdgeFunctionalTestBase {

  public static $modules = [
    'apigee_edge',
    'apigee_edge_test',
  ];

  protected function setUp() {
    parent::setUp();
  }

  public function testErrorPage() {
    $this->drupalLogin($this->rootUser);
    $errorPageTitle = $this->getRandomGenerator()->word(16);
    $this->drupalPostForm('/admin/config/apigee-edge/error-page-settings', [
      'error_page_title' => $errorPageTitle,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $paths = [
      '/exception/entity-storage',
      '/exception/api',
    ];

    foreach ($paths as $path) {
      $this->drupalGet($path);
      $this->assertSession()->pageTextContains($errorPageTitle);
    }
  }

}
