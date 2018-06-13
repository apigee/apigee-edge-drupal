<?php

namespace Drupal\Tests\apigee_edge\FunctionalJavascript;

use Drupal\Core\Url;

/**
 * App settings form tests.
 *
 * @group apigee_edge
 * @group apigee_edge_developer_app
 */
class AppSettingsFormTest extends ApigeeEdgeFunctionalJavascriptTestBase {

  /**
   * Default API product entity.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProduct
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
    $this->defaultApiProduct->delete();
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
    $this->drupalGet(Url::fromRoute('apigee_edge.settings.app'));
    $this->assertSession()->pageTextContains('Unable to retrieve API product list from Apigee Edge. Please ensure that Apigee Edge connection settings are correct.');

    // Visit the app settings form using valid API credentials.
    $this->restoreKey();
    $this->drupalGet(Url::fromRoute('apigee_edge.settings.app'));
    $this->assertSession()->pageTextNotContains('Unable to retrieve API product list from Apigee Edge. Please ensure that Apigee Edge connection settings are correct.');

    // Selecting default API product is not required by default.
    $product_list = $this->getSession()->getPage()->find('css', '#default-api-product-multiple fieldset');
    $this->assertFalse($product_list->hasAttribute('required'));
    $this->getSession()->getPage()->pressButton('edit-submit');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Selecting default API product is required.
    $this->getSession()->getPage()->uncheckField('edit-user-select');
    $web_assert->assertWaitOnAjaxRequest();
    $product_list = $this->getSession()->getPage()->find('css', '#default-api-product-multiple fieldset');
    $this->assertTrue($product_list->hasAttribute('required'));
    $this->getSession()->getPage()->pressButton('edit-submit');
    $this->assertSession()->pageTextContains('Default API Product field is required.');
    $this->getSession()->getPage()->checkField("default_api_product_multiple[{$this->defaultApiProduct->getName()}]");
    $this->getSession()->getPage()->pressButton('edit-submit');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Selecting default API product is not required.
    $this->getSession()->getPage()->checkField('edit-user-select');
    $web_assert->assertWaitOnAjaxRequest();
    $product_list = $this->getSession()->getPage()->find('css', '#default-api-product-multiple fieldset');
    $this->assertFalse($product_list->hasAttribute('required'));
    $this->getSession()->getPage()->uncheckField("default_api_product_multiple[{$this->defaultApiProduct->getName()}]");
    $this->getSession()->getPage()->pressButton('edit-submit');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
  }

}
