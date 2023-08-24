<?php

/**
 * Copyright 2018 Google Inc.
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

namespace Drupal\Tests\apigee_edge\FunctionalJavascript;

use Drupal\Tests\apigee_edge\Functional\DeveloperAppUITestTrait;

/**
 * Developer app UI Javascript tests.
 *
 * @group apigee_edge
 * @group apigee_edge_javascript
 * @group apigee_edge_developer_app
 */
class DeveloperAppUITest extends ApigeeEdgeFunctionalJavascriptTestBase {

  use DeveloperAppUITestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->products[] = $this->createProduct();
    $this->account = $this->createAccount(static::$permissions);
    $this->drupalLogin($this->account);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    try {
      if ($this->account !== NULL) {
        $this->account->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    foreach ($this->products as $product) {
      try {
        if ($product !== NULL) {
          $product->delete();
        }
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }
    parent::tearDown();
  }

  /**
   * Tests callback url validation on the client-side.
   */
  public function testCallbackUrlValidationClientSide() {
    $isValidInput = function () : bool {
      return $this->getSession()->evaluateScript('document.getElementById("edit-callbackurl-0-value").checkValidity()');
    };

    // Override default configuration.
    $pattern_error_message = 'It must be https://example.com';
    $this->config('apigee_edge.common_app_settings')
      ->set('callback_url_pattern', '^https:\/\/example.com')
      ->set('callback_url_pattern_error_message', $pattern_error_message)
      ->save();

    $app = $this->createDeveloperApp([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomString(),
      'callbackUrl' => $this->randomMachineName(),
    ], $this->account, [$this->products[0]->id()]);
    $app_edit_url = $app->toUrl('edit-form-for-developer');

    $this->drupalGet($app_edit_url);
    $this->drupalGet($app_edit_url);
    $this->submitForm([], 'Save');
    $this->createScreenshot('DeveloperAppUITest-' . __FUNCTION__);
    $this->assertFalse($isValidInput());
    $this->drupalGet($app_edit_url);
    $this->submitForm(['callbackUrl[0][value]' => 'http://example.com'], 'Save');
    $this->createScreenshot('DeveloperAppUITest-' . __FUNCTION__);
    $this->assertFalse($isValidInput());
    $this->assertEquals($pattern_error_message, $this->getSession()->evaluateScript('document.getElementById("edit-callbackurl-0-value").title'));
    $this->drupalGet($app_edit_url);
    $this->submitForm(['callbackUrl[0][value]' => 'https://example.com'], 'Save');
    $this->assertSession()->pageTextContains('App has been successfully updated.');
    $this->assertSession()->pageTextContains('https://example.com');
  }

}
