<?php

namespace Drupal\Tests\apigee_edge\Functional;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\Tests\BrowserTestBase;

/**
 * @group ApigeeEdge
 */
class QueryTest extends BrowserTestBase {

  public static $modules = [
    'apigee_edge',
  ];

  /**
   * @var \Apigee\Edge\Api\Management\Controller\DeveloperController
   */
  protected $developerController;

  /**
   * @var \Drupal\apigee_edge\Entity\Storage\DeveloperStorageInterface
   */
  protected $storage;

  protected $edgeDevelopers = [
    ['email' => 'test00@example.com', 'userName' => 'test00', 'firstName' => 'Test00', 'lastName' => 'User'],
    ['email' => 'test01@example.com', 'userName' => 'test01', 'firstName' => 'Test01', 'lastName' => 'User'],
    ['email' => 'test02@example.com', 'userName' => 'test02', 'firstName' => 'Test02', 'lastName' => 'User'],
    ['email' => 'test03@example.com', 'userName' => 'test03', 'firstName' => 'Test03', 'lastName' => 'User'],
    ['email' => 'test04@example.com', 'userName' => 'test04', 'firstName' => 'Test04', 'lastName' => 'User'],
  ];

  protected function setUp() {
    parent::setUp();

    /** @var \Drupal\apigee_edge\SDKConnectorInterface $connector */
    $connector = \Drupal::service('apigee_edge.sdk_connector');
    $this->developerController = $connector->getControllerByEntity('developer');

    foreach ($this->edgeDevelopers as $edgeDeveloper) {
      $this->developerController->create(new Developer($edgeDeveloper));
    }

    $this->storage = \Drupal::entityTypeManager()->getStorage('developer');
  }

  public function testQuery() {
    $query = $this->storage->getQuery();
    $query->sort('lastName');
    $query->sort('email', 'DESC');
    $results = $query->execute();
    $this->assertEquals(array_values([
      'test04@example.com',
      'test03@example.com',
      'test02@example.com',
      'test01@example.com',
      'test00@example.com',
    ]), array_values($results));

    $query = $this->storage->getQuery();
    $query->sort('email');
    $query->range(1, 1);
    $results = $query->execute();
    $this->assertEquals(array_values(['test01@example.com']), array_values($results));

    $this->assertEquals(5, $this->storage->getQuery()->count()->execute());
  }

  protected function tearDown() {
    $ids = $this->developerController->getEntityIds();
    foreach ($ids as $id) {
      $this->developerController->delete($id);
    }
    parent::tearDown();
  }

}
