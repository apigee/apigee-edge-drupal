<?php

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Api\Management\Entity\App;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\Tests\BrowserTestBase;

/**
 * Create, delete, update Developer App entity tests.
 *
 * @group ApigeeEdge
 */
class DeveloperAppTest extends BrowserTestBase {

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
    \Drupal::entityTypeManager()->getStorage('developer_app')->resetCache();
  }

  public function testCrud() {
    /** @var DeveloperApp $app */
    $app = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $app->save();

    $this->assertNotEmpty($app->getAppId());

    $this->resetCache();

    $this->assertNotEmpty(DeveloperApp::load($app->id()));

    $applist = DeveloperApp::loadMultiple();
    $this->assertContains($app->id(), array_keys($applist));

    $value = $this->randomMachineName();
    $app->setAttribute('test', $value);
    $app->save();

    $this->resetCache();

    /** @var DeveloperApp $loadedApp */
    $loadedApp = DeveloperApp::load($app->id());
    $this->assertEquals($value, $loadedApp->getAttributeValue('test'));

    $app->delete();
  }

}
