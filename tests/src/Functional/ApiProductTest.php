<?php

namespace Drupal\Tests\apigee_edge\Functional;

use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\Tests\BrowserTestBase;

/**
 * Create, delete, update API Product entity tests.
 *
 * @group apigee_edge
 */
class ApiProductTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'apigee_edge',
  ];

  /**
   * @var \Drupal\apigee_edge\Entity\Developer
   */
  protected $developer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->developer = Developer::create([
      'email' => $this->randomMachineName() . '@example.com',
      'userName' => $this->randomMachineName(),
      'firstName' => $this->randomMachineName(),
      'lastName' => $this->randomMachineName(),
      'status' => Developer::STATUS_ACTIVE,
    ]);
    $this->developer->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $this->developer->delete();

    parent::tearDown();
  }

  protected function resetCache() {
    \Drupal::entityTypeManager()->getStorage('api_product')->resetCache();
  }

  public function testCrud() {
    /** @var ApiProduct $apiproduct */
    $apiproduct = ApiProduct::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomMachineName(),
      'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
    ]);

    $apiproduct->save();

    $this->assertNotEmpty($apiproduct->id());

    $this->resetCache();

    $apiproductlist = ApiProduct::loadMultiple();
    $this->assertContains($apiproduct->id(), array_keys($apiproductlist));

    $value = $this->randomMachineName();
    $apiproduct->setAttribute('test', $value);
    $apiproduct->save();

    $this->resetCache();

    /** @var ApiProduct $loadedApiProduct */
    $loadedApiProduct = ApiProduct::load($apiproduct->id());
    $this->assertEquals($value, $loadedApiProduct->getAttributeValue('test'));

    $apiproduct->delete();
  }

}
