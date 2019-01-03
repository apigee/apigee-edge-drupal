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

namespace Drupal\Tests\apigee_edge\FunctionalJavascript;

use Drupal\Core\Url;

/**
 * App settings form Javascript tests.
 *
 * @group apigee_edge
 * @group apigee_edge_javascript
 * @group apigee_edge_developer_app
 */
class AppSettingsFormTest extends ApigeeEdgeFunctionalJavascriptTestBase {

  /**
   * Default API product entity.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProductInterface
   */
  protected $defaultApiProduct;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->defaultApiProduct = $this->createProduct();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    try {
      $this->defaultApiProduct->delete();
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    parent::tearDown();
  }

  /**
   * Tests the app settings AJAX form.
   */
  public function testAppSettingsForm() {
    $web_assert = $this->assertSession();
    $this->drupalLogin($this->rootUser);

    // Visit the app settings form using invalid API credentials.
    $this->invalidateKey();
    $this->drupalGet(Url::fromRoute('apigee_edge.settings.general_app'));
    $this->assertSession()->pageTextContains('Unable to retrieve API product list from Apigee Edge. Please ensure that Apigee Edge connection settings are correct.');

    // Visit the app settings form using valid API credentials.
    $this->restoreKey();
    $this->drupalGet(Url::fromRoute('apigee_edge.settings.general_app'));
    $this->assertSession()->pageTextNotContains('Unable to retrieve API product list from Apigee Edge. Please ensure that Apigee Edge connection settings are correct.');

    // Selecting default API product is not required by default.
    $product_list = $this->getSession()->getPage()->find('css', '#default-api-product-multiple fieldset');
    $this->assertFalse($product_list->hasAttribute('required'));
    $this->getSession()->getPage()->pressButton('edit-submit');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Selecting default API product is required.
    $this->getSession()->getPage()->uncheckField('edit-user-select');
    $web_assert->assertWaitOnAjaxRequest();
    $this->createScreenshot("AppSettingsFormTest-" . __FUNCTION__);
    $product_list = $this->getSession()->getPage()->find('css', '#default-api-product-multiple fieldset');
    $this->assertTrue($product_list->hasAttribute('required'));
    $this->getSession()->getPage()->pressButton('edit-submit');
    $this->assertSession()->pageTextContains('Default API Products field is required.');
    $this->getSession()->getPage()->checkField("default_api_product_multiple[{$this->defaultApiProduct->getName()}]");
    $this->getSession()->getPage()->pressButton('edit-submit');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Selecting default API product is not required.
    $this->getSession()->getPage()->checkField('edit-user-select');
    $web_assert->assertWaitOnAjaxRequest();
    $this->createScreenshot("AppSettingsFormTest-" . __FUNCTION__);
    $product_list = $this->getSession()->getPage()->find('css', '#default-api-product-multiple fieldset');
    $this->assertFalse($product_list->hasAttribute('required'));
    $this->getSession()->getPage()->uncheckField("default_api_product_multiple[{$this->defaultApiProduct->getName()}]");
    $this->getSession()->getPage()->pressButton('edit-submit');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
  }

}
